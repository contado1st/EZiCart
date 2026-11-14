<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use Illuminate\Support\Facades\DB;

class WalletOrderPaymentService
{
    public function __construct(private OrderWalletService $wallet, private TransactionAwareNotificationSender $notifications,
        private AdministrativeAuditService $audits) {}

    public function pay(Order $order, User $buyer): Order
    {
        app(WalletFundingService::class)->assertSandbox();

        return DB::transaction(function () use ($order, $buyer): Order {
            $buyer = User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($buyer->role === 'buyer' && $buyer->status === 'approved' && $order->buyer_id === $buyer->id, 403);
            abort_unless($order->payment_method === 'WALLET' && $order->wallet_payment_due_at !== null, 422, 'This is not a deferred wallet purchase.');
            if ($order->wallet_transaction_id !== null) {
                abort_unless($this->wallet->paymentReady($order) && $order->status !== 'CANCELLED', 422, 'Recorded payment requires reconciliation.');

                return $order;
            }
            abort_unless($order->isAwaitingWalletPayment() && $order->wallet_payment_due_at->isFuture() && $order->wallet_paid_cents === 0
                && $order->inventory_restored_at === null && $order->picked_up_at === null && $order->seller_handover_at === null
                && $order->received_at === null && $order->hub_released_at === null && ! $order->pendingCancellation()->exists(), 422, 'The reservation expired or cannot be paid. Review your orders.');
            app(ShopAvailabilityService::class)->assertOpen($order->items()->get(['product_id', 'quantity'])->toArray());
            app(OrderTransitionService::class)->validateSellerOrderInventory($order);
            $this->wallet->pay($order, $buyer);
            $order->forceFill(['seller_response_due_at' => now()->addHours((int) config('commerce.seller_response_hours'))])->save();
            ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $buyer->id, 'event_type' => 'wallet_payment_received', 'status' => $order->status,
                'notes' => 'Full demo wallet payment posted. Seller preparation can now begin; no COD is due.']);
            $this->audits->record($buyer, $order, 'wallet_order_paid', ['wallet_paid_cents' => 0], ['wallet_paid_cents' => $order->wallet_paid_cents, 'transaction_id' => $order->wallet_transaction_id], 'Buyer confirmed payment from the internal demo wallet.');
            $this->notifications->send([$buyer, $order->seller], new OrderWorkflowNotification($order, 'wallet_payment_received',
                'Full demo wallet payment recorded. The seller may confirm and prepare the order. No COD cash is due.'));

            return $order;
        }, 3);
    }

    public function cancel(Order $order, User $buyer): void
    {
        DB::transaction(function () use ($order, $buyer): void {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($buyer->status === 'approved' && $buyer->role === 'buyer' && $order->buyer_id === $buyer->id, 403);
            abort_unless($order->isAwaitingWalletPayment(), 422, 'Paid orders use the normal cancellation approval process.');
            app(OrderTransitionService::class)->transition($order, $buyer, OrderStatus::Cancelled, 'unpaid_wallet_cancelled', notes: 'Buyer cancelled an unpaid wallet reservation. No wallet payment or refund was made.');
        }, 3);
    }
}
