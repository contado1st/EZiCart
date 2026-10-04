<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderConversation;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\OrderMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_only_participants_on_an_order_can_exchange_private_messages(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $order = $this->order($buyer, $seller);
        $otherBuyer = $this->user('buyer');
        $rider = $this->user('courier');

        $this->actingAsUser($buyer)->get(route('buyer.orders.messages.show', $order))->assertOk()->assertSee('No messages yet');
        $this->post(route('buyer.orders.messages.store', $order), ['body' => '<script>alert(1)</script> Please confirm the order details.'])
            ->assertRedirect()->assertSessionHas('success');

        $conversation = OrderConversation::query()->where('order_id', $order->id)->firstOrFail();
        $firstMessage = $conversation->messages()->firstOrFail();
        $this->assertSame($buyer->id, $firstMessage->sender_id);
        $this->assertSame(1, OrderConversation::query()->where('order_id', $order->id)->count());
        $sellerNotification = $seller->notifications()->firstOrFail();
        $this->assertSame($order->order_number, $sellerNotification->data['order_number']);
        $this->assertStringNotContainsString('script', $sellerNotification->data['message']);

        $this->actingAsUser($seller)->get(route('seller.orders.messages.show', $order))
            ->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertNotNull($firstMessage->fresh()->read_at);

        $this->post(route('seller.orders.messages.store', $order), ['body' => 'Thanks, the order is confirmed.'])->assertRedirect();
        $this->actingAsUser($buyer)->get(route('buyer.orders.messages.show', $order))->assertOk()->assertSee('Thanks, the order is confirmed.');
        $this->assertNotNull($conversation->messages()->latest('id')->firstOrFail()->read_at);

        $this->actingAsUser($otherBuyer)->get(route('buyer.orders.messages.show', $order))->assertForbidden();
        $this->post(route('buyer.orders.messages.store', $order), ['body' => 'Unauthorized'])->assertForbidden();
        $this->actingAsUser($rider)->post(route('buyer.orders.messages.store', $order), ['body' => 'Unassigned couriers cannot read this thread.'])->assertForbidden();
    }

    public function test_assigned_courier_and_involved_logistics_user_can_coordinate_in_the_order_thread(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $courier = $this->user('courier');
        $center = $this->user('sorting_center');
        $admin = $this->user('admin');
        $order = $this->order($buyer, $seller);
        $order->forceFill([
            'pickup_courier_id' => $courier->id,
            'sorting_center_id' => $center->id,
        ])->save();

        $this->actingAsUser($courier)->get(route('courier.orders.messages.show', $order))->assertOk();
        $this->post(route('courier.orders.messages.store', $order), ['body' => 'I have arrived for pickup.'])->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $buyer->id,
            'type' => OrderMessageNotification::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $seller->id,
            'type' => OrderMessageNotification::class,
        ]);
        $centerNotification = $center->notifications()->firstOrFail();
        $this->assertSame(route('logistics.orders.messages.show', $order), $centerNotification->data['url']);

        $this->actingAsUser($center)->get(route('logistics.orders.messages.show', $order))
            ->assertOk()->assertSee('I have arrived for pickup.');

        $order->forceFill(['pickup_courier_id' => null])->save();
        ParcelTrackingEvent::query()->create([
            'order_id' => $order->id,
            'actor_id' => $courier->id,
            'event_type' => 'pickup_declined',
            'status' => $order->status,
            'notes' => 'Rider declined the pickup.',
        ]);
        $this->actingAsUser($courier)->get(route('courier.orders.messages.show', $order))->assertForbidden();

        $unrelatedCenter = $this->user('sorting_center');
        $this->actingAsUser($unrelatedCenter)->get(route('logistics.orders.messages.show', $order))->assertForbidden();

        $this->actingAsUser($admin)->get(route('admin.orders.messages.show', $order))->assertOk();
        $this->post(route('admin.orders.messages.store', $order), ['body' => 'Support is reviewing the delivery update.'])->assertRedirect();
        $this->actingAsUser($seller)->get(route('seller.orders.messages.show', $order))
            ->assertOk()->assertSee('Support is reviewing the delivery update.');
    }

    private function actingAsUser(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function user(string $role): User
    {
        return User::query()->forceCreate([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => $role,
            'status' => 'approved',
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
        ]);
    }

    private function order(User $buyer, User $seller): Order
    {
        return Order::query()->forceCreate([
            'order_number' => 'EZC-'.strtoupper(fake()->unique()->bothify('??????????')),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'recipient_name' => 'Message Test Buyer',
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
        ]);
    }
}
