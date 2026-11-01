<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ParcelTrackingEvent;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReplacementOrderService
{
    public function __construct(private CheckoutPricingService $pricing, private AdministrativeAuditService $audits, private OrderFinancialService $finances, private TransactionAwareNotificationSender $notifications) {}

    public function create(Dispute $dispute, User $seller): Order
    {
        abort_unless($seller->role === 'seller' && $seller->id === $dispute->seller_id, 403);

        return DB::transaction(function () use ($dispute, $seller): Order {
            $original = Order::query()->whereKey($dispute->order_id)->lockForUpdate()->firstOrFail();
            $lockedDispute = Dispute::query()->whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            $existing = Order::query()->where('replacement_dispute_id', $dispute->id)->first();
            if ($existing) {
                return $existing;
            }
            abort_unless($lockedDispute->status === 'REPLACEMENT_APPROVED' && $original->status === 'RETURNED_TO_SELLER', 422, 'Replacement requires an approved dispute and confirmed return receipt.');
            $cart = $original->items->map(fn ($item) => ['product_id' => $item->product_id, 'variation_id' => $item->variation_id, 'quantity' => $item->quantity])->all();
            $quote = $this->pricing->quote($cart, null, true);
            $replacement = Order::query()->forceCreate([
                ...$original->only(['buyer_id', 'seller_id', 'purchase_id', 'recipient_name', 'recipient_contact', 'region', 'province', 'municipality', 'barangay', 'house_number', 'postal_code', 'street_address', 'destination_area_id']),
                'replacement_for_order_id' => $original->id, 'replacement_dispute_id' => $dispute->id,
                'order_number' => 'EZC-'.Str::upper(Str::random(10)), 'status' => 'PLACED', 'payment_method' => 'COD',
                'subtotal' => 0, 'discount_amount' => 0, 'shipping_fee' => 0, 'commission_fee' => 0, 'total_amount' => 0,
                'seller_response_due_at' => now()->addHours((int) config('commerce.seller_response_hours')),
                'notes' => 'No-charge replacement for '.$original->order_number.'. Do not collect payment.',
            ]);
            foreach ($quote['lines'] as $line) {
                abort_unless(Product::query()->whereKey($line['product_id'])->where('stock', '>=', $line['quantity'])->decrement('stock', $line['quantity']) === 1, 422, 'Replacement stock is unavailable.');
                if ($line['variation_id']) {
                    abort_unless(ProductVariation::query()->whereKey($line['variation_id'])->where('stock', '>=', $line['quantity'])->decrement('stock', $line['quantity']) === 1, 422, 'Replacement variation is unavailable.');
                }
                OrderItem::create(['order_id' => $replacement->id, 'product_id' => $line['product_id'], 'variation_id' => $line['variation_id'], 'variation_info' => $line['variation_info'], 'product_name' => $line['name'], 'unit_price' => 0, 'quantity' => $line['quantity'], 'item_total' => 0]);
            }
            $this->finances->initialize($replacement);
            $this->audits->record($seller, $replacement, 'replacement_created', [], ['original_order_id' => $original->id, 'dispute_id' => $dispute->id], 'Approved replacement, stock reserved and no additional buyer charge.');
            ParcelTrackingEvent::create(['order_id' => $replacement->id, 'actor_id' => $seller->id, 'status' => 'PLACED', 'event_type' => 'replacement_created', 'notes' => 'No-charge replacement shipment.']);
            $this->notifications->send([$replacement->buyer, $seller], new OrderWorkflowNotification($replacement, 'replacement_created', 'A replacement shipment was created without an additional charge.'));

            return $replacement;
        }, 3);
    }
}
