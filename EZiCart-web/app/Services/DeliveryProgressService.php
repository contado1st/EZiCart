<?php

namespace App\Services;

use App\Models\Order;

class DeliveryProgressService
{
    public function describe(Order $order): array
    {
        $ready = app(OrderWalletService::class)->paymentReady($order);
        // Query complete attempt history even if a caller prefetched only one rider's attempts.
        $attempts = $order->deliveryAttempts()->orderBy('id')->get();
        $failures = $attempts->where('outcome', 'failed')->count();
        $limit = max(1, (int) config('logistics.maximum_delivery_attempts', 3));
        $lastFailure = $attempts->where('outcome', 'failed')->last();
        $scheduled = $attempts->first(fn ($attempt) => $attempt->outcome === 'scheduled' && (int) $attempt->rider_id === (int) $order->delivery_courier_id);
        $assignment = $order->deliveryAssignments()->where('status', 'active')->where('rider_id', $order->delivery_courier_id)->first();
        $deadline = $assignment?->assigned_at?->copy()->addMinutes((int) config('commerce.rider_acceptance_minutes', 15));
        $expired = $assignment && ! $assignment->accepted_at && $deadline?->lte(now());
        $number = $order->status === 'DELIVERY_FAILED' || $failures >= $limit ? ($lastFailure?->attempt_no ?? 1)
            : ($scheduled?->attempt_no ?? ((int) $attempts->where('outcome', '!=', 'scheduled')->max('attempt_no') + 1));
        $step = match (true) {
            $order->status === 'DELIVERY_FAILED' => $failures >= $limit ? 'Return required. Bring the parcel back for a scanned Returns or Exception hub intake before logistics arranges seller return.' : 'Bring the parcel back for scanned Returns or Exception hub intake. Then propose a retry from Work history; logistics decides the schedule and rider.',
            ! $ready => 'Payment verification is on hold. Do not start delivery or collect cash; contact administration.',
            $failures >= $limit => 'Delivery attempt limit reached. Follow logistics seller-return instructions; do not start another delivery attempt.',
            $order->status === 'ASSIGNED_TO_RIDER' && ! $order->hub_released_at && ! $assignment => 'Logistics must review the missing acceptance record and verify the physical hub handoff.',
            $order->status === 'ASSIGNED_TO_RIDER' && ! $order->hub_released_at && $expired => 'The acceptance deadline expired. Ask logistics to assign this parcel again.',
            $order->status === 'ASSIGNED_TO_RIDER' && ! $order->hub_released_at && ! $assignment->accepted_at => 'Accept the assignment before its deadline, then wait for logistics to verify physical hub handoff.',
            $order->status === 'ASSIGNED_TO_RIDER' && ! $order->hub_released_at => 'Assignment accepted. Wait for logistics to verify physical hub handoff.',
            $scheduled?->scheduled_at?->isFuture() => 'Wait for the scheduled retry time before starting delivery.',
            $order->status === 'ASSIGNED_TO_RIDER' => 'Hub handoff recorded. Start delivery when you are ready to travel to the recipient.',
            $order->status === 'OUT_FOR_DELIVERY' => $order->payment_method === 'COD' ? 'At the recipient: record the exact cash collected, buyer delivery code, recipient confirmation and required delivery proof.' : 'At the recipient: do not collect cash. Buyer delivery code, recipient confirmation and delivery proof are still required.',
            default => 'Follow the recorded parcel stage and physical handover instructions.',
        };

        return ['payment_ready' => $ready, 'failures' => $failures, 'attempt_no' => max(1, $number), 'attempt_limit' => $limit,
            'return_required' => $failures >= $limit, 'scheduled_at' => $scheduled?->scheduled_at, 'accepted_at' => $assignment?->accepted_at,
            'acceptance_due_at' => $deadline,
            'can_accept' => $ready && ! $expired && $order->status === 'ASSIGNED_TO_RIDER' && ! $order->hub_released_at && $assignment && ! $assignment->accepted_at,
            'code_usable' => app(DeliveryCodeService::class)->isUsable($order), 'next_step' => $step];
    }
}
