<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ParcelTrackingEvent;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Purchase;
use App\Models\ShipmentFinancial;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBudget;
use App\Notifications\OrderWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlacePurchaseService
{
    public function __construct(private CheckoutPricingService $pricing, private TransactionAwareNotificationSender $notifications, private OrderFinancialService $finances) {}

    public function place(User $buyer, string $token, string $requestHash, array $cart, string $reviewedHash, ?int $voucherId, array $address, string $paymentMethod = 'COD'): Purchase
    {
        return DB::transaction(function () use ($buyer, $token, $requestHash, $cart, $reviewedHash, $voucherId, $address, $paymentMethod): Purchase {
            abort_unless(in_array($paymentMethod, ['COD', 'WALLET'], true) && $buyer->role === 'buyer' && $buyer->status === 'approved', 422, 'Unsupported checkout payment method or buyer.');
            // SQLite has no row-level FOR UPDATE. Acquire its writer lock before reads.
            if (DB::getDriverName() === 'sqlite') {
                DB::table('users')->where('id', $buyer->id)->update(['updated_at' => DB::raw('updated_at')]);
            }
            User::query()->whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            if (app(DeliveryPricingService::class)->enabled() && (! filled($address['province'] ?? null) || ! filled($address['municipality'] ?? null))) {
                throw ValidationException::withMessages(['shipping' => 'A complete destination is required for the final delivery quote.']);
            }
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
            // Additive rules for newly configured offers/closures, shared by every checkout caller.
            // Legacy vouchers have null recipient/per-buyer limits and open shops retain existing behavior.
            if ($voucher) {
                app(VoucherClaimService::class)->claim($buyer, $voucher);
                app(VoucherClaimService::class)->assertUsable($buyer, $voucher);
            }
            app(ShopAvailabilityService::class)->assertOpen($cart);
            $quote = $this->pricing->quote($cart, $voucher, true, ['address' => $address, 'payment_method' => $paymentMethod]);
            if ($quote['total_cents'] > config('financial.maximum_transaction_cents')) {
                throw ValidationException::withMessages(['cart' => 'This order exceeds the supported financial transaction limit. Reduce its quantity.']);
            }
            if (count($quote['groups']) > 1) {
                throw ValidationException::withMessages(['cart' => 'Choose products from one shop per checkout. Items from other shops stay saved in your cart.']);
            }
            if (! hash_equals($quote['hash'], $reviewedHash)) {
                throw ValidationException::withMessages(['quote' => 'Prices or discounts changed. Review the updated checkout total before placing your order.']);
            }
            $purchase = Purchase::query()->create([
                'buyer_id' => $buyer->id, 'checkout_token' => $token,
                'request_hash' => $requestHash, 'total_cents' => $quote['total_cents'],
            ]);
            foreach ($quote['groups'] as $sellerId => $group) {
                $budgeted = $voucher && VoucherBudget::where('voucher_id', $voucher->id)->exists();
                $itemSponsor = $budgeted && $voucher->seller_id !== null ? $group['discount_cents'] : 0;
                $platformShipping = $budgeted && $voucher->seller_id === null ? $group['shipping_discount_cents'] : 0;
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
                    'status' => 'PLACED', 'payment_method' => $paymentMethod, 'financial_version' => 1,
                    'seller_promo_funded_cents' => $itemSponsor, 'platform_shipping_funded_cents' => $platformShipping, 'shipping_discount_cents' => $group['shipping_discount_cents'],
                    'seller_response_due_at' => now()->addHours((int) config('commerce.seller_response_hours')),
                ]);
                ShipmentFinancial::create(['order_id' => $order->id, 'rate_version_id' => $group['shipping_snapshot']['rate_version_id'], 'quote_snapshot' => $group['shipping_snapshot'], 'buyer_shipping_cents' => $group['shipping_cents'], 'seller_shipping_cents' => $budgeted && $voucher->seller_id !== null ? $group['shipping_discount_cents'] : 0, 'platform_shipping_cents' => $platformShipping]);
                if ($voucher) {
                    app(VoucherFundingService::class)->consume($voucher, $order, $group['discount_cents'] + $group['shipping_discount_cents'], $buyer);
                }
                $finance = $this->finances->initialize($order);
                if ($budgeted && $voucher->seller_id === null) {
                    $finance->update(['funded_cents' => $finance->platform_subsidy_cents, 'funded_at' => now()]);
                }
                if ($paymentMethod === 'WALLET') {
                    app(OrderWalletService::class)->pay($order, $buyer);
                }
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
                        'unit_weight_grams' => $line['product']->weight_grams,
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
                DB::table('voucher_claims')->where('voucher_id', $voucher->id)->where('buyer_id', $buyer->id)->increment('redemption_count', 1, ['updated_at' => now()]);
                if ($voucher->usage_limit && $voucher->used_count >= $voucher->usage_limit && VoucherBudget::where('voucher_id', $voucher->id)->exists()) {
                    app(VoucherFundingService::class)->release($voucher, null);
                }
            }

            return $purchase;
        }, 3);
    }
}
