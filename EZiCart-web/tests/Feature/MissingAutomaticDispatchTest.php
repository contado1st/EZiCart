<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\DispatchCycle;
use App\Models\DispatchLocation;
use App\Models\Order;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use App\Services\DispatchLocationService;
use App\Services\DispatchOfferService;
use App\Services\ParcelLoadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MissingAutomaticDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_scheduler_initiates_eligible_stage_once_and_does_not_restart_unassigned(): void
    {
        Notification::fake();
        [$hub, $area, $order] = $this->crew();
        $this->rider($hub, $area, 14.1401);
        $this->artisan('dispatch:process')->assertSuccessful();
        $this->assertDatabaseCount('dispatch_cycles', 0);
        $this->artisan('dispatch:process', ['--apply' => true])->assertSuccessful();
        $cycle = DispatchCycle::sole();
        $this->assertSame('OFFERING', $cycle->status);
        $this->artisan('dispatch:process', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('dispatch_cycles', 1);
        $offer = $cycle->offers()->sole();
        app(DispatchOfferService::class)->respond($offer, $offer->rider, false);
        $this->assertSame('UNASSIGNED', $cycle->fresh()->status);
        $this->artisan('dispatch:process', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('dispatch_cycles', 1);
        $this->assertDatabaseCount('dispatch_offers', 1);
    }

    public function test_scheduler_does_not_initiate_work_without_a_verified_origin_pin(): void
    {
        [$hub, $area, $order] = $this->crew();
        $this->rider($hub, $area, 14.1401);
        DispatchLocation::where('user_id', $order->seller_id)->delete();
        $this->artisan('dispatch:process', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('dispatch_cycles', 0);
    }

    public function test_nearest_fresh_rider_and_stable_tie_receive_one_offer_and_acceptance_does_not_invent_pickup(): void
    {
        $this->travelTo(now()->startOfSecond());
        Notification::fake();
        [$hub, $area, $order] = $this->crew();
        $far = $this->rider($hub, $area, 14.25);
        $near = $this->rider($hub, $area, 14.1401);
        $tie = $this->rider($hub, $area, 14.1401);
        $key = (string) Str::uuid();
        $cycle = app(DispatchOfferService::class)->start($order, $hub, $key);
        $this->assertSame($cycle->id, app(DispatchOfferService::class)->start($order, $hub, $key)->id);
        $offer = $cycle->offers()->sole();
        $this->assertSame($near->id, $offer->rider_id);
        $this->assertTrue($offer->expires_at->equalTo(now()->addSeconds(60)));
        $this->assertSame(1, app(ParcelLoadService::class)->summary($near)['count']);
        $this->assertSame('ACCEPTED', app(DispatchOfferService::class)->respond($offer, $near, true));
        $this->assertSame($near->id, $order->fresh()->pickup_courier_id);
        $this->assertNotNull($order->fresh()->pickup_claimed_at);
        $this->assertNull($order->fresh()->picked_up_at);
        $this->assertSame('READY_FOR_PICKUP', $order->fresh()->status);
        $this->assertSame(1000, app(ParcelLoadService::class)->summary($near)['grams']);
        $this->assertDatabaseCount('dispatch_cycles', 1);
        $this->assertDatabaseCount('dispatch_offers', 1);
        $this->as($hub)->get(route('dispatch.index'))->assertOk()->assertSee('Cycles / Unassigned');
        Notification::assertSentTo($near, WebOperationsNotification::class);
    }

    public function test_three_distinct_declines_end_in_unassigned_and_scheduler_does_not_restart_it(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$hub, $area, $order] = $this->crew();
        for ($i = 1; $i <= 3; $i++) {
            $this->rider($hub, $area, 14.14 + $i / 10000);
        }
        $cycle = app(DispatchOfferService::class)->start($order, $hub, (string) Str::uuid());
        for ($i = 0; $i < 3; $i++) {
            $offer = $cycle->offers()->where('status', 'PENDING')->sole();
            app(DispatchOfferService::class)->respond($offer, $offer->rider, false);
        }
        $this->assertSame('UNASSIGNED', $cycle->fresh()->status);
        $this->assertNull($cycle->fresh()->active_order_id);
        $this->assertSame(3, $cycle->offers()->where('status', 'DECLINED')->count());
        $this->assertSame(3, $cycle->offers()->distinct()->count('rider_id'));
        $this->artisan('dispatch:process', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('dispatch_offers', 3);
        $this->as($hub)->get(route('dispatch.index', ['status' => 'UNASSIGNED']))->assertSee($order->order_number)->assertSee('UNASSIGNED');
        $retry = app(DispatchOfferService::class)->start($order, $hub, (string) Str::uuid());
        $this->assertSame('OFFERING', $retry->status);
    }

    public function test_expiry_boundary_cannot_be_accepted_and_three_expiries_are_counted_separately_from_declines(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$hub, $area, $order] = $this->crew();
        for ($i = 1; $i <= 3; $i++) {
            $this->rider($hub, $area, 14.14 + $i / 10000);
        }
        $cycle = app(DispatchOfferService::class)->start($order, $hub, (string) Str::uuid());
        $first = $cycle->offers()->sole();
        $this->travel(59)->seconds();
        $this->artisan('dispatch:process', ['--apply' => true])->assertSuccessful();
        $this->assertSame('PENDING', $first->fresh()->status);
        $this->travel(1)->seconds();
        $this->assertSame('EXPIRED', app(DispatchOfferService::class)->respond($first, $first->rider, true));
        for ($i = 0; $i < 2; $i++) {
            $this->travel(60)->seconds();
            $this->artisan('dispatch:process', ['--apply' => true])->assertSuccessful();
        }
        $this->assertSame('UNASSIGNED', $cycle->fresh()->status);
        $this->assertSame(3, $cycle->offers()->where('status', 'EXPIRED')->count());
        $this->assertSame(0, $cycle->offers()->where('status', 'DECLINED')->count());
        $this->assertNull($order->fresh()->pickup_courier_id);
    }

    #[TestWith(['missing_pin'])]
    #[TestWith(['stale'])]
    #[TestWith(['unavailable'])]
    #[TestWith(['capacity'])]
    #[TestWith(['unknown_weight'])]
    #[TestWith(['license'])]
    #[TestWith(['dedicated'])]
    #[TestWith(['three_jobs'])]
    public function test_ineligible_candidates_fall_back_without_guessing_or_overbooking(string $condition): void
    {
        $this->travelTo(now()->startOfSecond());
        [$hub, $area, $order] = $this->crew();
        $rider = $this->rider($hub, $area);
        match ($condition) {
            'missing_pin' => DispatchLocation::where('user_id', $order->seller_id)->delete(),
            'stale' => DispatchLocation::where('user_id', $rider->id)->update(['recorded_at' => now()->subSeconds(301)]),
            'unavailable' => $rider->forceFill(['work_availability' => 'unavailable'])->save(),
            'capacity' => $rider->forceFill(['vehicle_capacity_grams' => 500])->save(),
            'unknown_weight' => $order->forceFill(['parcel_weight_grams' => null])->save(),
            'license' => $rider->forceFill(['license_expires_at' => today()->subDay()])->save(),
            'dedicated' => $rider->forceFill(['hub_transfer_only' => true])->save(),
            'three_jobs' => collect(range(1, 3))->each(fn () => $this->parcel($hub, $area, $order->seller, ['pickup_courier_id' => $rider->id])),
        };
        $cycle = app(DispatchOfferService::class)->start($order, $hub, (string) Str::uuid());
        $this->assertSame('UNASSIGNED', $cycle->status);
        $this->assertDatabaseCount('dispatch_offers', 0);
        $this->assertNull($order->fresh()->pickup_courier_id);
    }

    public function test_pending_offer_capacity_is_shared_with_manual_assignments_and_released_once(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$hub, $area, $order] = $this->crew();
        $rider = $this->rider($hub, $area, extra: ['vehicle_capacity_grams' => 1500]);
        $cycle = app(DispatchOfferService::class)->start($order, $hub, (string) Str::uuid());
        $another = $this->parcel($hub, $area, $order->seller);
        $this->as($hub)->post(route('logistics.orders.assignPickup', $another), ['pickup_courier_id' => $rider->id])->assertSessionHasErrors('vehicle_capacity_kg');
        $fallback = app(DispatchOfferService::class)->start($another, $hub, (string) Str::uuid());
        $this->assertSame('UNASSIGNED', $fallback->status);
        app(DispatchOfferService::class)->cancel($cycle, $hub, 'Switch to manual assignment after review.');
        $this->assertSame(0, app(ParcelLoadService::class)->summary($rider)['grams']);
        $this->as($hub)->post(route('logistics.orders.assignPickup', $another), ['pickup_courier_id' => $rider->id])->assertSessionHasNoErrors();
        $this->assertSame($rider->id, $another->fresh()->pickup_courier_id);
    }

    public function test_live_cycle_prevents_manual_assignment_and_changed_pin_invalidates_acceptance(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$hub, $area, $order] = $this->crew();
        $rider = $this->rider($hub, $area);
        $cycle = app(DispatchOfferService::class)->start($order, $hub, (string) Str::uuid());
        $this->as($hub)->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $rider->id])->assertUnprocessable();
        app(DispatchLocationService::class)->pin($hub, $order->seller, ['latitude' => 14.2, 'longitude' => 121.47, 'reason' => 'Corrected the confirmed pickup entrance.']);
        $this->assertSame('EXPIRED', app(DispatchOfferService::class)->respond($cycle->offers()->sole(), $rider, true));
        $this->assertSame('UNASSIGNED', $cycle->fresh()->status);
        $this->assertNull($order->fresh()->pickup_courier_id);
    }

    public function test_delivery_offer_uses_hub_pin_and_retains_existing_handoff_and_acceptance_records(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$hub, $area, $order] = $this->crew();
        $order->forceFill(['status' => 'SORTED', 'sorting_center_id' => $hub->id])->save();
        app(DispatchLocationService::class)->pin($hub, $hub, ['latitude' => 14.15, 'longitude' => 121.48, 'reason' => 'Confirmed actual hub dispatch entrance.']);
        $rider = $this->rider($hub, $area);
        $cycle = app(DispatchOfferService::class)->start($order, $hub, (string) Str::uuid());
        $this->assertSame('ACCEPTED', app(DispatchOfferService::class)->respond($cycle->offers()->sole(), $rider, true));
        $this->assertSame('ASSIGNED_TO_RIDER', $order->fresh()->status);
        $this->assertNotNull($order->deliveryAssignments()->sole()->accepted_at);
        $this->assertNull($order->fresh()->hub_released_at);
    }

    public function test_location_permissions_freshness_and_offer_ownership_are_enforced(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$hub, $area, $order] = $this->crew();
        $rider = $this->rider($hub, $area);
        $this->as($rider)->post(route('dispatch.position'), ['latitude' => 999, 'longitude' => 121, 'accuracy_meters' => 2, 'captured_at' => now()->toIso8601String()])->assertSessionHasErrors('latitude');
        $this->post(route('dispatch.position'), ['latitude' => 14, 'longitude' => 121, 'accuracy_meters' => 251, 'captured_at' => now()->toIso8601String()])->assertSessionHasErrors('accuracy_meters');
        $this->post(route('dispatch.position'), ['latitude' => 14, 'longitude' => 121, 'accuracy_meters' => 2, 'captured_at' => now()->subMinutes(3)->toIso8601String()])->assertUnprocessable();
        $other = $this->user('sorting_center');
        $this->as($other)->post(route('dispatch.start', $order), ['operation_key' => (string) Str::uuid()])->assertNotFound();
        $cycle = app(DispatchOfferService::class)->start($order, $hub, (string) Str::uuid());
        $this->as($this->rider($hub, $area, 14.3))->post(route('dispatch.respond', $cycle->offers()->sole()), ['decision' => 'accept'])->assertNotFound();
        $this->as($order->buyer)->post(route('dispatch.position'), ['latitude' => 14])->assertForbidden();
        $this->as($rider)->get(route('dispatch.index'))->assertSee('Share location for offers');
    }

    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function user(string $role, array $extra = []): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.invalid', 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'contact_no' => '09123456789', 'email_verified_at' => now(), 'first_name' => 'Dispatch', 'last_name' => 'Fixture', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Verified fixture street', ...$extra]);
    }

    private function crew(): array
    {
        $hub = $this->user('sorting_center');
        $seller = $this->user('seller');
        $area = Area::create(['name' => 'Origin fixture', 'code' => Str::upper(Str::random(8)), 'is_active' => true, 'sorting_center_id' => $hub->id]);
        AreaMunicipality::create(['area_id' => $area->id, 'province' => 'Laguna', 'municipality' => 'Majayjay', 'province_normalized' => 'laguna', 'municipality_normalized' => 'majayjay']);
        $order = $this->parcel($hub, $area, $seller);
        app(DispatchLocationService::class)->pin($hub, $seller, ['latitude' => 14.14, 'longitude' => 121.47, 'reason' => 'Verified actual seller pickup entrance.']);

        return [$hub, $area, $order];
    }

    private function parcel(User $hub, Area $area, User $seller, array $extra = []): Order
    {
        return Order::forceCreate(['order_number' => 'AUTO-'.Str::random(12), 'buyer_id' => $this->user('buyer')->id, 'seller_id' => $seller->id, 'recipient_name' => 'Synthetic Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Fixture', 'subtotal' => 100, 'total_amount' => 150, 'shipping_fee' => 50, 'payment_method' => 'COD', 'status' => 'READY_FOR_PICKUP', 'destination_area_id' => $area->id, 'parcel_weight_grams' => 1000, 'pickup_requested_at' => now()->subMinute(), ...$extra]);
    }

    private function rider(User $hub, Area $area, float $latitude = 14.1401, array $extra = []): User
    {
        $rider = $this->user('courier', ['logistics_owner_id' => $hub->id, 'work_availability' => 'available', 'vehicle_capacity_grams' => 10000, 'vehicle_capacity_verified_at' => now(), 'license_expires_at' => today()->addYear(), 'hub_transfer_only' => false, ...$extra]);
        $rider->serviceAreas()->attach($area->id, ['is_active' => true, 'is_primary' => true]);
        app(DispatchLocationService::class)->rider($rider, ['latitude' => $latitude, 'longitude' => 121.47, 'accuracy_meters' => 10, 'captured_at' => now()->toIso8601String()]);

        return $rider;
    }
}
