<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ParcelIncident;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ParcelIncidentService
{
    public function __construct(private AdministrativeAuditService $audits, private TransactionAwareNotificationSender $notifications) {}

    public static function assertClear(Order $order): void
    {
        abort_if(ParcelIncident::where('open_order_id', $order->id)->lockForUpdate()->first() !== null, 422, 'Lost/Damaged incident hold: logistics must reconcile custody and administration must review the claim before this action.');
    }

    public function query(User $actor): Builder
    {
        abort_unless($actor->status === 'approved', 403);

        return ParcelIncident::query()->when(! $actor->isCommerceAdmin() && ! $actor->isLogisticsAdmin(), fn ($q) => $q->whereHas('order', fn ($o) => $this->orders($actor, $o)));
    }

    public function orders(User $actor, ?Builder $query = null): Builder
    {
        $query ??= Order::query();

        return match ($actor->role) {
            'admin', 'ecommerce_admin', 'logistics_admin' => $query,
            'buyer' => $query->where('buyer_id', $actor->id),
            'seller' => $query->where('seller_id', $actor->id),
            'courier' => $query->where(fn ($q) => $q->where('pickup_courier_id', $actor->id)->orWhere('delivery_courier_id', $actor->id)->orWhereHas('returnPickupAssignment', fn ($a) => $a->where('rider_id', $actor->id))->orWhereHas('transfers', fn ($t) => $t->where('carrier_rider_id', $actor->id))),
            'sorting_center' => $query->where(fn ($q) => $q->whereIn('orders.id', app(HubOrderScope::class)->query($actor)->select('orders.id'))->orWhereHas('transfers', fn ($t) => $t->where('source_hub_id', $actor->id)->orWhere('destination_hub_id', $actor->id))),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function report(Order $order, User $actor, array $data, ?string $evidence): ParcelIncident
    {
        abort_unless($actor->status === 'approved' && ($actor->role === 'sorting_center' || $actor->isLogisticsAdmin()), 403);
        validator($data, ['kind' => ['required', 'in:LOST,DAMAGED'], 'reason' => ['required', 'string', 'min:10', 'max:1000'], 'operation_key' => ['required', 'uuid']])->validate();

        return DB::transaction(function () use ($order, $actor, $data, $evidence) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->orders($actor)->whereKey($order->id)->exists(), 404);
            abort_unless(in_array($order->status, array_column(OrderStatus::cases(), 'value'), true) && ! in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'CANCELLED'], true), 422, 'Report an incident for a parcel already in the physical fulfillment workflow.');
            $hash = $evidence ? $this->digest($evidence) : null;
            abort_if($data['kind'] === 'DAMAGED' && ! $hash, 422, 'A private damage photo is mandatory.');
            if ($old = ParcelIncident::where('operation_key', $data['operation_key'])->first()) {
                abort_unless($old->order_id === $order->id && $old->reported_by === $actor->id && $old->kind === $data['kind'] && $old->reason === $data['reason'] && $old->evidence_sha256 === $hash, 409, 'This incident action has already recorded different details.');

                return $old;
            }
            self::assertClear($order);
            $transfer = $order->activeTransfer;
            $custodian = $transfer?->status === 'IN_TRANSIT' ? $transfer->carrier_rider_id : match ($order->status) {
                'PICKED_UP' => $order->pickup_courier_id,
                'OUT_FOR_DELIVERY', 'DELIVERY_FAILED' => $order->delivery_courier_id,
                'RETURN_IN_TRANSIT' => $order->return_handed_to_seller_at ? $order->seller_id : $order->delivery_courier_id,
                'ASSIGNED_TO_RIDER' => $order->hub_released_at ? $order->delivery_courier_id : $order->sorting_center_id,
                'DELIVERED', 'COMPLETED' => $order->buyer_id,
                'READY_FOR_PICKUP' => $order->seller_handover_at ? $order->pickup_courier_id : $order->seller_id,
                'RETURNED_TO_SELLER' => $order->seller_id,
                default => $order->sorting_center_id,
            };
            $incident = ParcelIncident::create(['order_id' => $order->id, 'open_order_id' => $order->id, 'reported_by' => $actor->id, 'custodian_id' => $custodian, 'operation_key' => $data['operation_key'], 'kind' => $data['kind'], 'reason' => $data['reason'], 'evidence_path' => $evidence, 'evidence_sha256' => $hash,
                'custody_snapshot' => ['status' => $order->status, 'custodian_id' => $custodian, 'sorting_center_id' => $order->sorting_center_id, 'pickup_courier_id' => $order->pickup_courier_id, 'delivery_courier_id' => $order->delivery_courier_id, 'transfer_id' => $transfer?->id, 'transfer_status' => $transfer?->status, 'weight_grams' => $order->parcel_weight_grams]]);
            DispatchOfferService::withdrawForOrder($order, 'Parcel incident reported; provisional offers withdrawn. Physical cargo reservations remain.');
            ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $actor->id, 'event_type' => 'parcel_incident_reported', 'status' => $order->status, 'notes' => $data['kind'].' incident #'.$incident->id.': '.$data['reason'].'. Custody and money unchanged; claim review started.']);
            $this->audits->record($actor, $incident, 'parcel_incident_reported', [], ['kind' => $incident->kind, 'status' => 'OPEN', 'custody' => $incident->custody_snapshot, 'claim_status' => $incident->claim_status], $incident->reason);
            $this->notify($incident, $incident->kind.' reported for '.$order->order_number.'. Fulfillment is on hold and a claim review has started. No refund has been issued.');

            return $incident;
        }, 3);
    }

    public function assessment(ParcelIncident $incident): string
    {
        $order = $incident->order;
        $finance = $order->finance;

        return match (true) {
            $order->payout?->status === 'SETTLED' => 'PAID_OUT_RECOVERY_REVIEW',
            $order->refundExecutions()->exists() => 'EXISTING_REFUND_REVIEW',
            $order->payment_method === 'COD' && ! $finance?->collected_cents => 'NO_COLLECTED_COD',
            $finance?->remittance_status === 'VERIFIED' && $finance?->ledger_funded_at !== null => 'VERIFIED_FUNDS_REVIEW',
            $order->payment_method === 'WALLET' && app(OrderWalletService::class)->paymentReady($order) => 'WALLET_FUNDS_REVIEW',
            $order->payment_method === 'COD' && $finance?->collected_cents > 0 => 'COLLECTED_COD_REVIEW',
            default => 'PAYMENT_RECONCILIATION',
        };
    }

    public function reviewClaim(ParcelIncident $incident, User $actor, int $version, string $reason): void
    {
        abort_unless($actor->status === 'approved' && $actor->isCommerceAdmin(), 403);
        validator(['reason' => $reason], ['reason' => ['required', 'string', 'min:10', 'max:1000']])->validate();
        DB::transaction(function () use ($incident, $actor, $version, $reason) {
            Order::whereKey($incident->order_id)->lockForUpdate()->firstOrFail();
            $locked = ParcelIncident::whereKey($incident->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->version === $version, 409, 'Claim review changed. Refresh before recording your assessment.');
            $assessment = $this->assessment($locked);
            $locked->update(['claim_status' => $assessment, 'claim_notes' => $reason, 'claim_reviewed_by' => $actor->id, 'claim_reviewed_at' => now(), 'version' => $version + 1]);
            $this->audits->record($actor, $locked, 'incident_claim_reviewed', [], ['claim_status' => $assessment], $reason.' No stock, payout, refund or ledger posting was executed.');
            $this->notify($locked, 'Claim review recorded for '.$locked->order->order_number.': '.str_replace('_', ' ', $assessment).'. No compensation or refund has been issued by this review.');
        }, 3);
    }

    public function clear(ParcelIncident $incident, User $actor, int $version, string $reference, string $reason, string $evidence): void
    {
        abort_unless($actor->status === 'approved' && $actor->isLogisticsAdmin(), 403);
        validator(['reason' => $reason], ['reason' => ['required', 'string', 'min:10', 'max:1000']])->validate();
        DB::transaction(function () use ($incident, $actor, $version, $reference, $reason, $evidence) {
            $order = Order::whereKey($incident->order_id)->lockForUpdate()->firstOrFail();
            $locked = ParcelIncident::whereKey($incident->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->reported_by !== $actor->id, 403, 'An independent logistics admin must reconcile this incident.');
            abort_unless($locked->status === 'OPEN' && (int) $locked->version === $version, 409, 'This incident has already changed.');
            abort_unless(hash_equals('EZP:'.$order->parcel_code, $reference), 422, 'Scan the current physical parcel label to reconcile custody.');
            abort_unless(! $locked->evidence_path || hash_equals($locked->evidence_sha256, $this->digest($locked->evidence_path)), 422, 'Original incident evidence changed. Investigate before clearing the hold.');
            $digest = $this->digest($evidence);
            $locked->update(['status' => 'RESOLVED', 'open_order_id' => null, 'resolved_by' => $actor->id, 'resolved_at' => now(), 'resolution_reason' => $reason, 'resolution_evidence_path' => $evidence, 'resolution_evidence_sha256' => $digest, 'version' => $version + 1]);
            ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $actor->id, 'event_type' => 'parcel_incident_reconciled', 'status' => $order->status, 'notes' => 'Incident #'.$locked->id.' custody reconciled using the physical label and private supporting photo. '.$reason]);
            $this->audits->record($actor, $locked, 'parcel_incident_reconciled', ['status' => 'OPEN'], ['status' => 'RESOLVED', 'custody_status' => $order->status], $reason.' Hold removed only; normal custody scans, refund and payout controls remain.');
            $this->notify($locked, 'Incident hold cleared after custody reconciliation for '.$order->order_number.'. Resume the recorded parcel workflow; no inventory or money was changed.');
        }, 3);
    }

    public function digest(string $path): string
    {
        abort_unless(Storage::disk('private')->exists($path), 422, 'Private supporting evidence is missing.');
        $digest = hash_file('sha256', Storage::disk('private')->path($path));
        abort_unless(is_string($digest), 422, 'Evidence could not be verified.');

        return $digest;
    }

    private function notify(ParcelIncident $incident, string $message): void
    {
        $order = $incident->order;
        $recipients = $order->messageParticipants();
        $admins = User::whereIn('role', ['logistics_admin', 'admin', 'ecommerce_admin'])->where('status', 'approved')->get();
        $this->notifications->send($recipients->merge($admins)->unique('id'), new WebOperationsNotification(['category' => 'orders', 'title' => $incident->kind.' parcel incident', 'message' => $message, 'order_id' => $order->id, 'url' => route('parcel-incidents.show', $incident)]));
    }
}
