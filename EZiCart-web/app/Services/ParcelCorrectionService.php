<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ParcelCorrection;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ParcelCorrectionService
{
    public function __construct(private AdministrativeAuditService $audits, private TransactionAwareNotificationSender $notifications) {}

    public function query(User $actor): Builder
    {
        abort_unless($actor->status === 'approved' && in_array($actor->role, ['sorting_center', 'logistics_admin'], true), 403);

        return ParcelCorrection::query()->when($actor->role === 'sorting_center', fn ($q) => $q->where('hub_id', $actor->id));
    }

    public function snapshot(Order $order): array
    {
        return ['status' => $order->status, 'pickup_courier_id' => $order->pickup_courier_id === null ? null : (int) $order->pickup_courier_id,
            'delivery_courier_id' => $order->delivery_courier_id === null ? null : (int) $order->delivery_courier_id,
            'sorting_center_id' => $order->sorting_center_id === null ? null : (int) $order->sorting_center_id,
            'destination_area_id' => $order->destination_area_id === null ? null : (int) $order->destination_area_id,
            'delivery_area' => $order->delivery_area, 'parcel_code_version' => (int) $order->parcel_code_version];
    }

    public function evidenceIntact(ParcelCorrection $correction): bool
    {
        if (! Storage::disk('private')->exists($correction->evidence_path)) {
            return false;
        }
        $checksum = @hash_file('sha256', Storage::disk('private')->path($correction->evidence_path));

        return is_string($checksum) && hash_equals($correction->evidence_sha256, $checksum);
    }

    public function request(Order $order, User $actor, string $reason, string $evidence): ParcelCorrection
    {
        $reason = trim($reason);
        validator(['reason' => $reason], ['reason' => ['required', 'string', 'min:5', 'max:500']])->validate();

        return DB::transaction(function () use ($order, $actor, $reason, $evidence): ParcelCorrection {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->role === 'sorting_center' && $actor->status === 'approved', 403);
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(app(HubOrderScope::class)->query($actor)->whereKey($locked->id)->exists(), 404);
            abort_unless(in_array($locked->status, [...LogisticsQueueService::STATUSES, 'READY_FOR_PICKUP'], true), 422, 'Use correction review for a parcel already in the logistics workflow.');
            if (ParcelCorrection::where('open_order_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['correction' => 'This parcel already has an open correction review. Review the existing case or contact logistics administration.']);
            }
            abort_unless(Storage::disk('private')->exists($evidence), 422, 'Private supporting evidence is required.');
            $checksum = @hash_file('sha256', Storage::disk('private')->path($evidence));
            abort_unless(is_string($checksum), 422, 'Evidence could not be read. Upload it again.');
            $correction = ParcelCorrection::create(['order_id' => $locked->id, 'open_order_id' => $locked->id, 'hub_id' => $actor->id, 'requested_by' => $actor->id,
                'snapshot_before' => $this->snapshot($locked), 'baseline_event_id' => $locked->trackingEvents()->max('id'), 'reason' => $reason,
                'evidence_path' => $evidence, 'evidence_sha256' => $checksum, 'status' => 'OPEN']);
            $this->audits->record($actor, $correction, 'parcel_correction_requested', $correction->snapshot_before,
                ['review_status' => 'OPEN', 'order_id' => $locked->id, 'parcel_state' => $correction->snapshot_before], $reason);
            $this->notifications->send(User::where('role', 'logistics_admin')->where('status', 'approved')->get(), new WebOperationsNotification([
                'message' => 'A hub requested correction review for '.$locked->order_number.'. No parcel state changed.', 'url' => route('logistics-corrections.show', $correction)]));

            return $correction;
        }, 3);
    }

    public function review(ParcelCorrection $correction, User $actor, string $outcome, string $reason, ?int $eventId): ParcelCorrection
    {
        $reason = trim($reason);
        validator(['outcome' => $outcome, 'reason' => $reason], ['outcome' => ['required', 'in:RESOLVED,REJECTED'], 'reason' => ['required', 'string', 'min:5', 'max:1000']])->validate();

        return DB::transaction(function () use ($correction, $actor, $outcome, $reason, $eventId): ParcelCorrection {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->status === 'approved' && $actor->isLogisticsAdmin(), 403);
            $orderId = ParcelCorrection::whereKey($correction->id)->value('order_id');
            $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
            $locked = ParcelCorrection::whereKey($correction->id)->lockForUpdate()->firstOrFail();
            abort_if((int) $locked->requested_by === $actor->id, 403, 'An independent logistics administrator must review the request.');
            if ($locked->status !== 'OPEN') {
                abort_unless($locked->status === $outcome && (int) $locked->reviewed_by === $actor->id && $locked->decision_reason === $reason && $locked->tracking_event_id === $eventId, 409, 'This review already has a different recorded decision. Refresh its history.');

                return $locked;
            }
            $after = $this->snapshot($order);
            if ($outcome === 'RESOLVED') {
                abort_unless($this->evidenceIntact($locked), 422, 'Supporting evidence is missing or changed. Investigate before resolving.');
                $event = ParcelTrackingEvent::whereKey($eventId)->where('order_id', $order->id)->where('id', '>', $locked->baseline_event_id ?? 0)
                    ->where('created_at', '>=', $locked->created_at)->where('status', $order->status)->lockForUpdate()->first();
                abort_unless($event && $after != $locked->snapshot_before, 422, 'First complete the appropriate guarded parcel workflow. Link its newer event matching the current state; closing review cannot change status or custody.');
                if (in_array($order->status, ['DELIVERED', 'COMPLETED'], true)) {
                    $this->assertDeliveryEvidence($order);
                }
                if ($order->status === 'RETURNED_TO_SELLER') {
                    abort_unless(app(ReturnWorkflowService::class)->receiptConfirmed($order), 422, 'Recorded physical seller receipt is required; a return label alone is insufficient.');
                }
            } else {
                abort_if($eventId !== null, 422, 'Rejection does not link a completed parcel transition.');
            }
            $locked->forceFill(['status' => $outcome, 'open_order_id' => null, 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                'decision_reason' => $reason, 'snapshot_after' => $after, 'tracking_event_id' => $eventId])->save();
            $this->audits->record($actor, $locked, 'parcel_correction_'.strtolower($outcome), $locked->snapshot_before,
                ['review_status' => $outcome, 'parcel_state' => $after, 'tracking_event_id' => $eventId], $reason);
            $this->notifications->send($locked->hub, new WebOperationsNotification(['message' => 'Correction review for '.$order->order_number.' was '.strtolower($outcome).'. Review the recorded decision; closure itself changed no parcel state.', 'url' => route('logistics-corrections.show', $locked)]));

            return $locked;
        }, 3);
    }

    private function assertDeliveryEvidence(Order $order): void
    {
        $attempt = $order->deliveryAttempts()->where('rider_id', $order->delivery_courier_id)->where('outcome', 'delivered')->whereNotNull('proof_path')->latest('id')->first();
        abort_unless($order->delivered_at && $order->delivery_code_used_at && $attempt && Storage::disk('private')->exists($attempt->proof_path), 422, 'Delivery proof and used buyer code must be recorded by the actual delivery workflow.');
        if ($order->payment_method === 'WALLET') {
            abort_unless(app(OrderWalletService::class)->paymentReady($order), 422, 'Wallet payment verification must be reconciled first.');
        } else {
            $finance = $order->finance()->first();
            abort_unless($order->payment_method === 'COD' && $order->cod_collected_amount !== null && $finance
                && (int) $finance->collector_id === (int) $order->delivery_courier_id && $finance->collected_cents === CheckoutPricingService::cents($order->total_amount)
                && CheckoutPricingService::cents($order->cod_collected_amount) === $finance->collected_cents, 422, 'An exact recorded COD collection is required; closing review cannot collect cash.');
        }
    }
}
