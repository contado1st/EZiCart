<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ParcelCorrection;
use App\Models\ParcelIncident;
use App\Models\ParcelTransfer;
use App\Models\SellerReturnResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderCompletionEligibilityService
{
    /** Shared by buyer receipt and the non-routable system transition. No writes or fund release. */
    public function holdReason(Order $order): ?string
    {
        if (ParcelIncident::where('open_order_id', $order->id)->lockForUpdate()->first() !== null) {
            return 'Lost/Damaged incident hold: custody and claim review are required.';
        }
        if ($order->pendingCancellation()->exists() || ParcelTransfer::where('active_order_id', $order->id)->exists()) {
            return 'A cancellation or physical transfer is still open.';
        }
        if ($order->dispute()->whereIn('status', ['PENDING', 'UNDER_REVIEW', 'REFUND_APPROVED', 'REPLACEMENT_APPROVED'])->exists()
            || SellerReturnResponse::whereHas('dispute', fn ($q) => $q->where('order_id', $order->id))->where('stage', '!=', 'resolved')->exists()
            || $order->refundExecutions()->exists()) {
            return 'An active return, dispute or refund prevents completion.';
        }
        if (ParcelCorrection::where('order_id', $order->id)->where('status', 'OPEN')->exists() || $order->payout()->where('status', 'ON_HOLD')->exists()
            || DB::table('logistics_exceptions')->where('order_id', $order->id)->where('status', 'OPEN')->exists()) {
            return 'An unresolved correction or settlement hold prevents completion.';
        }
        if (! app(OrderWalletService::class)->paymentReady($order)) {
            return 'Payment verification requires administrator review.';
        }
        if (! $order->delivered_at || $order->delivered_at->isFuture() || ! $order->delivery_code_used_at || $order->delivery_code_used_at->gt($order->delivered_at)) {
            return 'Verified delivery time and delivery-code evidence are required.';
        }
        $proof = $order->deliveryAttempts()->where('outcome', 'delivered')->where('rider_id', $order->delivery_courier_id)
            ->where('attempted_at', $order->delivered_at)->whereNotNull('proof_path')->latest('id')->value('proof_path');
        if (! $proof || ! Storage::disk('private')->exists($proof)) {
            return 'The verified delivery proof file is missing; administrator review is required.';
        }
        $finance = $order->finance()->first();
        if ($order->payment_method === 'COD' && (! $finance || $finance->due_cents !== CheckoutPricingService::cents($order->total_amount)
            || $finance->collected_cents !== $finance->due_cents || CheckoutPricingService::cents($order->cod_collected_amount) !== $finance->due_cents)) {
            return 'Recorded COD collection and order amounts require reconciliation.';
        }

        return null;
    }

    public function automaticReason(Order $order): ?string
    {
        if ($order->status !== 'DELIVERED') {
            return 'Only Delivered orders can complete automatically.';
        }
        if (! $order->completion_enrolled_at || ! $order->auto_completion_due_at) {
            return 'Not enrolled. Historical deliveries require explicit operator opt-in.';
        }
        if (! $order->delivered_at || ! $order->auto_completion_due_at->equalTo($order->delivered_at->copy()->addHours(168))) {
            return 'The completion deadline does not match verified delivery plus 168 elapsed hours.';
        }
        if ($order->auto_completion_due_at->isFuture()) {
            return 'The 168-hour buyer receipt window is still open.';
        }

        return $this->holdReason($order);
    }

    public function afterRollout(Order $order): bool
    {
        $cutoff = config('commerce.auto_completion_rollout_at');

        return filled($cutoff) && $order->delivered_at !== null && $order->delivered_at->gte(Carbon::parse($cutoff));
    }
}
