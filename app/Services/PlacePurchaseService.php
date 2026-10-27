<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ParcelTrackingEvent;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\OrderWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlacePurchaseService
{
    public function __construct(private CheckoutPricingService $pricing, private TransactionAwareNotificationSender $notifications, private OrderFinancialService $finances) {}

    public function place(User $buyer, string $token, string $requestHash, array $cart, string $reviewedHash, ?int $voucherId, array $address): Purchase
    {
        return DB::transaction(function () use ($buyer, $token, $requestHash, $cart, $reviewedHash, $voucherId, $address): Purchase {
            // SQLite has no row-level FOR UPDATE. Acquire its writer lock before reads.
            if (DB::getDriverName() === 'sqlite') {
                DB::table('users')->where('id', $buyer->id)->update(['updated_at' => DB::raw('updated_at')]);
            }
            User::query()->whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            $existing = Purchase::query()->where('buyer_id', $buyer->id)->where('checkout_token', $token)->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw ValidationException::withMessages(['checkout_token' => 'This checkout was already submitted with different details. Start a new checkout.']);
                }

                return $existing;
            }
            $voucher = $voucherId ? Voucher::query()->whereKey($voucherId)->lockForUpdate()->first() : null;
            if ($voucherId && ! $voucher) {
                throw ValidationException::withMessages(['voucher_code' => 'The voucher is no longer available. Review checkout again.']);
            }
            $quote = $this->pricing->quote($cart, $voucher, true);
            if (! hash_equals($quote['hash'], $reviewedHash)) {
                throw ValidationException::withMessages(['quote' => 'Prices or discounts changed. Review the updated checkout total before placing your order.']);
            }
            $purchase = Purchase::query()->create([
                'buyer_id' => $buyer->id, 'checkout_token' => $token,
                'request_hash' => $requestHash, 'total_cents' => $quote['total_cents'],
            ]);
            foreach ($quote['groups'] as $sellerId => $group) {
                $order = Order::query()->forceCreate([
                    ...$address, 'purchase_id' => $purchase->id, 'buyer_id' => $buyer->id, 'seller_id' => $sellerId,
                    'order_number' => 'EZC-'.Str::upper(Str::random(10)),
                    'subtotal' => $group['subtotal_cents'] / 100,
                    'discount_amount' => $group['discount_cents'] / 100,
                    'platform_discount_amount' => $group['platform_discount_cents'] / 100,
                    'voucher_code' => $group['eligible'] ? $voucher?->code : null,
                    'shipping_fee' => $group['shipping_cents'] / 100,
                    'commission_fee' => $group['commission_cents'] / 100,
                    'total_amount' => $group['total_cents'] / 100,
                    'status' => 'PLACED', 'payment_method' => 'COD',
                    'seller_response_due_at' => now()->addHours((int) config('commerce.seller_response_hours')),
                ]);
                $this->finances->initialize($order);
                foreach ($group['lines'] as $line) {
                    $updated = Product::query()->whereKey($line['product_id'])->where('stock', '>=', $line['quantity'])->decrement('stock', $line['quantity']);
                    if ($updated !== 1) {
                        throw ValidationException::withMessages(['cart' => 'An item just sold out. No order was placed.']);
                    }
                    if ($line['variation_id'] !== null) {
                        $updated = ProductVariation::query()->whereKey($line['variation_id'])->where('product_id', $line['product_id'])->where('stock', '>=', $line['quantity'])->decrement('stock', $line['quantity']);
                        if ($updated !== 1) {
                            throw ValidationException::withMessages(['cart' => 'A variation just sold out. No order was placed.']);
                        }
                    }
                    OrderItem::query()->create([
                        'order_id' => $order->id, 'product_id' => $line['product_id'], 'variation_id' => $line['variation_id'],
                        'variation_info' => $line['variation_info'], 'product_name' => $line['name'],
                        'unit_price' => $line['unit_cents'] / 100, 'quantity' => $line['quantity'], 'item_total' => $line['item_cents'] / 100,
                    ]);
                }
                ParcelTrackingEvent::query()->create([
                    'order_id' => $order->id, 'actor_id' => $buyer->id, 'event_type' => 'order_placed',
                    'status' => 'PLACED', 'location' => $order->municipality, 'notes' => 'Order placed by buyer.',
                ]);
                $this->notifications->send($order->seller, new OrderWorkflowNotification($order, 'order_placed', 'A buyer placed an order. Confirm within '.config('commerce.seller_response_hours').' hours.'));
            }
            if ($voucher) {
                $voucher->increment('used_count');
            }

            return $purchase;
        }, 3);
    }
}
