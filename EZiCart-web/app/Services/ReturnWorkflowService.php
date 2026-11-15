<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReturnInspectionItem;
use App\Models\ShipmentFinancial;
use App\Models\ShipmentPriceAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnWorkflowService
{
    public function canView(User $actor, Order $order): bool
    {
        return $actor->status === 'approved' && ($actor->isCommerceAdmin() || $actor->isLogisticsAdmin()
            || ($actor->role === 'seller' && (int) $order->seller_id === $actor->id)
            || ($actor->role === 'buyer' && (int) $order->buyer_id === $actor->id)
            || ($actor->role === 'sorting_center' && (int) $order->sorting_center_id === $actor->id));
    }

    public function receiptConfirmed(Order $order): bool
    {
        return $order->status === 'RETURNED_TO_SELLER' && $order->return_handed_to_seller_at !== null
            && $order->trackingEvents()->where('event_type', 'returned_to_seller')->where('status', 'RETURNED_TO_SELLER')->where('actor_id', $order->seller_id)->where('created_at', '>=', $order->return_handed_to_seller_at)->exists()
            && DB::table('scan_events')->where('order_id', $order->id)->where('actor_id', $order->seller_id)->where('station', 'seller_return_receipt')->where('result', 'accepted')->where('created_at', '>=', $order->return_handed_to_seller_at)->exists();
    }

    /** Read-only state and navigation; the existing financial services decide at execution time. */
    public function summary(Order $order): array
    {
        $order->loadMissing('items', 'finance', 'refundExecutions', 'payout', 'dispute');
        $lines = ReturnInspectionItem::where('order_id', $order->id)->get()->keyBy('order_item_id');
        $shipment = ShipmentFinancial::where('order_id', $order->id)->first();
        $costs = ShipmentPriceAdjustment::where('order_id', $order->id)->where('measurements->kind', 'return_cost')->latest('id')->first();
        $receipt = $this->receiptConfirmed($order);
        $refund = $order->refundExecutions->sortByDesc('id')->first();
        $walletReady = $order->payment_method === 'WALLET' && app(OrderWalletService::class)->paymentReady($order);
        $undelivered = $order->delivered_at === null && ! $order->deliveryAttempts()->where('outcome', 'delivered')->exists();
        $staged = $order->finance?->ledger_funded_at !== null && $order->finance?->remittance_status === 'VERIFIED'
            && $order->finance?->due_cents === CheckoutPricingService::cents($order->total_amount) && $order->finance->due_cents > 0;
        $payoutSettled = $order->payout?->status === 'SETTLED';
        $refundMessage = match (true) {
            $order->refundExecutions->count() > 1 => 'Multiple historical refund records require administrator reconciliation; no additional refund is inferred.',
            $refund?->status === 'EXECUTED' => 'The refund execution is recorded. Inventory inspection and logistics cost settlement are separate.',
            $refund?->status === 'AUTHORIZED' && ! $receipt => 'Refund authorized; current seller-receipt evidence must be reviewed before execution.',
            $refund?->status === 'AUTHORIZED' => 'Refund authorized, not yet paid. Commerce administration must execute it through the existing funded refund action.',
            $order->payment_method === 'COD' && ! $order->finance?->collected_cents => 'No collected COD is recorded. Do not create a buyer cash refund for an uncollected order.',
            $order->payment_method !== 'WALLET' && $order->payment_method !== 'COD' => 'External provider refund integration is not connected. A payment-method label does not prove refundable funds.',
            ! $receipt => 'Wait for recorded seller receipt or administrator review of missing historical receipt evidence.',
            $payoutSettled => 'Seller proceeds were already settled. Recover/reconcile funds before refund authorization.',
            $order->payment_method === 'WALLET' && ! $walletReady => 'Wallet payment or account hold requires review before this wallet RTS flow can proceed.',
            $order->payment_method === 'WALLET' && ! $staged => 'Verified staged funds are missing, mismatched or sponsor funding is incomplete. Finance must reconcile funding before proceeding.',
            $order->payment_method === 'WALLET' && $undelivered => 'Undelivered wallet RTS is ready for commerce-admin authorization review. Authorizing does not send money.',
            default => 'Delivered returns follow the existing marketplace dispute/refund decision. Return status alone never authorizes money.',
        };

        return ['receipt_confirmed' => $receipt, 'lines' => $lines, 'refund' => $refund, 'refund_message' => $refundMessage,
            'can_authorize_rts' => $receipt && $undelivered && $walletReady && $staged && ! $payoutSettled && $refund === null,
            'can_execute_wallet' => $receipt && $walletReady && $staged && ! $payoutSettled && $order->refundExecutions->count() === 1 && $refund?->status === 'AUTHORIZED',
            'shipment' => $shipment, 'return_cost' => $costs,
            'cost_message' => $shipment?->settled_at ? 'A logistics settlement snapshot is recorded. Review its actual allocations and held components.'
                : ($order->payment_method === 'COD' && ! $order->finance?->collected_cents ? 'No buyer cash refund is due for uncollected COD. Return-cost funding requires separate finance reconciliation; this screen does not create a refund or transport credit.'
                    : ($refund?->status !== 'EXECUTED' ? 'Buyer refund must be executed before treasury-funded return-cost allocation.'
                    : (! $shipment?->rate_version_id ? 'No approved compensation schedule is recorded. Legacy shipping is not converted into invented rider/hub percentages.'
                        : ($costs?->status === 'APPROVED' ? 'Return-cost funding was approved. Logistics administration may review eligible allocation; no seller sale proceeds are released.'
                            : ($costs?->status === 'PROPOSED' ? 'Return-cost request awaits separate commerce/treasury approval and available funding.' : 'Logistics administration must request evidenced return-cost funding, followed by separate treasury approval.'))))),
            'inspection_reason' => DB::table('administrative_audits')->where('entity_type', $order->getMorphClass())->where('entity_id', $order->id)->where('action', 'return_inspected')->latest('id')->value('reason')];
    }
}
