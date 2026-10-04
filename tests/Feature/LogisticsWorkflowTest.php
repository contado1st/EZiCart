<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogisticsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_pickup_claim_is_exclusive_and_parcel_intake_persists_events(): void
    {
        $courier = $this->user('courier', 'approved');
        $otherCourier = $this->user('courier', 'approved');
        $center = $this->user('sorting_center', 'approved');
        $order = $this->order(['status' => 'READY_FOR_PICKUP', 'pickup_requested_at' => now(), 'pickup_scheduled_for' => now()->addDay(), 'pickup_window' => 'Morning (8 AM–12 PM)']);

        $this->actingAs($center)->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $courier->id])->assertRedirect();
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'pickup_assigned']);
        $this->actingAs($courier)->post(route('courier.orders.claim', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'pickup_courier_id' => $courier->id, 'status' => 'READY_FOR_PICKUP']);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'pickup_accepted']);

        $this->actingAs($otherCourier)->post(route('courier.orders.claim', $order))->assertForbidden();
        $this->actingAs($courier)->post(route('courier.orders.confirmPickup', $order))->assertRedirect();
        $this->actingAs($order->seller)->post(route('seller.orders.confirmHandover', $order))->assertRedirect();
        $this->actingAs($courier)->post(route('courier.orders.confirmPickup', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PICKED_UP']);

        $this->actingAs($center)->post(route('logistics.orders.receive', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $center->id]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'hub_received']);
    }

    public function test_sorting_and_dispatch_require_destination_and_eligible_rider(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $order = $this->order(['status' => 'AT_SORTING_CENTER', 'province' => '  LAGUNA  ', 'municipality' => '  MAJAYJAY  ']);

        $this->actingAs($center)->post(route('logistics.orders.sort', $order), ['delivery_area' => 'Untrusted free text'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'destination_area_id' => $order->destination_area_id, 'delivery_area' => 'Majayjay']);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'sorted']);

        $pendingRider = $this->user('courier', 'pending');
        $this->actingAs($center)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $pendingRider->id])->assertUnprocessable();

        $rider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $this->actingAs($center)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $rider->id])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'delivery_courier_id' => $rider->id, 'status' => 'ASSIGNED_TO_RIDER']);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'rider_assigned']);
    }

    public function test_rider_can_complete_only_owned_delivery_and_failure_requires_reason(): void
    {
        $rider = $this->user('courier', 'approved');
        $otherRider = $this->user('courier', 'approved');
        $order = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $rider->id]);

        $this->actingAs($otherRider)->patch(route('courier.orders.completeDelivery', $order), ['recipient_confirmation' => 'Recipient'])->assertForbidden();
        $this->actingAs($rider)->patch(route('courier.orders.failDelivery', $order), [])->assertSessionHasErrors('failure_reason');
        $this->actingAs($rider)->patch(route('courier.orders.failDelivery', $order), ['failure_reason' => 'recipient_unavailable'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'DELIVERY_FAILED', 'delivery_failure_reason' => 'recipient_unavailable']);

        $deliveryOrder = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $rider->id]);
        $this->actingAs($rider)->patch(route('courier.orders.completeDelivery', $deliveryOrder), ['recipient_confirmation' => 'A. Buyer', 'delivery_notes' => 'Left at door'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $deliveryOrder->id, 'status' => 'DELIVERED']);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $deliveryOrder->id, 'event_type' => 'delivered']);
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $deliveryOrder->id, 'outcome' => 'delivered', 'attempt_no' => 1]);
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $order->id, 'outcome' => 'failed', 'attempt_no' => 1]);
    }

    public function test_rider_tracking_only_shows_orders_assigned_to_that_rider(): void
    {
        $rider = $this->user('courier', 'approved');
        $otherRider = $this->user('courier', 'approved');
        $ownOrder = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $rider->id]);
        $otherOrder = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $otherRider->id]);

        $this->actingAs($rider)->get(route('courier.tracking'))
            ->assertOk()->assertSee($ownOrder->order_number)->assertDontSee($otherOrder->order_number);
    }

    public function test_logistics_can_approve_reject_reassign_and_return_failed_parcels(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $pendingRider = $this->user('courier', 'pending');
        $rejectedRider = $this->user('courier', 'pending');
        $firstRider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $nextRider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);

        $this->actingAs($center)->post(route('logistics.riders.approve', $pendingRider))->assertRedirect();
        $this->actingAs($center)->post(route('logistics.riders.reject', $rejectedRider))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $pendingRider->id, 'status' => 'approved']);
        $this->assertDatabaseHas('users', ['id' => $rejectedRider->id, 'status' => 'rejected']);

        $order = $this->order(['status' => 'SORTED', 'delivery_area' => 'Majayjay']);
        $this->actingAs($center)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $firstRider->id])->assertRedirect();
        $this->actingAs($center)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $nextRider->id])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'delivery_courier_id' => $nextRider->id]);

        $order->update(['status' => 'DELIVERY_FAILED']);
        $this->actingAs($center)->post(route('logistics.orders.return', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'RETURN_IN_TRANSIT', 'delivery_courier_id' => $nextRider->id]);
        $this->actingAs($order->seller)->post(route('seller.orders.confirmReturn', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'RETURNED_TO_SELLER', 'delivery_courier_id' => $nextRider->id]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'returned_to_seller']);
    }

    public function test_logistics_workspace_pages_render_with_live_records(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $this->order(['status' => 'AT_SORTING_CENTER']);

        $this->actingAs($center)->get(route('logistics.dashboard'))->assertOk();
        $this->get(route('logistics.intake'))->assertOk();
        $this->get(route('logistics.pickupRequests'))->assertOk();
        $this->get(route('logistics.sorting'))->assertOk();
        $this->get(route('logistics.dispatch'))->assertOk();
        $this->get(route('logistics.tracking'))->assertOk();
        $this->get(route('logistics.riders'))->assertOk();
        $this->get(route('logistics.reports'))->assertOk();
    }

    public function test_courier_workspace_pages_render_and_detail_is_owner_scoped(): void
    {
        $courier = $this->user('courier', 'approved');
        $assignedOrder = $this->order(['status' => 'ASSIGNED_TO_RIDER', 'delivery_courier_id' => $courier->id]);
        $otherOrder = $this->order(['status' => 'ASSIGNED_TO_RIDER', 'delivery_courier_id' => $this->user('courier', 'approved')->id]);

        $this->actingAs($courier)->get(route('courier.dashboard'))->assertOk();
        $this->get(route('courier.tracking'))->assertOk();
        $this->get(route('courier.history'))->assertOk();
        $this->get(route('courier.orders.show', $assignedOrder))->assertOk()->assertSee($assignedOrder->order_number);
        $this->get(route('courier.orders.show', $otherOrder))->assertForbidden();
    }

    public function test_cod_delivery_requires_exact_amount_in_centavos(): void
    {
        Storage::fake('private');
        $rider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $order = $this->order(['status' => 'OUT_FOR_DELIVERY', 'payment_method' => 'COD', 'delivery_courier_id' => $rider->id]);

        $this->actingAs($rider)->patch(route('courier.orders.completeDelivery', $order), ['recipient_confirmation' => 'Buyer'])
            ->assertSessionHasErrors('cod_collected_amount');
        $this->patch(route('courier.orders.completeDelivery', $order), [
            'recipient_confirmation' => 'Buyer',
            'cod_collected_amount' => '150.01',
        ])->assertSessionHasErrors('cod_collected_amount');
        $this->patch(route('courier.orders.completeDelivery', $order), [
            'recipient_confirmation' => 'Buyer',
            'cod_collected_amount' => '150.00',
            'proof_file' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'DELIVERED', 'cod_collected_amount' => '150.00']);
        $attempt = $order->deliveryAttempts()->firstOrFail();
        Storage::disk('private')->assertExists($attempt->proof_path);
        $this->get(route('delivery-attempts.proof', $attempt))->assertOk();
        $otherBuyer = $this->user('buyer', 'approved');
        $this->actingAs($otherBuyer)->get(route('delivery-attempts.proof', $attempt))->assertForbidden();
        $this->actingAs($order->buyer)->get(route('delivery-attempts.proof', $attempt))->assertOk();
        $this->get(route('buyer.orders.show', $order))->assertOk()->assertSee('Download proof');
    }

    public function test_buyer_registration_stays_blocked_until_admin_approval_and_documents_are_private(): void
    {
        Storage::fake('private');

        $this->post(route('register.post'), [
            'first_name' => 'New', 'last_name' => 'Buyer', 'sex' => 'Other',
            'email' => 'new-buyer@example.test', 'contact_no' => '09123456789',
            'birthday' => '1990-01-01', 'province' => 'Laguna', 'municipality' => 'Majayjay',
            'barangay' => 'Poblacion', 'street_address' => 'Test street',
            'id_document' => UploadedFile::fake()->image('id.png'),
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $buyer = User::where('email', 'new-buyer@example.test')->firstOrFail();
        $this->assertSame('pending', $buyer->status);
        Storage::disk('private')->assertExists($buyer->id_path);
        $this->assertFileDoesNotExist(storage_path('app/public/'.$buyer->id_path));

        $this->post(route('login.post'), ['email' => $buyer->email, 'password' => 'password123'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
        $this->assertGuest();

        $admin = $this->user('admin', 'approved');
        $this->actingAs($admin)->post(route('admin.registrations.approve', $buyer))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $buyer->id, 'status' => 'approved']);
        $this->post(route('logout'))->assertRedirect();
        $this->post(route('login.post'), ['email' => $buyer->email, 'password' => 'password123'])
            ->assertRedirect(route('buyer.dashboard'));
        $this->assertAuthenticatedAs($buyer->fresh());
    }

    public function test_inactive_sessions_are_logged_out_and_rejected_users_cannot_be_reactivated(): void
    {
        $buyer = $this->user('buyer', 'suspended');
        $this->actingAs($buyer)->get(route('buyer.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $admin = $this->user('admin', 'approved');
        $rejected = $this->user('seller', 'rejected');
        $this->actingAs($admin)->post(route('admin.moderation.reactivate', $rejected))->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $rejected->id, 'status' => 'rejected']);
    }

    public function test_buyer_can_cancel_before_shipment_and_inventory_restores_only_once(): void
    {
        $buyer = $this->user('buyer', 'approved');
        $seller = $this->user('seller', 'approved');
        $product = Product::create([
            'user_id' => $seller->id, 'name' => 'Test item', 'description' => 'Item',
            'category' => 'Test', 'price' => 10, 'stock' => 2, 'is_archived' => false,
        ]);
        $order = $this->order(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'product_name' => 'Test item', 'unit_price' => 10, 'quantity' => 3, 'item_total' => 30,
        ]);

        $this->actingAs($buyer)->post(route('buyer.orders.cancel', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'CANCELLED']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'cancelled']);
        $this->post(route('buyer.orders.cancel', $order))->assertUnprocessable();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
    }

    public function test_seller_confirmation_and_preparation_are_separate_locked_transitions(): void
    {
        $order = $this->order();
        $seller = $order->seller;

        $this->actingAs($seller)->get(route('seller.orders.index'))->assertOk();
        $this->get(route('seller.orders.show', $order))->assertOk();
        $this->actingAs($seller)->patch(route('seller.orders.updateStatus', $order), ['status' => 'CONFIRMED'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'CONFIRMED']);
        $this->patch(route('seller.orders.updateStatus', $order), ['status' => 'PREPARING'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PREPARING']);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'confirmed']);
    }

    public function test_seller_schedules_pickup_before_logistics_can_assign_a_rider(): void
    {
        $order = $this->order(['status' => 'READY_FOR_PICKUP']);
        $seller = $order->seller;
        $scheduledFor = now()->addDay()->format('Y-m-d\\TH:i');

        $this->actingAs($seller)->post(route('seller.orders.schedulePickup', $order), [
            'pickup_scheduled_for' => $scheduledFor,
            'pickup_window' => 'Morning (8 AM–12 PM)',
            'pickup_notes' => 'Use the rear loading entrance.',
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'pickup_window' => 'Morning (8 AM–12 PM)',
            'pickup_notes' => 'Use the rear loading entrance.',
        ]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'pickup_requested']);
        $this->actingAs($this->user('sorting_center', 'approved'))
            ->get(route('logistics.pickupRequests'))->assertOk()->assertSee($order->order_number);

        $otherSeller = $this->user('seller', 'approved');
        $this->actingAs($otherSeller)->post(route('seller.orders.schedulePickup', $order), [
            'pickup_scheduled_for' => $scheduledFor,
            'pickup_window' => 'Morning',
        ])->assertForbidden();
        $this->actingAs($seller)->post(route('seller.orders.schedulePickup', $order), [
            'pickup_scheduled_for' => $scheduledFor,
            'pickup_window' => 'Morning',
        ])->assertUnprocessable();
    }

    public function test_sensitive_documents_are_private_and_logistics_access_is_rider_scoped(): void
    {
        Storage::fake('private');
        $rider = $this->user('courier', 'pending', ['id_path' => 'documents/ids/rider.png']);
        Storage::disk('private')->put($rider->id_path, 'private bytes');
        $center = $this->user('sorting_center', 'approved');
        $buyer = $this->user('buyer', 'approved', ['id_path' => 'documents/ids/buyer.png']);
        Storage::disk('private')->put($buyer->id_path, 'private bytes');

        $this->actingAs($center)->get(route('logistics.riders.documents.show', [$rider, 'identity']))->assertOk();
        $this->get(route('logistics.riders.documents.show', [$buyer, 'identity']))->assertForbidden();
        $this->get(route('logistics.riders.documents.show', [$rider, 'unknown']))->assertNotFound();
    }

    public function test_logistics_enforces_rider_capacity_and_delivery_attempt_limit(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $rider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $activeOrder = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $rider->id]);
        $dispatchOrder = $this->order(['status' => 'SORTED', 'delivery_area' => 'Majayjay']);
        config()->set('logistics.maximum_active_deliveries_per_rider', 1);
        $this->actingAs($center)->post(route('logistics.orders.assignRider', $dispatchOrder), ['delivery_courier_id' => $rider->id])->assertUnprocessable();

        config()->set('logistics.maximum_delivery_attempts', 1);
        $this->actingAs($rider)->patch(route('courier.orders.failDelivery', $activeOrder), ['failure_reason' => 'recipient_unavailable'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $activeOrder->id, 'status' => 'RETURN_IN_TRANSIT']);
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $activeOrder->id, 'attempt_no' => 1, 'outcome' => 'failed']);
    }

    public function test_rider_area_assignments_are_structured_and_area_filtered(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $rider = $this->user('courier', 'approved');
        $primaryArea = $this->area('Laguna', 'Majayjay');
        $secondaryArea = $this->area('Laguna', 'Calamba');

        $this->actingAs($center)->patch(route('logistics.riders.areas.update', $rider), [
            'area_ids' => [$primaryArea->id, $secondaryArea->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('area_user', ['user_id' => $rider->id, 'area_id' => $primaryArea->id, 'is_primary' => true, 'is_active' => true]);
        $this->assertDatabaseHas('area_user', ['user_id' => $rider->id, 'area_id' => $secondaryArea->id, 'is_primary' => false, 'is_active' => true]);
    }

    private function user(string $role, string $status, array $extra = []): User
    {
        $user = User::create(array_merge([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => $role,
            'status' => $status,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'sex' => 'Other',
            'contact_no' => '09123456789',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
            'street_address' => '1 Test Street',
        ], $extra));

        if ($role === 'courier' && filled($extra['assigned_area'] ?? null)) {
            $area = $this->area((string) $user->province, (string) $extra['assigned_area']);
            $user->serviceAreas()->attach($area->id, ['is_primary' => true, 'is_active' => true]);
        }

        return $user;
    }

    private function order(array $extra = []): Order
    {
        $buyer = $this->user('buyer', 'approved');
        $seller = $this->user('seller', 'approved');
        $area = $this->area('Laguna', 'Majayjay');

        return Order::create(array_merge([
            'order_number' => 'EZC-'.strtoupper(fake()->unique()->bothify('??????????')),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'recipient_name' => 'Recipient Test',
            'recipient_contact' => '09123456789',
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'destination_area_id' => $area->id,
            'barangay' => 'Poblacion',
            'street_address' => '2 Test Street',
            'subtotal' => 100,
            'shipping_fee' => 50,
            'commission_fee' => 10,
            'total_amount' => 150,
            'payment_method' => 'GCash',
            'status' => 'PLACED',
        ], $extra));
    }

    private function area(string $province, string $municipality): Area
    {
        $normalize = fn (string $value): string => mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
        $provinceNormalized = $normalize($province);
        $municipalityNormalized = $normalize($municipality);
        $mapping = AreaMunicipality::query()
            ->where('province_normalized', $provinceNormalized)
            ->where('municipality_normalized', $municipalityNormalized)
            ->first();

        if ($mapping !== null) {
            return $mapping->area;
        }

        $area = Area::query()->create([
            'name' => trim($municipality),
            'code' => strtolower(str_replace(' ', '-', $province.'-'.$municipality)),
            'is_active' => true,
        ]);

        return AreaMunicipality::query()->create([
            'area_id' => $area->id,
            'province' => $province,
            'municipality' => trim($municipality),
            'province_normalized' => $provinceNormalized,
            'municipality_normalized' => $municipalityNormalized,
        ])->area;
    }
}
