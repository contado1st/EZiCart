<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ParcelTransfer;
use App\Models\User;
use App\Services\ParcelLoadService;
use Database\Seeders\HubTransferDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HubTransferFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_full_hub_transfer_reserves_dedicated_driver_delivers_to_destination_and_resumes_last_mile(): void
    {
        $this->seed(HubTransferDemoSeeder::class);
        $order = Order::where('order_number', 'HUB-DEMO-001')->firstOrFail();
        $source = $this->account('hub.majayjay');
        $destination = $this->account('hub.santacruz');
        $driver = $this->account('transport.majayjay');
        $this->signIn($this->account('hub.admin'))->post(route('logistics-admin.orders.transfer', $order), ['destination_hub_id' => $destination->id, 'reason' => 'Transfer to destination routing hub.'])->assertRedirect()->assertSessionHasNoErrors();
        $transfer = ParcelTransfer::firstOrFail();
        $this->assertSame('PLANNED', $transfer->status);
        $this->signIn($destination)->post(route('parcel-transfers.receive', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertUnprocessable();
        $this->signIn($source)->post(route('parcel-transfers.release', $transfer), $this->releasePayload($order, $driver))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('IN_TRANSIT', $transfer->fresh()->status);
        $this->assertSame($source->id, $order->fresh()->sorting_center_id);
        $this->assertSame(21000, app(ParcelLoadService::class)->summary($driver)['grams']);
        $this->assertTrue($driver->notifications()->where('data', 'like', '%HUB-DEMO-001%')->exists());
        $this->signIn($driver)->get(route('courier.dashboard'))->assertRedirect(route('parcel-transfers.index'));
        $this->get(route('parcel-transfers.index'))->assertOk()->assertSee('HUB-DEMO-001')->assertSee('Santa Cruz')->assertSee('21.000 kg')->assertDontSee('HUB-DEMO-002');
        $this->get(route('load-planning.index'))->assertOk()->assertSee('HUB-DEMO-001');
        $this->get(route('workflow.attention'))->assertOk()->assertJsonPath('count', 1);
        $this->signIn($destination)->post(route('parcel-transfers.receive', $transfer), ['parcel_reference' => 'EZP:WRONG'])->assertUnprocessable();
        $this->post(route('parcel-transfers.receive', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('RECEIVED', $transfer->fresh()->status);
        $this->assertNull($transfer->fresh()->active_order_id);
        $this->assertSame($destination->id, $order->fresh()->sorting_center_id);
        $this->assertSame('AT_SORTING_CENTER', $order->fresh()->status);
        $this->assertSame(0, app(ParcelLoadService::class)->summary($driver)['grams']);
        $this->post(route('parcel-transfers.receive', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertUnprocessable();
        $this->assertSame(1, $order->trackingEvents()->where('event_type', 'hub_transfer_received')->count());
        $this->post(route('logistics.orders.sort', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $this->account('local.santacruz')->id, 'coverage_override_reason' => 'Demo final delivery supervised by destination hub.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('ASSIGNED_TO_RIDER', $order->fresh()->status);
        $this->assertSame($this->account('local.santacruz')->id, $order->fresh()->delivery_courier_id);
        $this->assertDatabaseHas('administrative_audits', ['action' => 'hub_transfer_released']);
        $this->assertDatabaseHas('administrative_audits', ['action' => 'hub_transfer_received']);
    }

    public function test_driver_duties_and_hub_ownership_are_enforced_on_the_server(): void
    {
        $this->seed(HubTransferDemoSeeder::class);
        $order = Order::where('order_number', 'HUB-DEMO-001')->firstOrFail();
        $this->signIn($this->account('hub.admin'))->post(route('logistics-admin.orders.transfer', $order), ['destination_hub_id' => $this->account('hub.santacruz')->id, 'reason' => 'Dedicated driver verification.'])->assertRedirect();
        $transfer = ParcelTransfer::firstOrFail();
        $this->signIn($this->account('hub.majayjay'))->post(route('parcel-transfers.release', $transfer), $this->releasePayload($order, $this->account('local.majayjay')))->assertSessionHasErrors('carrier_rider_id');
        $this->post(route('parcel-transfers.release', $transfer), $this->releasePayload($order, $this->account('transport.calamba')))->assertForbidden();
        $this->assertSame('PLANNED', $transfer->fresh()->status);
        $pickup = $order->replicate(['order_number', 'parcel_code']);
        $pickup->forceFill(['order_number' => 'DUTY-PICKUP', 'status' => 'READY_FOR_PICKUP', 'sorting_center_id' => $this->account('hub.majayjay')->id, 'destination_area_id' => null, 'pickup_requested_at' => now()])->save();
        $this->post(route('logistics.orders.assignPickup', $pickup), ['pickup_courier_id' => $this->account('transport.majayjay')->id])->assertSessionHasErrors('carrier_rider_id');
        $this->assertNull($pickup->fresh()->pickup_courier_id);
        $this->signIn($this->account('local.majayjay'))->get(route('parcel-transfers.index'))->assertForbidden();
        $this->signIn($this->account('transport.calamba'))->get(route('parcel-transfers.index'))->assertOk()->assertDontSee('HUB-DEMO-001');
        $this->post(route('parcel-transfers.release', $transfer), $this->releasePayload($order, $this->account('transport.calamba')))->assertForbidden();
        $this->post(route('parcel-transfers.receive', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertForbidden();
    }

    public function test_admin_duty_changes_are_audited_and_blocked_while_vehicle_has_reserved_loads(): void
    {
        $this->seed(HubTransferDemoSeeder::class);
        $rider = $this->account('local.calamba');
        $payload = ['vehicle_capacity_kg' => 40, 'hub_transfer_only' => 1, 'reason' => 'Assign dedicated hub transport duty.'];
        $this->signIn($rider)->patch(route('logistics-admin.riders.capacity', $rider), $payload)->assertForbidden();
        $this->signIn($this->account('hub.admin'))->patch(route('logistics-admin.riders.capacity', $rider), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($rider->fresh()->hub_transfer_only);
        $this->assertDatabaseHas('administrative_audits', ['action' => 'vehicle_capacity_verified']);
        $order = Order::where('order_number', 'HUB-DEMO-002')->firstOrFail();
        $order->forceFill(['delivery_courier_id' => $rider->id, 'status' => 'ASSIGNED_TO_RIDER'])->save();
        $this->patch(route('logistics-admin.riders.capacity', $rider), array_replace($payload, ['hub_transfer_only' => 0]))->assertSessionHasErrors('hub_transfer_only');
        $this->assertTrue($rider->fresh()->hub_transfer_only);
    }

    public function test_demo_seeder_rerun_preserves_passwords_parcel_progress_and_account_counts(): void
    {
        $this->seed(HubTransferDemoSeeder::class);
        $order = Order::where('order_number', 'HUB-DEMO-001')->firstOrFail();
        $order->forceFill(['status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $this->account('hub.santacruz')->id])->save();
        $rider = $this->account('transport.majayjay');
        $rider->forceFill(['password' => 'ChangedDemo123!'])->save();
        $this->seed(HubTransferDemoSeeder::class);
        $this->assertDatabaseCount('users', 12);
        $this->assertDatabaseCount('orders', 2);
        $this->assertSame('AT_SORTING_CENTER', $order->fresh()->status);
        $this->assertTrue(Hash::check('ChangedDemo123!', $rider->fresh()->password));
        $this->assertTrue(Hash::check('Password123!', $this->account('hub.calamba')->password));
    }

    private function account(string $prefix): User
    {
        return User::where('email', $prefix.'@ezicart.com')->firstOrFail();
    }

    private function signIn(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function releasePayload(Order $order, User $driver): array
    {
        return ['parcel_reference' => 'EZP:'.$order->parcel_code, 'carrier_rider_id' => $driver->id, 'transport_reference' => 'DEMO-MANIFEST-001'];
    }
}
