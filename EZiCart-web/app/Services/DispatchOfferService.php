<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DispatchCycle;
use App\Models\DispatchOffer;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DispatchOfferService
{
    public function __construct(private ParcelLoadService $loads, private DispatchLocationService $locations, private AdministrativeAuditService $audits, private TransactionAwareNotificationSender $notifications) {}

    public static function assertNoOffer(Order $order): void
    {
        abort_if(DispatchCycle::where('active_order_id', $order->id)->lockForUpdate()->first() !== null, 422, 'Cancel the live automatic offer cycle before making a manual assignment.');
    }

    /** Caller may hold the order lock; this only releases provisional offers, never physical cargo. */
    public static function withdrawForOrder(Order $order, string $reason): void
    {
        $cycles = DispatchCycle::where('active_order_id', $order->id)->lockForUpdate()->get();
        foreach ($cycles as $cycle) {
            DispatchOffer::where('dispatch_cycle_id', $cycle->id)->where('status', 'PENDING')->update(['status' => 'WITHDRAWN', 'active_cycle_id' => null, 'responded_at' => now(), 'reason' => $reason, 'updated_at' => now()]);
            $cycle->update(['status' => 'CANCELLED', 'active_order_id' => null, 'outcome_reason' => $reason]);
        }
    }

    public function start(Order $order, User $hub, string $key): DispatchCycle
    {
        abort_unless($hub->role === 'sorting_center' && $hub->status === 'approved', 403);
        validator(['key' => $key], ['key' => ['required', 'uuid']])->validate();
        abort_unless(app(HubOrderScope::class)->query($hub)->whereKey($order->id)->exists(), 404);

        return DB::transaction(function () use ($order, $hub, $key) {
            $fleet = $this->lockFleet($hub->id);
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($old = DispatchCycle::where('operation_key', $key)->first()) {
                abort_unless($old->order_id === $locked->id && $old->hub_id === $hub->id, 409);

                return $old;
            }
            self::assertNoOffer($locked);
            abort_unless(app(HubOrderScope::class)->query($hub)->whereKey($locked->id)->exists(), 404);
            abort_unless(in_array($locked->status, ['READY_FOR_PICKUP', 'SORTED'], true), 422, 'Automatic dispatch starts from an unassigned pickup or first-attempt Sorted parcel.');
            $cycle = DispatchCycle::create(['order_id' => $locked->id, 'active_order_id' => $locked->id, 'hub_id' => $hub->id, 'operation_key' => $key, 'kind' => $locked->status === 'READY_FOR_PICKUP' ? 'pickup' : 'delivery', 'status' => 'OFFERING']);
            $this->next($cycle, $locked, $fleet);
            $this->audits->record($hub, $cycle, 'automatic_dispatch_started', [], ['order_id' => $locked->id, 'status' => $cycle->status], 'Explicit dispatch cycle; at most three distinct 60-second offers. No physical custody changed.');

            return $cycle->refresh();
        }, 3);
    }

    public function respond(DispatchOffer $offer, User $rider, bool $accept, ?string $reason = null): string
    {
        abort_unless($rider->role === 'courier' && $rider->status === 'approved' && $offer->rider_id === $rider->id, 404);

        return $this->operate($offer->cycle, function ($cycle, $order, $fleet) use ($offer, $rider, $accept, $reason) {
            $locked = DispatchOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'PENDING') {
                abort_unless(($accept && $locked->status === 'ACCEPTED') || (! $accept && $locked->status === 'DECLINED'), 409, 'This offer is no longer pending.');

                return $locked->status;
            }
            if ($cycle->status !== 'OFFERING') {
                return 'WITHDRAWN';
            }
            if ($locked->expires_at->lte(now())) {
                $this->finishOffer($locked, 'EXPIRED', 'The 60-second offer expired.');
                $this->next($cycle, $order, $fleet);

                return 'EXPIRED';
            }
            if (! $accept) {
                $this->finishOffer($locked, 'DECLINED', $reason ?? 'Rider declined the automatic offer.');
                $this->next($cycle, $order, $fleet);

                return 'DECLINED';
            }
            try {
                $this->validJob($cycle, $order);
                $this->eligible($cycle, $order, $fleet->firstWhere('id', $rider->id));
            } catch (ValidationException|HttpExceptionInterface $error) {
                $this->finishOffer($locked, 'EXPIRED', 'Eligibility changed before acceptance.');
                $this->next($cycle, $order, $fleet);

                return 'EXPIRED';
            }
            $this->finishOffer($locked, 'ACCEPTED', 'Rider accepted before expiry; awaiting normal pickup/hub custody scans.');
            $cycle->update(['status' => 'ACCEPTED', 'active_order_id' => null, 'outcome_reason' => 'Rider #'.$rider->id.' accepted. Physical custody remains unchanged.']);
            if ($cycle->kind === 'pickup') {
                $order->forceFill(['pickup_courier_id' => $rider->id, 'pickup_claimed_at' => now()])->save();
            } else {
                app(OrderTransitionService::class)->transition($order, $cycle->hub, OrderStatus::AssignedToRider, 'automatic_rider_assigned', notes: 'Hub-authorized offer accepted by rider #'.$rider->id.'.', attributes: ['delivery_courier_id' => $rider->id, 'assigned_at' => now(), 'hub_released_at' => null]);
                $order->deliveryAssignments()->where('status', 'active')->where('rider_id', $rider->id)->update(['accepted_at' => now()]);
            }
            ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $rider->id, 'event_type' => 'automatic_offer_accepted', 'status' => $order->fresh()->status, 'notes' => 'Offer #'.$locked->id.' accepted. Continue the existing physical custody process.']);
            $this->audits->record($rider, $locked, 'automatic_offer_accepted', ['status' => 'PENDING'], ['status' => 'ACCEPTED'], 'Rider accepted; provisional capacity becomes the normal assigned load once.');
            $this->notify([$order->buyer, $order->seller, $cycle->hub], 'Rider accepted for '.$order->order_number.'. Physical arrival/handover and parcel scans are still required.');

            return 'ACCEPTED';
        });
    }

    public function process(DispatchCycle $cycle): void
    {
        $this->operate($cycle, function ($cycle, $order, $fleet) {
            if ($cycle->status !== 'OFFERING') {
                return;
            }
            $offer = $cycle->offers()->where('status', 'PENDING')->lockForUpdate()->first();
            if ($offer && $offer->expires_at->isFuture() && User::whereKey($offer->rider_id)->where('status', 'approved')->where('work_availability', 'available')->exists() && ! $order->pendingCancellation()->exists() && ! $order->activeIncident) {
                return;
            }
            if ($offer) {
                $this->finishOffer($offer, 'EXPIRED', 'Offer expired or the rider/job became unavailable.');
            }
            $this->next($cycle, $order, $fleet);
        });
    }

    public function cancel(DispatchCycle $cycle, User $actor, string $reason): void
    {
        abort_unless($actor->status === 'approved' && ($actor->isLogisticsAdmin() || ($actor->role === 'sorting_center' && $cycle->hub_id === $actor->id)), 404);
        validator(['reason' => $reason], ['reason' => ['required', 'string', 'min:5', 'max:500']])->validate();
        $this->operate($cycle, function ($cycle, $order) use ($actor, $reason) {
            if ($cycle->status !== 'OFFERING') {
                return;
            }
            self::withdrawForOrder($order, $reason);
            $this->audits->record($actor, $cycle, 'automatic_dispatch_cancelled', [], ['status' => 'CANCELLED'], $reason);
        });
    }

    /** Lock vehicles before order/cycle, matching manual assignment and transfer lock order. */
    private function operate(DispatchCycle $cycle, callable $action): mixed
    {
        return DB::transaction(function () use ($cycle, $action) {
            $extra = $cycle->offers()->where('status', 'PENDING')->pluck('rider_id')->all();
            $fleet = $this->lockFleet($cycle->hub_id, $extra);
            $order = Order::whereKey($cycle->order_id)->lockForUpdate()->firstOrFail();
            $cycle = DispatchCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();

            return $action($cycle, $order, $fleet);
        }, 3);
    }

    private function lockFleet(int $hubId, array $extra = [])
    {
        User::whereKey($hubId)->lockForUpdate()->firstOrFail();

        return User::where(fn ($q) => $q->where(fn ($q) => $q->where('role', 'courier')->where(fn ($q) => $q->where('logistics_owner_id', $hubId)->orWhereHas('serviceAreas', fn ($a) => $a->where('sorting_center_id', $hubId))))->orWhereIn('id', $extra))->orderBy('id')->lockForUpdate()->get();
    }

    private function validJob(DispatchCycle $cycle, Order $order): void
    {
        $cycle->setRelation('hub', User::whereKey($cycle->hub_id)->lockForUpdate()->firstOrFail());
        abort_unless($cycle->hub->status === 'approved' && app(HubOrderScope::class)->query($cycle->hub)->whereKey($order->id)->exists(), 422, 'Hub ownership/availability changed.');
        ParcelIncidentService::assertClear($order);
        abort_if($order->pendingCancellation()->exists() || $order->activeTransfer()->exists() || ! app(OrderWalletService::class)->paymentReady($order), 422, 'Payment, cancellation or transfer hold requires manual review.');
        $this->loads->requireWeight($order);
        if ($cycle->kind === 'pickup') {
            abort_unless($order->status === 'READY_FOR_PICKUP' && $order->pickup_requested_at && ! $order->pickup_courier_id && ! $order->pickup_claimed_at && ! $order->seller_handover_at && ! $order->picked_up_at, 422, 'Pickup is already assigned or no longer ready.');
        } else {
            abort_unless($order->status === 'SORTED' && (int) $order->sorting_center_id === (int) $cycle->hub_id && ! $order->delivery_courier_id && ! $order->deliveryAttempts()->where('outcome', 'failed')->exists(), 422, 'Use manual recovery/scheduling for an assigned or retry parcel.');
        }
        $origin = $cycle->kind === 'pickup' ? $order->seller()->lockForUpdate()->firstOrFail() : $cycle->hub;
        $point = $this->locations->origin($origin);
        abort_unless($point && ($cycle->pickup_address_hash === null || hash_equals($cycle->pickup_address_hash, $point->address_hash)), 422, 'A current hub-verified pickup pin is missing or the origin address changed. Use manual dispatch or verify the pin.');
        abort_unless($cycle->pickup_address_hash === null || ((float) $cycle->pickup_latitude === $point->latitude && (float) $cycle->pickup_longitude === $point->longitude), 422, 'The verified pickup pin changed. Start a reviewed new cycle.');
        if ($cycle->pickup_address_hash === null) {
            $cycle->update(['pickup_latitude' => $point->latitude, 'pickup_longitude' => $point->longitude, 'pickup_address_hash' => $point->address_hash]);
        }
    }

    private function eligible(DispatchCycle $cycle, Order $order, User $rider): int
    {
        abort_unless($rider->role === 'courier' && $rider->status === 'approved' && $rider->work_availability === 'available' && ! $rider->hub_transfer_only, 422);
        abort_unless((int) $rider->logistics_owner_id === (int) $cycle->hub_id || $rider->serviceAreas()->where('sorting_center_id', $cycle->hub_id)->exists(), 422);
        if ($cycle->kind === 'delivery') {
            abort_unless($rider->serviceAreas()->whereKey($order->destination_area_id)->where('areas.is_active', true)->exists(), 422);
            app(LogisticsCoverageService::class)->assertRider($order, $rider, null);
        } else {
            $seller = $order->seller;
            abort_unless($rider->serviceAreas()->where('sorting_center_id', $cycle->hub_id)->where('areas.is_active', true)->whereHas('municipalities', fn ($q) => $q->where('province_normalized', mb_strtolower(trim($seller->province)))->where('municipality_normalized', mb_strtolower(trim($seller->municipality))))->exists(), 422);
        }
        $point = $this->locations->fresh($rider);
        abort_unless($point, 422);
        $active = $this->loads->activeLoads($rider->id)->whereKeyNot($order->id)->count();
        $offered = DispatchOffer::where('rider_id', $rider->id)->where('order_id', '!=', $order->id)->where('status', 'PENDING')->where('expires_at', '>', now())->count();
        abort_unless($active + $offered < (int) config('dispatch.maximum_active_jobs'), 422);
        $this->loads->assertFits($order, $rider, newWork: true);
        $lat = deg2rad($point->latitude - (float) $cycle->pickup_latitude);
        $lon = deg2rad($point->longitude - (float) $cycle->pickup_longitude);
        $a = sin($lat / 2) ** 2 + cos(deg2rad($point->latitude)) * cos(deg2rad((float) $cycle->pickup_latitude)) * sin($lon / 2) ** 2;

        return (int) round(6371000 * 2 * asin(min(1, sqrt($a))));
    }

    private function next(DispatchCycle $cycle, Order $order, $fleet): void
    {
        try {
            $this->validJob($cycle, $order);
        } catch (ValidationException|HttpExceptionInterface $error) {
            $this->unassigned($cycle, $error->getMessage());

            return;
        }
        $tried = $cycle->offers()->pluck('rider_id')->all();
        if (count($tried) >= (int) config('dispatch.maximum_candidates')) {
            $this->unassigned($cycle, 'Three distinct riders declined or expired. Manual dispatch or an explicit new cycle is required.');

            return;
        }
        $candidates = [];
        foreach ($fleet as $rider) {
            if (in_array($rider->id, $tried, true)) {
                continue;
            }
            try {
                $candidates[] = ['rider' => $rider, 'distance' => $this->eligible($cycle, $order, $rider)];
            } catch (ValidationException|HttpExceptionInterface) {
                continue;
            }
        }
        usort($candidates, fn ($a, $b) => [$a['distance'], $a['rider']->id] <=> [$b['distance'], $b['rider']->id]);
        if (! $candidates) {
            $this->unassigned($cycle, 'No fresh, available, covered rider below three jobs with verified spare capacity. Manual dispatch remains available.');

            return;
        }
        $chosen = $candidates[0];
        $offer = DispatchOffer::create(['dispatch_cycle_id' => $cycle->id, 'active_cycle_id' => $cycle->id, 'order_id' => $order->id, 'rider_id' => $chosen['rider']->id, 'status' => 'PENDING', 'weight_grams' => $order->parcel_weight_grams, 'distance_meters' => $chosen['distance'], 'expires_at' => now()->addSeconds((int) config('dispatch.offer_seconds'))]);
        $this->notify([$chosen['rider']], '60-second '.$cycle->kind.' offer for '.$order->order_number.'. Open Automatic dispatch to accept or decline.');
        $this->audits->record(null, $offer, 'automatic_offer_created', [], ['rider_id' => $offer->rider_id, 'distance_meters' => $offer->distance_meters, 'weight_grams' => $offer->weight_grams, 'expires_at' => $offer->expires_at], 'Fresh device-reported location to confirmed pickup pin; straight-line distance with stable rider ID tie-break.');
    }

    private function finishOffer(DispatchOffer $offer, string $status, string $reason): void
    {
        $offer->update(['status' => $status, 'active_cycle_id' => null, 'responded_at' => now(), 'reason' => $reason]);
        $this->audits->record(null, $offer, 'automatic_offer_'.strtolower($status), ['status' => 'PENDING'], ['status' => $status], $reason);
    }

    private function unassigned(DispatchCycle $cycle, string $reason): void
    {
        $cycle->update(['status' => 'UNASSIGNED', 'active_order_id' => null, 'outcome_reason' => mb_substr($reason, 0, 500)]);
        $this->notify([$cycle->hub], $cycle->order->order_number.' is Unassigned. '.$reason);
    }

    private function notify(iterable $users, string $message): void
    {
        $this->notifications->send($users, new WebOperationsNotification(['category' => 'orders', 'message' => $message, 'url' => route('dispatch.index')]));
    }
}
