<?php

namespace App\Services;

use App\Models\FlashSale;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use Illuminate\Support\Facades\DB;

class FlashReminderService
{
    public function __construct(private TransactionAwareNotificationSender $notifications) {}

    public function subscribe(User $buyer, FlashSale $slot, bool $enabled): void
    {
        DB::transaction(function () use ($buyer, $slot, $enabled) {
            $buyer = User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            abort_unless($buyer->role === 'buyer' && $buyer->status === 'approved', 403);
            $slot = FlashSale::whereKey($slot->id)->lockForUpdate()->firstOrFail();
            if (! $enabled) {
                DB::table('flash_reminders')->where('buyer_id', $buyer->id)->where('flash_sale_id', $slot->id)->update(['cancelled_at' => now(), 'updated_at' => now()]);

                return;
            }
            abort_unless(! $slot->ended_at && $slot->ends_at->isFuture() && $slot->product->isListingVisible() && $slot->product->stock > 0 && $slot->product->seller->acceptsWebOrders()
                && $slot->allocated_quantity < $slot->quantity_limit && $this->validPrice($slot), 404);
            DB::table('flash_reminders')->insertOrIgnore(['buyer_id' => $buyer->id, 'flash_sale_id' => $slot->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('flash_reminders')->where('buyer_id', $buyer->id)->where('flash_sale_id', $slot->id)->update(['cancelled_at' => null, 'updated_at' => now()]);
            if ($slot->starts_at->lte(now()->addMinute())) {
                $reminder = DB::table('flash_reminders')->where('buyer_id', $buyer->id)->where('flash_sale_id', $slot->id)->lockForUpdate()->first();
                if ($reminder->notified_at === null) {
                    $this->notify($buyer, $slot, $reminder->id, $slot->starts_at->lte(now()) ? 'Flash sale is live' : 'Flash sale starts soon');
                }
            }
        });
    }

    public function deliver(): int
    {
        $count = 0;
        DB::table('flash_reminders')->whereNull('notified_at')->whereNull('cancelled_at')->whereIn('flash_sale_id', FlashSale::where('starts_at', '<=', now()->addMinute())->select('id'))->orderBy('id')->chunkById(100, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $count += DB::transaction(function () use ($row) {
                    $buyer = User::whereKey($row->buyer_id)->lockForUpdate()->first();
                    $slot = FlashSale::whereKey($row->flash_sale_id)->lockForUpdate()->first();
                    $current = DB::table('flash_reminders')->where('id', $row->id)->lockForUpdate()->first();
                    if (! $buyer || ! $slot || ! $current || $current->notified_at || $current->cancelled_at) {
                        return 0;
                    }
                    if ($slot->ended_at || $slot->starts_at->lte(now()) || $buyer->status !== 'approved' || $buyer->role !== 'buyer') {
                        DB::table('flash_reminders')->where('id', $current->id)->update(['cancelled_at' => now(), 'updated_at' => now()]);

                        return 0;
                    }
                    if ($slot->starts_at->gt(now()->addMinute())) {
                        return 0;
                    }
                    if (! $slot->product->isListingVisible() || $slot->product->stock < 1 || ! $slot->product->seller->acceptsWebOrders() || $slot->allocated_quantity >= $slot->quantity_limit || ! $this->validPrice($slot)) {
                        DB::table('flash_reminders')->where('id', $current->id)->update(['cancelled_at' => now(), 'updated_at' => now()]);

                        return 0;
                    }
                    $this->notify($buyer, $slot, $current->id, 'Flash sale starts soon');

                    return 1;
                });
            }
        });

        return $count;
    }

    private function notify(User $buyer, FlashSale $slot, int $reminderId, string $title): void
    {
        $this->notifications->send($buyer, new WebOperationsNotification(['category' => 'promotions', 'title' => $title, 'message' => $slot->product->name.' · PHP '.number_format($slot->unit_cents / 100, 2).'. Flash quantity is limited; checkout confirms availability.',
            'url' => route('product.show', $slot->product_id), 'flash_sale_id' => $slot->id]));
        DB::table('flash_reminders')->where('id', $reminderId)->update(['notified_at' => now(), 'updated_at' => now()]);
    }

    private function validPrice(FlashSale $slot): bool
    {
        $minimum = DB::table('products')->where('products.id', $slot->product_id)->selectRaw(ProductPriceService::regularMinimumSql().' AS minimum_price')->value('minimum_price');

        return $minimum !== null && $slot->unit_cents < CheckoutPricingService::cents($minimum);
    }
}
