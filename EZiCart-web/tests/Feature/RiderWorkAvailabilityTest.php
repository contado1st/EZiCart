<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DeliveryAssignment;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use App\Services\ParcelTransferService;
use App\Services\RiderBadgeService;
use App\Services\RiderWorkAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RiderWorkAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['rider-work.enforce_availability' => true]);
        $this->freezeTime();
    }

    public function test_preparation_mode_collects_own_choice_without_withdrawing_offers_or_blocking_legacy_work(): void
    {
        Notification::fake();
        config(['rider-work.enforce_availability' => false]);
        [$hub, $area, $rider] = $this->crew(['work_availability' => 'unknown']);
        $offer = $this->order($hub, $area, ['pickup_courier_id' => $rider->id, 'status' => 'READY_FOR_PICKUP']);
        $other = $this->user('courier');
        $this->signIn($rider)->get(route('courier.work-availability.index'))->assertOk()->assertSee('Preparation mode');
        $this->patch(route('courier.work-availability.update'), ['state' => 'unavailable', 'version' => 0, 'user_id' => $other->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('unavailable', $rider->fresh()->work_availability);
        $this->assertSame('available', $other->fresh()->work_availability);
        $this->assertSame($rider->id, $offer->fresh()->pickup_courier_id);
        $this->assertTrue(User::availableForNewWork()->whereKey($rider->id)->exists());
        $this->assertArrayNotHasKey('work_availability', $rider->fresh()->toArray());
        Notification::assertNothingSent();
    }

    public function test_changed_choice_has_a_version_and_duplicate_posts_do_not_add_audits_or_overwrite_newer_choices(): void
    {
        $rider = $this->user('courier', ['work_availability' => 'unknown']);
        $this->signIn($rider)->patch(route('courier.work-availability.update'), ['state' => 'available', 'version' => 0])->assertSessionHasNoErrors();
        $this->patch(route('courier.work-availability.update'), ['state' => 'available', 'version' => 0])->assertSessionHasNoErrors();
        $this->assertSame(1, $rider->fresh()->work_availability_version);
        $this->assertDatabaseCount('administrative_audits', 1);
        $this->patch(route('courier.work-availability.update'), ['state' => 'unavailable', 'version' => 0])->assertSessionHasErrors('version');
        $this->assertSame('available', $rider->fresh()->work_availability);
        $this->patch(route('courier.work-availability.update'), ['state' => 'unknown', 'version' => 1])->assertSessionHasErrors('state');
    }

    public function test_unavailable_choice_releases_only_unaccepted_pickup_delivery_and_return_offers_once(): void
    {
        Notification::fake();
        [$hub, $area, $rider] = $this->crew();
        $pickup = $this->order($hub, $area, ['status' => 'READY_FOR_PICKUP', 'pickup_courier_id' => $rider->id]);
        $delivery = $this->order($hub, $area, ['status' => 'ASSIGNED_TO_RIDER', 'delivery_courier_id' => $rider->id]);
        $deliveryOffer = $this->assignment($delivery, $rider, $hub);
        $return = $this->order($hub, $area, ['status' => 'COMPLETED']);
        $returnOffer = $this->assignment($return, $rider, $hub, false, 'return_pending');
        $acceptedPickup = $this->order($hub, $area, ['status' => 'READY_FOR_PICKUP', 'pickup_courier_id' => $rider->id, 'pickup_claimed_at' => now()]);
        $acceptedDelivery = $this->order($hub, $area, ['status' => 'ASSIGNED_TO_RIDER', 'delivery_courier_id' => $rider->id]);
        $acceptedOffer = $this->assignment($acceptedDelivery, $rider, $hub, true);
        $key = 'logistics.dashboard.stats.'.$hub->id.'.'.today()->toDateString();
        Cache::put($key, ['available_riders' => 1], 60);
        $this->signIn($rider)->patch(route('courier.work-availability.update'), ['state' => 'unavailable', 'version' => 0])->assertSessionHasNoErrors();
        $this->assertNull($pickup->fresh()->pickup_courier_id);
        $this->assertSame('SORTED', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->delivery_courier_id);
        $this->assertSame('reassigned', $deliveryOffer->fresh()->status);
        $this->assertNull($returnOffer->fresh()->active_order_id);
        $this->assertSame('COMPLETED', $return->fresh()->status);
        $this->assertSame($rider->id, $acceptedPickup->fresh()->pickup_courier_id);
        $this->assertSame($rider->id, $acceptedDelivery->fresh()->delivery_courier_id);
        $this->assertNotNull($acceptedOffer->fresh()->accepted_at);
        $this->assertDatabaseCount('parcel_tracking_events', 3);
        $this->patch(route('courier.work-availability.update'), ['state' => 'unavailable', 'version' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('parcel_tracking_events', 3);
        $this->assertFalse(Cache::has($key));
        Notification::assertSentTo($pickup->buyer, OrderWorkflowNotification::class);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_unknown_and_unavailable_are_excluded_from_new_work_and_pickup_posts_cannot_bypass_the_rule(): void
    {
        Notification::fake();
        [$hub, $area, $rider] = $this->crew(['work_availability' => 'unavailable']);
        $unknown = $this->user('courier', ['work_availability' => 'unknown']);
        $suspended = $this->user('courier', ['status' => 'suspended']);
        $this->assertFalse(User::availableForNewWork()->whereKey($rider->id)->exists());
        $this->assertFalse(User::availableForNewWork()->whereKey($unknown->id)->exists());
        $this->assertFalse(User::availableForNewWork()->whereKey($suspended->id)->exists());
        $order = $this->order($hub, $area, ['status' => 'READY_FOR_PICKUP', 'pickup_requested_at' => now()]);
        $this->signIn($hub)->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $rider->id])->assertSessionHasErrors('rider_availability');
        $this->assertNull($order->fresh()->pickup_courier_id);
        $rider->forceFill(['work_availability' => 'available'])->save();
        $this->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $rider->id])->assertSessionHasNoErrors();
        $rider->forceFill(['work_availability' => 'unavailable'])->save();
        $this->signIn($rider)->post(route('courier.orders.claim', $order))->assertSessionHasErrors('rider_availability');
        $this->assertNull($order->fresh()->pickup_claimed_at);
    }

    public function test_accepted_delivery_can_continue_while_unavailable_but_new_acceptance_and_auto_accept_handoff_cannot(): void
    {
        Notification::fake();
        [$hub, $area, $rider] = $this->crew(['work_availability' => 'unavailable']);
        $order = $this->order($hub, $area, ['status' => 'ASSIGNED_TO_RIDER', 'delivery_courier_id' => $rider->id]);
        $assignment = $this->assignment($order, $rider, $hub);
        $this->signIn($rider)->post(route('courier.orders.accept-assignment', $order))->assertSessionHasErrors('rider_availability');
        $badge = app(RiderBadgeService::class)->ensure($rider);
        $scan = ['rider_badge' => 'EZR:'.$badge, 'parcel_reference' => 'EZP:'.$order->parcel_code, 'method' => 'manual'];
        $this->signIn($hub)->post(route('logistics.orders.releaseToRider', $order), $scan)->assertSessionHasErrors('rider_availability');
        $this->assertNull($assignment->fresh()->accepted_at);
        $assignment->update(['accepted_at' => now()]);
        $this->post(route('logistics.orders.releaseToRider', $order), $scan)->assertSessionHasNoErrors();
        $this->assertNotNull($order->fresh()->hub_released_at);
        $this->signIn($rider)->post(route('courier.orders.accept-assignment', $order))->assertUnprocessable();
    }

    public function test_last_mile_assignment_rechecks_availability_on_the_server(): void
    {
        Notification::fake();
        [$hub, $area, $rider] = $this->crew(['work_availability' => 'unavailable']);
        $order = $this->order($hub, $area);
        $this->signIn($hub)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $rider->id])->assertSessionHasErrors('rider_availability');
        $this->assertSame('SORTED', $order->fresh()->status);
        $this->assertDatabaseCount('delivery_assignments', 0);
        $rider->forceFill(['work_availability' => 'available'])->save();
        $this->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $rider->id])->assertSessionHasNoErrors();
        $this->assertSame('ASSIGNED_TO_RIDER', $order->fresh()->status);
        $this->assertSame($rider->id, $order->fresh()->delivery_courier_id);
        $this->assertDatabaseCount('delivery_assignments', 1);
    }

    public function test_unavailable_rider_can_finish_accepted_pickup_but_cannot_skip_seller_handover_or_scan(): void
    {
        Notification::fake();
        [$hub, $area, $rider] = $this->crew(['work_availability' => 'unavailable']);
        $order = $this->order($hub, $area, ['status' => 'READY_FOR_PICKUP', 'pickup_courier_id' => $rider->id, 'pickup_claimed_at' => now(), 'pickup_arrived_at' => now()]);
        $reference = ['parcel_reference' => 'EZP:'.$order->parcel_code, 'method' => 'manual'];
        $this->signIn($rider)->post(route('courier.orders.confirmPickup', $order), $reference)->assertUnprocessable();
        $this->assertSame('READY_FOR_PICKUP', $order->fresh()->status);
        $order->forceFill(['seller_handover_at' => now()])->save();
        $this->post(route('courier.orders.confirmPickup', $order))->assertUnprocessable();
        $this->post(route('courier.orders.confirmPickup', $order), $reference)->assertSessionHasNoErrors();
        $this->assertSame('PICKED_UP', $order->fresh()->status);
        $this->assertSame('unavailable', $rider->fresh()->work_availability);
        $this->assertDatabaseHas('scan_events', ['order_id' => $order->id, 'station' => 'seller_pickup', 'result' => 'accepted']);
    }

    public function test_available_is_not_a_bypass_for_capacity_or_expired_license(): void
    {
        [$hub, $area, $rider] = $this->crew(['vehicle_capacity_verified_at' => null]);
        $order = $this->order($hub, $area, ['status' => 'READY_FOR_PICKUP', 'pickup_requested_at' => now()]);
        $this->signIn($hub)->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $rider->id])->assertSessionHasErrors('vehicle_capacity_kg');
        $rider->forceFill(['vehicle_capacity_verified_at' => now(), 'license_expires_at' => today()->subDay()])->save();
        $this->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $rider->id])->assertSessionHasErrors('license_expires_at');
        $this->assertNull($order->fresh()->pickup_courier_id);
    }

    public function test_return_offers_obey_availability_but_accepted_return_collection_still_requires_real_handover(): void
    {
        Notification::fake();
        [$hub, $area, $rider] = $this->crew(['work_availability' => 'unavailable']);
        $order = $this->order($hub, $area, ['status' => 'COMPLETED']);
        Dispute::create(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id, 'reason' => 'Return', 'description' => 'Approved synthetic return', 'status' => 'REFUND_APPROVED']);
        $this->signIn($hub)->post(route('return-pickups.assign', $order), ['rider_id' => $rider->id])->assertSessionHasErrors('rider_availability');
        $rider->forceFill(['work_availability' => 'available'])->save();
        $this->post(route('return-pickups.assign', $order), ['rider_id' => $rider->id])->assertSessionHasNoErrors();
        $this->signIn($rider)->post(route('return-pickups.accept', $order))->assertSessionHasNoErrors();
        $this->patch(route('courier.work-availability.update'), ['state' => 'unavailable', 'version' => 0])->assertSessionHasNoErrors();
        $this->post(route('return-pickups.collect', $order), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertUnprocessable();
        $this->signIn($order->buyer)->post(route('return-pickups.handover', $order))->assertSessionHasNoErrors();
        $this->signIn($rider)->post(route('return-pickups.collect', $order), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertSessionHasNoErrors();
        $this->assertSame('RETURN_IN_TRANSIT', $order->fresh()->status);
    }

    public function test_hub_driver_must_be_available_for_new_release_but_becoming_unavailable_keeps_in_transit_custody(): void
    {
        Notification::fake();
        [$source, , $driver] = $this->crew(['hub_transfer_only' => true, 'work_availability' => 'unavailable']);
        $destination = $this->user('sorting_center');
        $area = Area::create(['name' => 'Destination area', 'code' => 'DEST-'.Str::random(5), 'is_active' => true, 'sorting_center_id' => $destination->id]);
        $order = $this->order($source, $area, ['status' => 'AT_SORTING_CENTER']);
        $transfers = app(ParcelTransferService::class);
        $transfer = $transfers->plan($order, $this->user('logistics_admin'), $destination, 'Synthetic hub transport plan.');
        try {
            $transfers->release($transfer, $source, 'EZP:'.$order->parcel_code, 'MANIFEST-TEST', $driver);
            $this->fail('Unavailable driver must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rider_availability', $exception->errors());
        }
        $driver->forceFill(['work_availability' => 'available'])->save();
        $transfers->release($transfer, $source, 'EZP:'.$order->parcel_code, 'MANIFEST-TEST', $driver);
        $this->signIn($driver)->get(route('courier.work-availability.index'))->assertOk();
        $this->patch(route('courier.work-availability.update'), ['state' => 'unavailable', 'version' => 0])->assertSessionHasNoErrors();
        $this->assertSame('IN_TRANSIT', $transfer->fresh()->status);
        $this->assertSame($driver->id, $transfer->fresh()->carrier_rider_id);
    }

    public function test_legacy_or_physical_evidence_is_reported_and_never_silently_cleared(): void
    {
        Notification::fake();
        [$hub, $area, $rider] = $this->crew();
        $legacy = $this->order($hub, $area, ['status' => 'ASSIGNED_TO_RIDER', 'delivery_courier_id' => $rider->id]);
        $pickup = $this->order($hub, $area, ['status' => 'READY_FOR_PICKUP', 'pickup_courier_id' => $rider->id, 'pickup_arrived_at' => now()]);
        $return = $this->order($hub, $area, ['status' => 'COMPLETED']);
        $offer = $this->assignment($return, $rider, $hub, false, 'return_pending');
        ParcelTrackingEvent::create(['order_id' => $return->id, 'actor_id' => $return->buyer_id, 'event_type' => 'return_buyer_handover', 'status' => 'COMPLETED', 'notes' => 'Legacy physical handover evidence']);
        $this->signIn($rider)->patch(route('courier.work-availability.update'), ['state' => 'unavailable', 'version' => 0])->assertSessionHasNoErrors();
        $this->assertSame($rider->id, $legacy->fresh()->delivery_courier_id);
        $this->assertSame($rider->id, $pickup->fresh()->pickup_courier_id);
        $this->assertNotNull($offer->fresh()->active_order_id);
        $report = app(RiderWorkAvailabilityService::class)->readiness();
        $this->assertSame(1, $report['assignments_without_acceptance_record']);
        $this->assertSame(1, $report['unclaimed_pickups_with_custody_evidence']);
        $this->assertSame(1, $report['unaccepted_returns_with_custody_evidence']);
        $this->artisan('riders:availability-audit', ['--check' => true, '--json' => true])->assertExitCode(1);
        Notification::assertNothingSent();
    }

    public function test_reconciliation_is_dry_by_default_requires_activation_and_records_system_actor_on_apply(): void
    {
        Notification::fake();
        [$hub, $area, $rider] = $this->crew(['work_availability' => 'unknown']);
        $pickup = $this->order($hub, $area, ['status' => 'READY_FOR_PICKUP', 'pickup_courier_id' => $rider->id]);
        $this->artisan('riders:reconcile-availability')->assertExitCode(0);
        $this->assertSame($rider->id, $pickup->fresh()->pickup_courier_id);
        config(['rider-work.enforce_availability' => false]);
        $this->artisan('riders:reconcile-availability', ['--apply' => true])->assertExitCode(1);
        config(['rider-work.enforce_availability' => true]);
        $key = 'logistics.dashboard.stats.'.$hub->id.'.'.today()->toDateString();
        Cache::put($key, ['available_riders' => 1], 60);
        $this->artisan('riders:reconcile-availability', ['--apply' => true])->assertExitCode(0);
        $this->assertNull($pickup->fresh()->pickup_courier_id);
        $this->assertNull($pickup->trackingEvents()->firstOrFail()->actor_id);
        $this->assertFalse(Cache::has($key));
        $this->artisan('riders:reconcile-availability', ['--apply' => true])->assertExitCode(0);
        $this->assertDatabaseCount('parcel_tracking_events', 1);
    }

    public function test_only_original_suspending_hub_can_reactivate_and_reactivation_does_not_make_rider_available(): void
    {
        Notification::fake();
        [$hub, $area, $rider] = $this->crew();
        $otherHub = $this->user('sorting_center');
        $otherArea = Area::create(['name' => 'Other', 'code' => 'O-'.Str::random(6), 'is_active' => true, 'sorting_center_id' => $otherHub->id]);
        $this->signIn($hub)->post(route('logistics.riders.suspend', $rider), ['reason' => 'Check rider credentials.'])->assertRedirect();
        $this->assertSame($hub->id, $rider->fresh()->suspending_hub_id);
        $rider->forceFill(['logistics_owner_id' => $otherHub->id])->save();
        $rider->serviceAreas()->sync([$otherArea->id => ['is_active' => true]]);
        $this->signIn($otherHub)->post(route('logistics.riders.reactivate', $rider))->assertNotFound();
        $rider->forceFill(['logistics_owner_id' => $hub->id])->save();
        $rider->serviceAreas()->sync([$area->id => ['is_active' => true]]);
        $this->signIn($hub)->post(route('logistics.riders.reactivate', $rider))->assertRedirect();
        $this->assertSame('approved', $rider->fresh()->status);
        $this->assertSame('unavailable', $rider->fresh()->work_availability);
        $this->assertSame(100000, $rider->fresh()->vehicle_capacity_grams);
        $this->assertNull($rider->fresh()->suspending_hub_id);
        $rider->forceFill(['status' => 'suspended', 'suspension_source' => 'logistics', 'suspension_previous_status' => 'approved'])->save();
        $this->post(route('logistics.riders.reactivate', $rider))->assertNotFound();
    }

    #[TestWith(['buyer'])]
    #[TestWith(['seller'])]
    #[TestWith(['sorting_center'])]
    public function test_other_roles_cannot_change_rider_availability(string $role): void
    {
        $actor = $this->user($role);
        $this->signIn($actor)->get(route('courier.work-availability.index'))->assertForbidden();
        $this->patch(route('courier.work-availability.update'), ['state' => 'available', 'version' => 0])->assertForbidden();
    }

    public function test_suspension_always_blocks_choices_and_new_work_even_before_availability_activation(): void
    {
        config(['rider-work.enforce_availability' => false]);
        $rider = $this->user('courier', ['status' => 'suspended']);
        $this->assertFalse(User::availableForNewWork()->whereKey($rider->id)->exists());
        $this->signIn($rider)->patch(route('courier.work-availability.update'), ['state' => 'available', 'version' => 0])->assertRedirectToRoute('login');
        $this->get(route('courier.work-availability.index'))->assertRedirectToRoute('login');
    }

    public function test_roster_distinguishes_all_reserved_parcels_from_local_job_limit_and_todays_deliveries(): void
    {
        [$hub, $area, $rider] = $this->crew(['work_availability' => 'unavailable']);
        $this->order($hub, $area, ['status' => 'READY_FOR_PICKUP', 'pickup_courier_id' => $rider->id]);
        $this->order($hub, $area, ['status' => 'DELIVERY_FAILED', 'delivery_courier_id' => $rider->id]);
        $this->order($hub, $area, ['status' => 'COMPLETED', 'delivery_courier_id' => $rider->id, 'delivered_at' => now()]);
        $response = $this->signIn($hub)->get(route('logistics.riders'))->assertOk()->assertSee('Unavailable')->assertSee('2 reserved / held parcels')->assertSee('1 delivered today');
        $this->assertSame(2, $response->viewData('workloads')[$rider->id]['count']);
        $this->assertSame(2000, $response->viewData('workloads')[$rider->id]['grams']);
        $this->assertSame(1, $response->viewData('riders')->firstWhere('id', $rider->id)->active_deliveries_count);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function user(string $role, array $data = []): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.com', 'password' => 'Password123!', 'first_name' => 'Availability', 'last_name' => 'Tester', 'business_name' => 'Synthetic hub',
            'role' => $role, 'status' => 'approved', 'contact_no' => '09123456789', 'work_availability' => $role === 'courier' ? 'available' : 'unknown',
            'vehicle_capacity_grams' => $role === 'courier' ? 100000 : null, 'vehicle_capacity_verified_at' => $role === 'courier' ? now() : null, ...$data]);
    }

    private function crew(array $data = []): array
    {
        $hub = $this->user('sorting_center');
        $area = Area::create(['name' => 'Synthetic area', 'code' => 'AV-'.Str::random(6), 'is_active' => true, 'sorting_center_id' => $hub->id]);
        $rider = $this->user('courier', ['logistics_owner_id' => $hub->id, ...$data]);
        $rider->serviceAreas()->attach($area->id, ['is_active' => true, 'is_primary' => true]);

        return [$hub, $area, $rider];
    }

    private function order(User $hub, Area $area, array $data = []): Order
    {
        return Order::forceCreate(['order_number' => 'AV-'.Str::random(12), 'buyer_id' => $this->user('buyer')->id, 'seller_id' => $this->user('seller')->id,
            'recipient_name' => 'Availability Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion',
            'street_address' => 'Synthetic test address', 'subtotal' => 100, 'shipping_fee' => 50, 'total_amount' => 150, 'payment_method' => 'COD',
            'status' => 'SORTED', 'sorting_center_id' => $hub->id, 'destination_area_id' => $area->id, 'parcel_weight_grams' => 1000, ...$data]);
    }

    private function assignment(Order $order, User $rider, User $hub, bool $accepted = false, string $status = 'active'): DeliveryAssignment
    {
        return DeliveryAssignment::create(['order_id' => $order->id, 'active_order_id' => $order->id, 'rider_id' => $rider->id, 'assigned_by' => $hub->id,
            'status' => $status, 'assigned_at' => now(), 'accepted_at' => $accepted ? now() : null]);
    }
}
