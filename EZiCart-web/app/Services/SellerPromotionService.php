<?php

namespace App\Services;

use App\Models\SellerPromotion;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use Illuminate\Support\Facades\DB;

class SellerPromotionService
{
    public function deliver(): int
    {
        $sent = 0;
        SellerPromotion::whereNull('cancelled_at')->where('starts_at', '<=', now())->where('expires_at', '>', now())->with(['seller', 'product', 'voucher'])->chunkById(100, function ($promotions) use (&$sent) {
            foreach ($promotions as $promotion) {
                if (! $promotion->seller->acceptsWebOrders() || ($promotion->product_id && ($promotion->product?->compliance_status !== 'approved' || $promotion->product->is_archived))
                    || ($promotion->voucher_id && (! $promotion->voucher?->is_active || $promotion->voucher->expires_at?->isPast()))) {
                    continue;
                }
                $promotion->seller->shopFollowers()->where('role', 'buyer')->where('status', 'approved')->where('seller_promotions_enabled', true)->select('users.*')->chunkById(100, function ($buyers) use ($promotion, &$sent) {
                    foreach ($buyers as $buyer) {
                        $sent += DB::transaction(function () use ($promotion, $buyer) {
                            $buyer = User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
                            $campaign = SellerPromotion::whereKey($promotion->id)->lockForUpdate()->firstOrFail();
                            if (! $buyer->seller_promotions_enabled || $buyer->status !== 'approved' || $campaign->cancelled_at || $campaign->expires_at->isPast()
                                || ! $buyer->followedShops()->whereKey($campaign->seller_id)->exists()
                                || DB::table('promotion_deliveries')->where('seller_promotion_id', $campaign->id)->where('buyer_id', $buyer->id)->exists()
                                || DB::table('promotion_deliveries')->where('buyer_id', $buyer->id)->where('created_at', '>=', now()->subWeek())->count() >= config('web-commerce.promotion_buyer_weekly_limit')) {
                                return 0;
                            }
                            DB::table('promotion_deliveries')->insert(['seller_promotion_id' => $campaign->id, 'buyer_id' => $buyer->id, 'created_at' => now(), 'updated_at' => now()]);
                            $url = $campaign->product_id ? route('product.show', $campaign->product_id) : ($campaign->voucher_id ? route('buyer.vouchers.index') : route('shops.show', $campaign->seller_id));
                            app(TransactionAwareNotificationSender::class)->send($buyer, new WebOperationsNotification(['category' => 'promotions', 'promotion_id' => $campaign->id,
                                'seller_name' => $promotion->seller->business_name ?: $promotion->seller->first_name,
                                'title' => $campaign->title, 'message' => $campaign->message, 'url' => $url, 'expires_at' => $campaign->expires_at->toIso8601String()]));

                            return 1;
                        }, 3);
                    }
                }, 'users.id', 'id');
            }
        });

        return $sent;
    }
}
