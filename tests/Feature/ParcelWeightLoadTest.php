<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Order;
use App\Models\ParcelTransfer;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutPricingService;
use App\Services\ParcelLoadService;
use App\Services\PlacePurchaseService;
use App\Services\RiderBadgeService;
use App\Services\StaffProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParcelWeightLoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_only_logistics_admin_can_verify_actual_vehicle_capacity(): void
    {
        $rider = $this->user('courier', ['vehicle_capacity_grams' => null, 'vehicle_capacity_verified_at' => null]);
        $payload = ['vehicle_capacity_kg' => '2.500', 'reason' => 'Verified current vehicle payload plate.'];
        $this->signIn($rider)->patch(route('logistics-admin.riders.capacity', $rider), $payload)->assertForbidden();
        $this->signIn($this->user('ecommerce_admin'))->patch(route('logistics-admin.riders.capacity', $rider), $payload)->assertForbidden();
        $admin = $this->user('logistics_admin');
        $this->signIn($admin)->patch(route('logistics-admin.riders.capacity', $rider), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2500, $rider->fresh()->vehicle_capacity_grams);
        $this->assertSame($admin->id, $rider->fresh()->vehicle_capacity_verified_by);
        $this->assertDatabaseHas('administrative_audits', ['action' => 'vehicle_capacity_verified']);
    }

    public function test_pickup_assignments_reserve_the_combined_weight_and_do_not_double_count_the_same_parcel(): void
    {
        $seller = $this->user('seller');
        $rider = $this->user('courier', ['vehicle_capacity_grams' => 2500]);
        $hub = $this->user('sorting_center');
        $first = $this->order($seller, ['parcel_weight_grams' => 1500]);
        $second = $this->order($seller, ['parcel_weight_grams' => 1200]);
        $this->signIn($hub)->post(route('logistics.orders.assignPickup', $first), ['pickup_courier_id' => $rider->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('logistics.orders.assignPickup', $second), ['pickup_courier_id' => $rider->id])->assertSessionHasErrors('vehicle_capacity_kg');
        $this->assertNull($second->fresh()->pickup_courier_id);
        $second->forceFill(['parcel_weight_grams' => 1000])->save();
        $this->post(route('logistics.orders.assignPickup', $second), ['pickup_courier_id' => $rider->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2500, app(ParcelLoadService::class)->summary($rider)['grams']);
        \DB::transaction(fn () => app(ParcelLoadService::class)->assertFits($first->fresh(), $rider));
        $first->forceFill(['status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $hub->id])->save();
        $this->assertSame(1000, app(ParcelLoadService::class)->summary($rider)['grams']);
    }

    public function test_missing_weights_unknown_reserved_parcels_and_unverified_vehicles_block_assignments(): void
    {
        $seller = $this->user('seller');
        $hub = $this->user('sorting_center');
        $rider = $this->user('courier', ['vehicle_capacity_verified_at' => null]);
        $order = $this->order($seller, ['parcel_weight_grams' => null]);
        $this->signIn($hub)->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $rider->id])->assertSessionHasErrors('parcel_weight_kg');
        $order->forceFill(['parcel_weight_grams' => 1000])->save();
        $this->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $rider->id])->assertSessionHasErrors('vehicle_capacity_kg');
        $rider->forceFill(['vehicle_capacity_verified_at' => now()])->save();
        $this->order($seller, ['pickup_courier_id' => $rider->id, 'parcel_weight_grams' => null]);
        $this->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $rider->id])->assertSessionHasErrors('parcel_weight_kg');
        $this->assertNull($order->fresh()->pickup_courier_id);
    }

    public function test_real_reweighing_is_audited_and_blocks_overweight_movement_without_discarding_the_measurement(): void
    {
        $seller = $this->user('seller');
        $rider = $this->user('courier', ['vehicle_capacity_grams' => 1000]);
        $hub = $this->user('sorting_center');
        $order = $this->order($seller, ['pickup_courier_id' => $rider->id]);
        $this->signIn($this->user('seller'))->patch(route('orders.weight', $order), ['parcel_weight_kg' => 1.5, 'reason' => 'Weighed on packing scale.'])->assertForbidden();
        $this->signIn($seller)->patch(route('orders.weight', $order), ['parcel_weight_kg' => '1.500', 'reason' => 'Added packaging and reweighed.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1500, $order->fresh()->parcel_weight_grams);
        $this->assertSame($seller->id, $order->fresh()->weight_recorded_by);
        $this->assertTrue(app(ParcelLoadService::class)->summary($rider)['overweight']);
        $this->signIn($rider)->post(route('courier.orders.claim', $order))->assertSessionHasErrors('vehicle_capacity_kg');
        $this->assertNull($order->fresh()->pickup_claimed_at);
        $this->assertDatabaseHas('administrative_audits', ['action' => 'parcel_weight_recorded']);
        $order->forceFill(['status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $hub->id])->save();
        $this->signIn($seller)->patch(route('orders.weight', $order), ['parcel_weight_kg' => 1, 'reason' => 'Attempt after hub intake.'])->assertUnprocessable();
        $this->signIn($hub)->patch(route('orders.weight', $order), ['parcel_weight_kg' => '1.600', 'reason' => 'Verified on hub intake scale.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1600, $order->fresh()->parcel_weight_grams);
    }

    public function test_unit_weight_is_required_and_order_estimates_are_frozen_at_purchase(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $payload = ['name' => 'Weighted product', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 10];
        $this->signIn($seller)->post(route('seller.products.store'), $payload)->assertSessionHasErrors('weight_kg');
        $this->post(route('seller.products.store'), $payload + ['weight_kg' => '0.625'])->assertRedirect()->assertSessionHasNoErrors();
        $product = Product::where('name', 'Weighted product')->firstOrFail();
        $this->assertSame(625, $product->weight_grams);
        $product->forceFill(['compliance_status' => 'approved'])->save();
        $cart = [['product_id' => $product->id, 'quantity' => 2]];
        $quote = app(CheckoutPricingService::class)->quote($cart);
        $purchase = app(PlacePurchaseService::class)->place($buyer, 'weight-checkout', hash('sha256', 'request'), $cart, $quote['hash'], null,
            ['recipient_name' => 'Test Buyer', 'recipient_contact' => $buyer->contact_no, 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Roque', 'street_address' => '1 Test Street']);
        $order = Order::where('purchase_id', $purchase->id)->firstOrFail();
        $product->update(['weight_grams' => 9000]);
        $this->assertSame(625, $order->items()->first()->unit_weight_grams);
        $this->assertSame(1250, app(ParcelLoadService::class)->estimate($order));
        $this->assertNull($order->parcel_weight_grams);
    }

    public function test_hub_transfers_use_a_verified_driver_and_share_the_vehicle_load_reservation(): void
    {
        $source = $this->user('sorting_center');
        $destination = $this->user('sorting_center');
        $seller = $this->user('seller');
        $driver = $this->user('courier', ['logistics_owner_id' => $source->id, 'vehicle_capacity_grams' => 2000]);
        $area = Area::create(['code' => 'WEIGHT-DEST', 'name' => 'Weight destination', 'sorting_center_id' => $destination->id, 'is_active' => true]);
        $first = $this->order($seller, ['status' => 'SORTED', 'sorting_center_id' => $source->id, 'destination_area_id' => $area->id, 'parcel_weight_grams' => 1200]);
        $transfer = ParcelTransfer::create(['order_id' => $first->id, 'active_order_id' => $first->id, 'source_hub_id' => $source->id, 'destination_hub_id' => $destination->id, 'requested_by' => $source->id, 'status' => 'PLANNED', 'reason' => 'Test weight planning.']);
        $this->signIn($source)->post(route('parcel-transfers.release', $transfer), ['parcel_reference' => 'EZP:'.$first->parcel_code, 'transport_reference' => 'MANIFEST-1', 'carrier_rider_id' => $driver->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1200, app(ParcelLoadService::class)->summary($driver)['grams']);
        $second = $this->order($seller, ['status' => 'SORTED', 'sorting_center_id' => $source->id, 'destination_area_id' => $area->id, 'parcel_weight_grams' => 1000]);
        $next = ParcelTransfer::create(['order_id' => $second->id, 'active_order_id' => $second->id, 'source_hub_id' => $source->id, 'destination_hub_id' => $destination->id, 'requested_by' => $source->id, 'status' => 'PLANNED', 'reason' => 'Next manifest parcel.']);
        $this->post(route('parcel-transfers.release', $next), ['parcel_reference' => 'EZP:'.$second->parcel_code, 'transport_reference' => 'MANIFEST-2', 'carrier_rider_id' => $driver->id])->assertSessionHasErrors('vehicle_capacity_kg');
        $this->assertSame('PLANNED', $next->fresh()->status);
        $this->signIn($destination)->post(route('parcel-transfers.receive', $transfer), ['parcel_reference' => 'EZP:'.$first->parcel_code])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, app(ParcelLoadService::class)->summary($driver)['grams']);
    }

    public function test_kg_validation_rejects_zero_negative_letters_and_excess_precision(): void
    {
        $seller = $this->user('seller');
        $order = $this->order($seller);
        $this->signIn($seller);
        foreach (['0', '-1', 'heavy', '0.0001', '100001'] as $weight) {
            $this->patch(route('orders.weight', $order), ['parcel_weight_kg' => $weight, 'reason' => 'Invalid measurement check.'])->assertSessionHasErrors('parcel_weight_kg');
        }
        $this->assertSame(1000, $order->fresh()->parcel_weight_grams);
    }

    public function test_operational_planning_pages_render_weights_and_keep_unrelated_accounts_out(): void
    {
        $seller = $this->user('seller');
        $hub = $this->user('sorting_center');
        $rider = $this->user('courier', ['logistics_owner_id' => $hub->id]);
        $order = $this->order($seller, ['pickup_courier_id' => $rider->id, 'parcel_weight_grams' => 1250]);
        foreach ([$seller, $hub, $rider, $this->user('logistics_admin')] as $actor) {
            $this->signIn($actor)->get(route('load-planning.index'))->assertOk()->assertSee('1.250 kg')->assertSee($order->order_number);
        }
        $this->signIn($this->user('seller'))->get(route('load-planning.index'))->assertOk()->assertDontSee($order->order_number);
        $this->signIn($seller)->get(route('seller.orders.show', $order))->assertOk()->assertSee('Packed parcel weight (kg)')->assertSee('1.250 kg');
    }

    public function test_vehicle_changes_invalidate_capacity_but_contact_changes_preserve_it(): void
    {
        $admin = $this->user('logistics_admin');
        $rider = $this->user('courier', ['vehicle_capacity_verified_by' => $admin->id]);
        $profiles = app(StaffProfileService::class);
        $contact = $profiles->submit($rider, $rider, ['first_name' => 'Updated']);
        $profiles->review($admin, $contact, true, null);
        $this->assertSame(100000, $rider->fresh()->vehicle_capacity_grams);

        $vehicle = $profiles->submit($rider, $rider, ['plate_number' => 'CAP 456']);
        $profiles->review($admin, $vehicle, true, null);
        $this->assertNull($rider->fresh()->vehicle_capacity_grams);
        $this->assertNull($rider->fresh()->vehicle_capacity_verified_by);
        $this->assertNull($rider->fresh()->vehicle_capacity_verified_at);
    }

    public function test_capacity_is_checked_again_at_physical_dispatch_after_assignment(): void
    {
        $hub = $this->user('sorting_center');
        $rider = $this->user('courier', ['logistics_owner_id' => $hub->id]);
        $order = $this->order($this->user('seller'), ['status' => 'ASSIGNED_TO_RIDER', 'sorting_center_id' => $hub->id, 'delivery_courier_id' => $rider->id]);
        $badge = app(RiderBadgeService::class)->ensure($rider);
        $payload = ['rider_badge' => 'EZR:'.$badge, 'parcel_reference' => 'EZP:'.$order->parcel_code, 'method' => 'manual'];
        $rider->forceFill(['vehicle_capacity_grams' => 500])->save();
        $this->signIn($hub)->post(route('logistics.orders.releaseToRider', $order), $payload)->assertSessionHasErrors('vehicle_capacity_kg');
        $this->assertNull($order->fresh()->hub_released_at);
        $this->assertDatabaseMissing('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'hub_released_to_rider']);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function user(string $role, array $extra = []): User
    {
        return User::forceCreate(array_merge(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'first_name' => 'Weight', 'last_name' => 'Test', 'role' => $role, 'status' => 'approved', 'contact_no' => '09'.fake()->unique()->numerify('#########'), 'business_name' => 'Weight shop', 'vehicle_capacity_grams' => $role === 'courier' ? 100000 : null, 'vehicle_capacity_verified_at' => $role === 'courier' ? now() : null], $extra));
    }

    private function order(User $seller, array $extra = []): Order
    {
        return Order::forceCreate(array_merge(['order_number' => 'WEIGHT-'.fake()->unique()->numerify('######'), 'buyer_id' => $this->user('buyer')->id, 'seller_id' => $seller->id, 'recipient_name' => 'Test Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Roque', 'street_address' => '1 Test Street', 'subtotal' => 100, 'total_amount' => 150, 'payment_method' => 'COD', 'status' => 'READY_FOR_PICKUP', 'pickup_requested_at' => now(), 'parcel_weight_grams' => 1000], $extra));
    }
}
