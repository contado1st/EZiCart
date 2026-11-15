<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ParcelIncident;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiderWorkAvailabilityService
{
    public function __construct(private AdministrativeAuditService $audits, private TransactionAwareNotificationSender $notifications) {}

    public function enforced(): bool
    {
        return (bool) config('rider-work.enforce_availability', false);
    }

    public function availableRiders(Builder $query): Builder
    {
        return $query->where('role', 'courier')->where('status', 'approved')
            ->when($this->enforced(), fn ($q) => $q->where('work_availability', 'available'));
    }

    public function assertNewWork(User $rider): void
    {
        abort_unless($rider->role === 'courier' && $rider->status === 'approved', 403, 'This rider account is not approved for work.');
        if ($this->enforced() && $rider->work_availability !== 'available') {
            throw ValidationException::withMessages(['rider_availability' => 'This rider is not available for new work. Choose another rider or ask them to update their availability.']);
        }
    }

    public function choose(User $actor, string $state, int $version): array
    {
        abort_unless(in_array($state, ['available', 'unavailable'], true), 422);

        return DB::transaction(function () use ($actor, $state, $version): array {
            $rider = app(ParcelLoadService::class)->lockVehicle($actor->id);
            abort_unless($rider->role === 'courier' && $rider->status === 'approved', 403);
            $before = $rider->work_availability;
            if ($before !== $state) {
                if ((int) $rider->work_availability_version !== $version) {
                    throw ValidationException::withMessages(['version' => 'Availability changed in another tab. Refresh before choosing again.']);
                }
                $rider->forceFill(['work_availability' => $state, 'work_availability_changed_at' => now(),
                    'work_availability_version' => (int) $rider->work_availability_version + 1])->save();
            }
            $released = $state === 'unavailable' ? $this->withdrawUnacceptedOffers($rider, $rider) : ['pickups' => 0, 'deliveries' => 0, 'returns' => 0];
            if ($before !== $state) {
                $this->audits->record($rider, $rider, 'rider_work_availability_changed', ['work_availability' => $before],
                    ['work_availability' => $state, 'released_unaccepted_offers' => $released], 'Manual willingness for new work; account approval and accepted custody are unchanged.');
                $this->invalidateHubCounts($rider);
            }

            return $released;
        }, 3);
    }

    /** Never release accepted work or infer a physical handover. Null actor denotes scheduled reconciliation. */
    public function withdrawUnacceptedOffers(User $candidate, ?User $actor = null): array
    {
        return DB::transaction(function () use ($candidate, $actor): array {
            $rider = app(ParcelLoadService::class)->lockVehicle($candidate->id);
            $counts = ['pickups' => 0, 'deliveries' => 0, 'returns' => 0];
            if (! $this->enforced() || $rider->role !== 'courier' || $rider->status !== 'approved' || $rider->work_availability === 'available') {
                return $counts;
            }
            abort_unless($actor === null || $actor->id === $rider->id, 403);
            $ids = Order::query()->where(fn ($q) => $q
                ->where(fn ($p) => $p->where('pickup_courier_id', $rider->id)->where('status', 'READY_FOR_PICKUP')->whereNull('pickup_claimed_at')->whereNull('pickup_arrived_at')->whereNull('seller_handover_at'))
                ->orWhere(fn ($d) => $d->where('delivery_courier_id', $rider->id)->where('status', 'ASSIGNED_TO_RIDER')->whereNull('hub_released_at')
                    ->whereHas('deliveryAssignments', fn ($a) => $a->where('rider_id', $rider->id)->where('status', 'active')->whereNull('accepted_at')))
                ->orWhere(fn ($r) => $r->whereIn('status', ['DELIVERED', 'COMPLETED'])
                    ->whereHas('returnPickupAssignment', fn ($a) => $a->where('rider_id', $rider->id)->whereNull('accepted_at'))
                    ->whereDoesntHave('trackingEvents', fn ($e) => $e->whereIn('event_type', ['return_buyer_handover', 'return_pickup_collected']))))->orderBy('id')->pluck('id');
            foreach ($ids as $id) {
                $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
                if (ParcelIncident::where('open_order_id', $order->id)->lockForUpdate()->first()) {
                    continue;
                }
                if ($order->status === 'READY_FOR_PICKUP' && (int) $order->pickup_courier_id === $rider->id
                    && $order->pickup_claimed_at === null && $order->pickup_arrived_at === null && $order->seller_handover_at === null) {
                    $order->forceFill(['pickup_courier_id' => null])->save();
                    $this->recordRelease($order, $rider, $actor, 'pickup_offer_withdrawn_for_availability');
                    app(LogisticsHubNotificationService::class)->sendForOrder($order, $this->notifications,
                        new OrderWorkflowNotification($order, 'pickup_offer_withdrawn_for_availability', 'Rider unavailable for new work. Reassign the unaccepted pickup; seller custody is unchanged.'));
                    $counts['pickups']++;
                }
                if ($order->status === 'ASSIGNED_TO_RIDER' && app(OrderTransitionService::class)->withdrawUnavailableDeliveryOffer($order, $rider, $actor)) {
                    $counts['deliveries']++;
                }
                $return = $order->returnPickupAssignment()->where('rider_id', $rider->id)->whereNull('accepted_at')->lockForUpdate()->first();
                if ($return && in_array($order->status, ['DELIVERED', 'COMPLETED'], true)
                    && ! $order->trackingEvents()->whereIn('event_type', ['return_buyer_handover', 'return_pickup_collected'])->exists()) {
                    $return->update(['status' => 'reassigned', 'active_order_id' => null, 'released_at' => now()]);
                    $this->recordRelease($order, $rider, $actor, 'return_pickup_offer_withdrawn_for_availability');
                    if ($order->sortingCenter) {
                        $this->notifications->send($order->sortingCenter, new OrderWorkflowNotification($order, 'return_pickup_offer_withdrawn_for_availability', 'Reassign the unaccepted buyer return pickup. No handover or collection was recorded.'));
                    }
                    $counts['returns']++;
                }
            }
            if (array_sum($counts) > 0) {
                $this->invalidateHubCounts($rider);
            }

            return $counts;
        }, 3);
    }

    public function readiness(): array
    {
        $approved = User::where('role', 'courier')->where('status', 'approved');
        $unknown = (clone $approved)->where(fn ($q) => $q->whereNull('work_availability')->orWhereNotIn('work_availability', ['available', 'unavailable']))->count();
        $ambiguous = Order::where('status', 'ASSIGNED_TO_RIDER')->whereNotNull('delivery_courier_id')->whereNull('hub_released_at')
            ->whereDoesntHave('deliveryAssignments', fn ($q) => $q->where('status', 'active')->whereColumn('rider_id', 'orders.delivery_courier_id'))->count();
        $unsafePickup = Order::where('status', 'READY_FOR_PICKUP')->whereNotNull('pickup_courier_id')->whereNull('pickup_claimed_at')
            ->where(fn ($q) => $q->whereNotNull('pickup_arrived_at')->orWhereNotNull('seller_handover_at'))->count();
        $unsafeReturn = Order::whereHas('returnPickupAssignment', fn ($a) => $a->whereNull('accepted_at'))
            ->where(fn ($q) => $q->whereNotIn('status', ['DELIVERED', 'COMPLETED'])
                ->orWhereHas('trackingEvents', fn ($e) => $e->whereIn('event_type', ['return_buyer_handover', 'return_pickup_collected'])))->count();

        return ['enforced' => $this->enforced(), 'approved_riders' => (clone $approved)->count(),
            'available' => (clone $approved)->where('work_availability', 'available')->count(),
            'unavailable' => (clone $approved)->where('work_availability', 'unavailable')->count(), 'not_chosen' => $unknown,
            'assignments_without_acceptance_record' => $ambiguous, 'unclaimed_pickups_with_custody_evidence' => $unsafePickup,
            'unaccepted_returns_with_custody_evidence' => $unsafeReturn,
            'ready_for_activation' => $unknown === 0 && $ambiguous === 0 && $unsafePickup === 0 && $unsafeReturn === 0];
    }

    private function invalidateHubCounts(User $rider): void
    {
        $ids = $rider->serviceAreas()->pluck('areas.sorting_center_id')->push($rider->logistics_owner_id)->filter()->unique();
        $hubs = User::where('role', 'sorting_center')->where('status', 'approved')->limit(2)->pluck('id');
        if ($hubs->count() === 1) {
            $ids->push($hubs->first());
        }
        $date = today()->toDateString();
        DB::afterCommit(function () use ($ids, $date): void {
            foreach ($ids->unique() as $id) {
                Cache::forget('logistics.dashboard.stats.'.$id.'.'.$date);
            }
        });
    }

    private function recordRelease(Order $order, User $rider, ?User $actor, string $event): void
    {
        ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $actor?->id, 'event_type' => $event,
            'status' => $order->status, 'notes' => 'Unaccepted offer withdrawn because rider #'.$rider->id.' is not available for new work. Physical custody is unchanged.']);
        $this->audits->record($actor, $order, $event, ['offered_rider_id' => $rider->id], ['offered_rider_id' => null], 'Unaccepted work returned to logistics; no physical handover recorded.');
        $this->notifications->send(collect([$order->buyer, $order->seller])->filter()->unique('id'), new OrderWorkflowNotification($order, $event,
            'The rider is unavailable for new work. Logistics will reassign the unaccepted offer; parcel custody is unchanged.'));
    }
}
