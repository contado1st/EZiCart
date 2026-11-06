<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\StaffProfileRevision;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowAttentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pickup_next_action_moves_between_seller_logistics_and_rider_and_clears_completed_badges(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $rider = $this->user('courier');
        $hub = $this->user('sorting_center');
        $order = $this->order($buyer, $seller);
        $this->signIn($seller)->getJson(route('workflow.attention', ['order_ids' => [$order->id]]))->assertOk()->assertJsonPath('count', 1)->assertJsonPath('items.0.target', route('seller.orders.schedulePickup', $order))->assertJsonPath('progress.'.$order->id.'.requires_action', true);
        $this->signIn($buyer)->getJson(route('workflow.attention', ['order_ids' => [$order->id]]))->assertOk()->assertJsonPath('count', 0)->assertJsonPath('progress.'.$order->id.'.requires_action', false);
        $order->forceFill(['pickup_requested_at' => now()])->save();
        $this->signIn($hub)->getJson(route('workflow.attention'))->assertOk()->assertJsonPath('count', 1)->assertJsonPath('items.0.target', route('logistics.orders.assignPickup', $order));
        $order->forceFill(['pickup_courier_id' => $rider->id, 'pickup_claimed_at' => now(), 'pickup_arrived_at' => now()])->save();
        $this->signIn($seller)->getJson(route('workflow.attention'))->assertJsonPath('count', 1)->assertJsonPath('items.0.target', route('seller.orders.confirmHandover', $order));
        $this->signIn($rider)->getJson(route('workflow.attention', ['order_ids' => [$order->id]]))->assertJsonPath('count', 0)->assertJsonPath('progress.'.$order->id.'.owner', 'seller');
        $order->forceFill(['seller_handover_at' => now()])->save();
        $this->signIn($seller)->getJson(route('workflow.attention'))->assertJsonPath('count', 0);
        $this->signIn($rider)->getJson(route('workflow.attention'))->assertJsonPath('count', 1)->assertJsonPath('items.0.target', route('courier.orders.confirmPickup', $order));
        $order->forceFill(['status' => 'PICKED_UP', 'picked_up_at' => now()])->save();
        $this->getJson(route('workflow.attention'))->assertJsonPath('count', 1)->assertJsonPath('items.0.target', null);
        $this->signIn($hub)->getJson(route('workflow.attention'))->assertJsonPath('count', 1);
        $order->forceFill(['status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $hub->id, 'received_at' => now()])->save();
        $this->signIn($rider)->getJson(route('workflow.attention'))->assertJsonPath('count', 0);
    }

    public function test_workflow_snapshots_and_counts_are_scoped_to_the_account_and_hub(): void
    {
        $owner = $this->user('buyer');
        $seller = $this->user('seller');
        $order = $this->order($owner, $seller);
        foreach (['buyer', 'seller', 'courier'] as $role) {
            $this->signIn($this->user($role))->getJson(route('workflow.attention', ['order_ids' => [$order->id]]))->assertOk()->assertJsonPath('count', 0)->assertJsonCount(0, 'progress');
        }
        $hub = $this->user('sorting_center');
        $other = $this->user('sorting_center');
        $order->forceFill(['sorting_center_id' => $hub->id, 'status' => 'AT_SORTING_CENTER'])->save();
        $this->signIn($other)->getJson(route('workflow.attention', ['order_ids' => [$order->id]]))->assertJsonPath('count', 0)->assertJsonCount(0, 'progress');
        $this->signIn($hub)->getJson(route('workflow.attention'))->assertJsonPath('count', 1);
    }

    public function test_admin_modules_receive_only_their_own_approval_tasks(): void
    {
        $seller = $this->user('seller');
        $rider = $this->user('courier');
        foreach ([$seller, $rider] as $subject) {
            StaffProfileRevision::create(['user_id' => $subject->id, 'submitted_by' => $subject->id, 'pending_user_id' => $subject->id, 'previous_values' => ['first_name' => $subject->first_name], 'proposed_values' => ['first_name' => 'Requested']]);
        }
        $this->signIn($this->user('ecommerce_admin'))->getJson(route('workflow.attention'))->assertOk()->assertJsonPath('count', 1)->assertJsonPath('items.0.url', route('admin.profile-reviews.index'));
        $this->signIn($this->user('logistics_admin'))->getJson(route('workflow.attention'))->assertOk()->assertJsonPath('count', 1)->assertJsonPath('items.0.url', route('logistics-admin.profile-reviews.index'));
    }

    public function test_order_notifications_include_next_step_and_the_responsible_role(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $order = $this->order($buyer, $seller, ['pickup_requested_at' => now(), 'pickup_courier_id' => $this->user('courier')->id, 'pickup_claimed_at' => now(), 'pickup_arrived_at' => now()]);
        $notification = new OrderWorkflowNotification($order, 'pickup_arrived', 'The rider arrived.');
        $data = $notification->toDatabase($seller);
        $this->assertTrue($data['requires_action']);
        $this->assertSame('seller', $data['action_owner']);
        $this->assertStringContainsString('confirm parcel handover', $data['next_step']);
        $this->assertFalse($notification->toDatabase($buyer)['requires_action']);
    }

    public function test_tricycle_and_four_wheel_vehicle_choices_still_require_profile_approval(): void
    {
        $this->withoutVite();
        $rider = $this->user('courier');
        $this->signIn($rider)->get(route('account.profile.edit'))->assertOk()->assertSee('Tricycle')->assertSee('Light truck');
        $this->patch(route('account.profile.update'), ['first_name' => $rider->first_name, 'last_name' => $rider->last_name, 'contact_no' => $rider->contact_no, 'vehicle_type' => 'Tricycle', 'vehicle_category' => 'two_wheel'])->assertRedirect()->assertSessionHasNoErrors();
        $revision = StaffProfileRevision::firstOrFail();
        $this->assertSame('three_wheel', $revision->proposed_values['vehicle_category']);
        $this->assertNull($rider->fresh()->vehicle_type);
        $this->signIn($this->user('logistics_admin'))->patch(route('logistics-admin.profile-reviews.review', $revision), ['decision' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Tricycle', $rider->fresh()->vehicle_type);
        $this->assertSame('three_wheel', $rider->fresh()->vehicle_category);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['first_name' => 'Test', 'last_name' => 'Workflow', 'email' => fake()->unique()->safeEmail(), 'contact_no' => '09'.fake()->unique()->numerify('#########'), 'password' => 'Password123!', 'status' => 'approved', 'vehicle_capacity_grams' => $role === 'courier' ? 100000 : null, 'vehicle_capacity_verified_at' => $role === 'courier' ? now() : null, 'role' => $role]);
    }

    private function order(User $buyer, User $seller, array $attributes = []): Order
    {
        return Order::forceCreate(array_merge(['order_number' => 'FLOW-'.fake()->unique()->numerify('######'), 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'recipient_name' => 'Test Buyer', 'recipient_contact' => $buyer->contact_no, 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Roque', 'street_address' => '1 Test Street', 'subtotal' => 100, 'total_amount' => 150, 'parcel_weight_grams' => 1000, 'payment_method' => 'COD', 'status' => 'READY_FOR_PICKUP'], $attributes));
    }
}
