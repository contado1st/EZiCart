<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Notifications\StockAvailabilityNotification;
use Illuminate\Support\Facades\DB;

class StockSubscriptionService
{
    public function __construct(private TransactionAwareNotificationSender $notifications) {}

    public function state(Product $product): string
    {
        $product->loadMissing('seller', 'variations');
        $stock = (int) $product->stock;
        if ($product->variations->isNotEmpty()) {
            $stock = min($stock, (int) $product->variations->where('is_active', true)->sum('stock'));
        }
        if ($product->is_archived || $product->compliance_status !== 'approved' || $product->seller?->status !== 'approved' || $stock <= 0) {
            return 'unavailable';
        }

        return $stock <= (int) config('commerce.low_stock_threshold', 5) ? 'low' : 'available';
    }

    public function watch(User $buyer, Product $product): void
    {
        abort_unless($buyer->role === 'buyer', 403);
        abort_unless($product->compliance_status === 'approved' && ! $product->is_archived && $product->seller?->status === 'approved', 404);
        DB::table('stock_subscriptions')->insertOrIgnore([
            'user_id' => $buyer->id, 'product_id' => $product->id, 'last_state' => $this->state($product), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function check(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $subscription = DB::table('stock_subscriptions')->where('id', $id)->lockForUpdate()->first();
            if (! $subscription) {
                return;
            }
            $buyer = User::query()->where('role', 'buyer')->where('status', 'approved')->find($subscription->user_id);
            $product = Product::with(['seller', 'variations'])->find($subscription->product_id);
            if (! $buyer || ! $product) {
                return;
            }
            $state = $this->state($product);
            if ($state === $subscription->last_state) {
                return;
            }
            DB::table('stock_subscriptions')->where('id', $id)->update(['last_state' => $state, 'updated_at' => now()]);
            $this->notifications->send($buyer, new StockAvailabilityNotification($product, $state, $subscription->last_state));
        }, 3);
    }
}
