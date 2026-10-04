<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $order = $this->order(['status' => 'READY_FOR_PICKUP']);

        $this->actingAs($courier)->post(route('courier.orders.claim', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'pickup_courier_id' => $courier->id, 'status' => 'READY_FOR_PICKUP']);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'pickup_claimed']);

        $this->actingAs($otherCourier)->post(route('courier.orders.claim', $order))->assertUnprocessable();
        $this->actingAs($courier)->post(route('courier.orders.confirmPickup', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PICKED_UP']);

        $this->actingAs($center)->post(route('logistics.orders.receive', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $center->id]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'hub_received']);
    }

    public function test_sorting_and_dispatch_require_destination_and_eligible_rider(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $order = $this->order(['status' => 'AT_SORTING_CENTER']);

        $this->actingAs($center)->post(route('logistics.orders.sort', $order), ['delivery_area' => 'Wrong area'])->assertUnprocessable();
        $this->actingAs($center)->post(route('logistics.orders.sort', $order), ['delivery_area' => 'Majayjay'])->assertRedirect();
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
        $firstRider = $this->user('courier', 'approved');
        $nextRider = $this->user('courier', 'approved');

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
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'RETURNED', 'delivery_courier_id' => null]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'returned']);
    }

    public function test_logistics_workspace_pages_render_with_live_records(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $this->order(['status' => 'AT_SORTING_CENTER']);

        $this->actingAs($center)->get(route('logistics.dashboard'))->assertOk();
        $this->get(route('logistics.intake'))->assertOk();
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

    private function user(string $role, string $status, array $extra = []): User
    {
        return User::create(array_merge([
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
    }

    private function order(array $extra = []): Order
    {
        $buyer = $this->user('buyer', 'approved');
        $seller = $this->user('seller', 'approved');

        return Order::create(array_merge([
            'order_number' => 'EZC-'.strtoupper(fake()->unique()->bothify('??????????')),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'recipient_name' => 'Recipient Test',
            'recipient_contact' => '09123456789',
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
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
}
