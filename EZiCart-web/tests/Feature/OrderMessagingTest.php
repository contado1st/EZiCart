<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DirectConversation;
use App\Models\Order;
use App\Models\OrderConversation;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        $this->actingAsUser($buyer)->get(route('buyer.orders.messages.show', $order))->assertOk()->assertSee('Choose a contact');
        $this->postJson(route('buyer.orders.messages.store', $order), ['body' => 'Missing recipient'])->assertJsonValidationErrors('recipient_id');
        $this->post(route('buyer.orders.messages.store', $order), ['recipient_id' => $seller->id, 'body' => '<script>alert(1)</script> Please confirm.'])->assertRedirect();
        $conversation = DirectConversation::firstOrFail();
        $message = $conversation->messages()->firstOrFail();
        $this->assertSame($seller->id, $message->recipient_id);
        $this->assertSame(1, $seller->notifications()->count());
        $this->actingAsUser($seller)->get(route('chat.show', $conversation))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertNotNull($message->fresh()->read_at);
        $this->post(route('chat.store', $conversation), ['body' => 'Order confirmed.'])->assertRedirect();
        $this->actingAsUser($buyer)->get(route('chat.show', $conversation))->assertOk()->assertSee('Order confirmed.');
        foreach (['buyer', 'courier', 'admin', 'logistics_admin', 'sorting_center'] as $role) {
            $this->actingAsUser($this->user($role))->get(route('chat.show', $conversation))->assertForbidden();
            $this->post(route('chat.store', $conversation), ['body' => 'Unauthorized'])->assertForbidden();
        }
    }

    public function test_assigned_courier_and_involved_logistics_user_can_coordinate_in_the_order_thread(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $rider = $this->user('courier');
        $center = $this->user('sorting_center');
        $order = $this->order($buyer, $seller);
        $order->forceFill(['pickup_courier_id' => $rider->id, 'sorting_center_id' => $center->id])->save();
        $this->actingAsUser($rider)->post(route('courier.orders.messages.store', $order), ['recipient_id' => $seller->id, 'body' => 'Private pickup details.'])->assertRedirect();
        $private = DirectConversation::firstOrFail();
        $this->assertSame(0, $buyer->notifications()->count());
        $this->assertSame(0, $center->notifications()->count());
        $this->assertSame(1, $seller->notifications()->where('type', DirectMessageNotification::class)->count());
        $this->actingAsUser($center)->get(route('chat.show', $private))->assertForbidden();
        $this->post(route('logistics.orders.messages.store', $order), ['recipient_id' => $rider->id, 'body' => 'Private hub update.'])->assertRedirect();
        $this->assertSame(2, DirectConversation::count());
        $this->actingAsUser($seller)->get(route('chat.show', $private))->assertOk()->assertDontSee('Private hub update.');
        $order->forceFill(['pickup_courier_id' => null])->save();
        $this->actingAsUser($rider)->get(route('chat.show', $private))->assertOk()->assertSee('Private pickup details.')->assertSee('Messaging is unavailable');
        $this->post(route('chat.store', $private), ['body' => 'No longer assigned'])->assertForbidden();
    }

    public function test_contact_policy_pair_reuse_and_inactive_accounts(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $rider = $this->user('courier');
        $admin = $this->user('ecommerce_admin');
        $logisticsAdmin = $this->user('logistics_admin');
        $this->actingAsUser($buyer)->get(route('chat.contacts'))->assertOk();
        $this->post(route('chat.start', $seller))->assertRedirect();
        $conversation = DirectConversation::firstOrFail();
        $this->actingAsUser($seller)->post(route('chat.start', $buyer))->assertRedirectToRoute('chat.show', $conversation);
        $this->assertSame(1, DirectConversation::count());
        $this->actingAsUser($buyer)->post(route('chat.start', $buyer))->assertForbidden();
        $this->post(route('chat.start', $rider))->assertForbidden();
        $this->post(route('chat.start', $logisticsAdmin))->assertForbidden();
        $this->post(route('chat.start', $admin))->assertRedirect();
        $this->actingAsUser($rider)->post(route('chat.start', $logisticsAdmin))->assertRedirect();
        $this->post(route('chat.start', $admin))->assertForbidden();
        $seller->forceFill(['status' => 'pending'])->save();
        $this->actingAsUser($buyer)->post(route('chat.store', $conversation), ['body' => 'Inactive recipient'])->assertForbidden();
        $this->get(route('chat.show', $conversation))->assertOk()->assertSee('Messaging is unavailable');
    }

    public function test_seller_and_commerce_admin_can_use_the_embedded_conversation_with_tab_session_support(): void
    {
        Notification::fake();
        $seller = $this->user('seller');
        $admin = $this->user('admin');
        $this->actingAsUser($seller)->post(route('chat.start', $admin), ['panel' => 1])->assertRedirect();
        $conversation = DirectConversation::firstOrFail();

        $this->get(route('chat.show', [$conversation, 'panel' => 1]))->assertOk()
            ->assertSee('communications-embedded', false)
            ->assertSee(asset('js/tab-sessions.js'), false)
            ->assertSee('Send message');
        $this->post(route('chat.store', $conversation), ['panel' => 1, 'body' => 'Seller asks the shop administrator.'])
            ->assertRedirectToRoute('chat.show', [$conversation, 'panel' => 1]);
        Notification::assertSentTo($admin, DirectMessageNotification::class);

        $this->actingAsUser($admin)->get(route('chat.show', [$conversation, 'panel' => 1]))->assertOk()
            ->assertSee('Seller asks the shop administrator.')->assertSee(asset('js/tab-sessions.js'), false);
        $this->post(route('chat.store', $conversation), ['panel' => 1, 'body' => 'Shop administrator replies privately.'])
            ->assertRedirectToRoute('chat.show', [$conversation, 'panel' => 1]);
        Notification::assertSentTo($seller, DirectMessageNotification::class);
        $this->actingAsUser($seller)->get(route('chat.show', [$conversation, 'panel' => 1]))->assertOk()
            ->assertSee('Shop administrator replies privately.');

        $this->actingAsUser($this->user('buyer'))->get(route('chat.show', [$conversation, 'panel' => 1]))->assertForbidden();
        $this->assertSame(2, $conversation->messages()->count());
    }

    public function test_legacy_group_history_is_preserved_without_being_exposed_or_broadcast(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $order = $this->order($buyer, $seller);
        $legacy = OrderConversation::create(['order_id' => $order->id]);
        $legacy->messages()->create(['sender_id' => $buyer->id, 'body' => 'Legacy shared secret.']);
        $this->actingAsUser($buyer)->get(route('buyer.orders.messages.show', $order))->assertOk()->assertDontSee('Legacy shared secret.');
        $this->post(route('chat.start', $seller))->assertRedirect();
        $conversation = DirectConversation::firstOrFail();
        $this->get(route('chat.show', $conversation))->assertOk()->assertDontSee('Legacy shared secret.');
        $this->assertDatabaseHas('order_messages', ['body' => 'Legacy shared secret.']);
        $unrelated = $this->order($this->user('buyer'), $this->user('seller'));
        $this->post(route('chat.store', $conversation), ['body' => 'Forged order', 'order_id' => $unrelated->id])->assertForbidden();
        $this->assertSame(0, $conversation->messages()->count());
        $this->postJson(route('chat.store', $conversation), ['body' => str_repeat('a', 2001)])->assertJsonValidationErrors('body');
    }

    public function test_service_area_riders_and_previous_sorting_centers_remain_related_contacts(): void
    {
        $center = $this->user('sorting_center');
        $rider = $this->user('courier');
        $area = Area::create(['name' => 'Test service area', 'code' => 'DM-AREA', 'is_active' => true, 'sorting_center_id' => $center->id]);
        $rider->serviceAreas()->attach($area->id, ['is_active' => true, 'is_primary' => true]);
        $this->actingAsUser($center)->post(route('chat.start', $rider))->assertRedirect();
        $pair = DirectConversation::firstOrFail();
        $this->actingAsUser($rider)->post(route('chat.start', $center))->assertRedirectToRoute('chat.show', $pair);
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $order = $this->order($buyer, $seller);
        ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $center->id, 'event_type' => 'received', 'status' => $order->status]);
        $this->actingAsUser($buyer)->post(route('chat.start', $center))->assertRedirect();
        $this->actingAsUser($center)->post(route('chat.start', $seller))->assertRedirect();
        $this->post(route('chat.start', $this->user('buyer')))->assertForbidden();
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
