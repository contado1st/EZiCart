<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderMessageNotification;
use App\Notifications\OrderStatusNotification;
use App\Services\DirectMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunicationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_buyer_seller_and_assigned_rider_have_a_private_searchable_inbox(): void
    {
        $order = $this->order();
        $rider = $this->user('courier');
        $order->forceFill(['delivery_courier_id' => $rider->id])->save();
        $conversation = app(DirectMessagingService::class)->start($order->buyer, $order->seller);
        foreach ([$order->buyer, $order->seller] as $actor) {
            $this->signIn($actor)->get(route('chat.index'))->assertOk()->assertViewHas('threads', fn ($threads) => $threads->total() === 1)->assertSee('All conversations');
            $this->get(route('chat.index', ['q' => 'unmatched-name']))->assertOk()->assertViewHas('threads', fn ($threads) => $threads->total() === 0);
        }
        $this->signIn($rider)->get(route('chat.index'))->assertOk()->assertViewHas('threads', fn ($threads) => $threads->total() === 0);
        $this->get(route('chat.show', $conversation))->assertForbidden();
    }

    public function test_conversation_preview_is_latest_message_and_history_is_paginated_and_escaped(): void
    {
        $order = $this->order();
        $conversation = app(DirectMessagingService::class)->start($order->buyer, $order->seller);
        foreach (range(1, 42) as $number) {
            $conversation->messages()->create(['sender_id' => $order->seller_id, 'recipient_id' => $order->buyer_id, 'body' => 'Message number '.$number]);
        }
        $this->signIn($order->buyer)->get(route('chat.show', $conversation))->assertOk()
            ->assertViewHas('messages', fn ($messages) => $messages->total() === 42 && $messages->count() === 40 && $messages->first()->body === 'Message number 3' && $messages->last()->body === 'Message number 42');
        $this->get(route('chat.show', [$conversation, 'messages_page' => 2]))->assertOk()->assertViewHas('messages', fn ($messages) => $messages->count() === 2);
        $this->get(route('chat.index'))->assertOk()->assertViewHas('threads', fn ($threads) => $threads->first()->latestMessage->body === 'Message number 42');
        $this->post(route('chat.store', $conversation), ['body' => '<script>unsafe()</script>'])->assertRedirectToRoute('chat.show', $conversation);
        $this->get(route('chat.show', $conversation))->assertOk()->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)->assertDontSee('<script>unsafe()</script>', false);
    }

    public function test_invalid_search_and_blank_messages_are_rejected(): void
    {
        $order = $this->order();
        $this->signIn($order->buyer)->getJson(route('chat.index', ['q' => ['invalid']]))->assertJsonValidationErrors('q');
        $this->postJson(route('buyer.orders.messages.store', $order), ['body' => '   '])->assertJsonValidationErrors('body');
    }

    public function test_notification_filters_keep_only_the_owners_matching_updates(): void
    {
        $buyer = $this->user('buyer');
        $this->notification($buyer, 'Private chat update', OrderMessageNotification::class);
        $read = $this->notification($buyer, 'Already read order update', OrderStatusNotification::class);
        $read->markAsRead();
        $this->notification($this->user('buyer'), 'Another account secret', OrderMessageNotification::class);
        $this->signIn($buyer)->get(route('notifications.index', ['filter' => 'unread']))->assertOk()->assertSee('Private chat update')->assertDontSee('Already read order update')->assertDontSee('Another account secret')->assertViewHas('unreadCount', 1);
        $this->get(route('notifications.index', ['category' => 'orders']))->assertOk()->assertSee('Already read order update')->assertDontSee('Private chat update');
        $this->getJson(route('notifications.index', ['category' => 'invalid']))->assertJsonValidationErrors('category');
    }

    public function test_mark_all_read_never_changes_another_accounts_notifications(): void
    {
        $buyer = $this->user('buyer');
        $other = $this->user('buyer');
        $mine = $this->notification($buyer, 'My update');
        $theirs = $this->notification($other, 'Other update');
        $this->signIn($buyer)->post(route('notifications.read-all'))->assertRedirect()->assertSessionHas('success');
        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
        $this->post(route('notifications.open', $theirs->id))->assertNotFound();
        $this->post(route('notifications.read', $theirs->id))->assertNotFound();
    }

    public function test_opening_an_owned_notification_marks_read_and_stays_on_this_application(): void
    {
        $buyer = $this->user('buyer');
        $valid = $this->notification($buyer, 'Own order update', OrderStatusNotification::class, route('buyer.dashboard'));
        $this->signIn($buyer)->post(route('notifications.open', $valid->id))->assertRedirect(route('buyer.dashboard', absolute: false));
        $this->assertNotNull($valid->fresh()->read_at);
        foreach (['https://outside.example/phish', 'javascript:alert(1)', '//outside.example/phish', '/\\outside.example/phish'] as $url) {
            $invalid = $this->notification($buyer, 'Invalid destination', OrderStatusNotification::class, $url);
            $this->post(route('notifications.open', $invalid->id))->assertRedirectToRoute('notifications.index');
        }
    }

    public function test_seller_and_rider_notifications_use_the_same_dedicated_frame(): void
    {
        foreach (['seller', 'courier'] as $role) {
            $actor = $this->user($role);
            $this->notification($actor, 'Role-specific update');
            $this->signIn($actor)->get(route('notifications.index'))->assertOk()->assertSee('notification-frame', false)->assertSee('Role-specific update')->assertSee('Mark all as read');
        }
    }

    public function test_communications_require_authentication(): void
    {
        $this->get(route('chat.index'))->assertRedirectToRoute('login');
        $this->get(route('notifications.index'))->assertRedirectToRoute('login');
        $this->post(route('notifications.read-all'))->assertRedirectToRoute('login');
        $this->get(route('communications.counts'))->assertRedirectToRoute('login');
    }

    public function test_unread_badges_count_each_recipients_alerts_and_clear_only_the_opened_thread(): void
    {
        $order = $this->order();
        $rider = $this->user('courier');
        $order->forceFill(['delivery_courier_id' => $rider->id])->save();
        $conversation = app(DirectMessagingService::class)->start($order->seller, $order->buyer);
        $this->signIn($order->seller)->post(route('chat.store', $conversation), ['body' => 'Please confirm the delivery details.'])->assertRedirect();
        $this->notification($order->buyer, 'An order status update');
        $this->signIn($order->buyer)->get(route('communications.counts'))->assertExactJson(['messages' => 1, 'notifications' => 2]);
        $this->get(route('chat.index'))->assertOk()->assertSee('aria-label="Messages, 1 unread"', false);
        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->get(route('communications.counts'))->assertExactJson(['messages' => 1, 'notifications' => 0]);
        $this->get(route('chat.show', [$conversation, 'panel' => 1]))->assertOk();
        $this->get(route('communications.counts'))->assertExactJson(['messages' => 0, 'notifications' => 0]);
        $this->signIn($rider)->get(route('communications.counts'))->assertExactJson(['messages' => 0, 'notifications' => 0]);
    }

    public function test_reading_one_conversation_keeps_other_message_badges_unread(): void
    {
        $order = $this->order();
        $other = $this->order();
        $other->forceFill(['buyer_id' => $order->buyer_id])->save();
        $service = app(DirectMessagingService::class);
        $first = $service->start($order->seller, $order->buyer);
        $second = $service->start($other->seller, $order->buyer);
        $this->signIn($order->seller)->post(route('chat.store', $first), ['body' => 'First order message.'])->assertRedirect();
        $this->signIn($other->seller)->post(route('chat.store', $second), ['body' => 'Other order message.'])->assertRedirect();
        $this->signIn($order->buyer)->get(route('communications.counts'))->assertExactJson(['messages' => 2, 'notifications' => 2]);
        $this->get(route('chat.show', $first))->assertOk();
        $this->get(route('communications.counts'))->assertExactJson(['messages' => 1, 'notifications' => 1]);
    }

    public function test_message_drawer_is_available_to_signed_in_users_and_loads_a_compact_inbox(): void
    {
        $order = $this->order();
        $conversation = app(DirectMessagingService::class)->start($order->buyer, $order->seller);
        $this->signIn($order->buyer)->get(route('home'))->assertOk()->assertSee('id="message-drawer"', false)->assertSee('data-message-drawer-toggle', false);
        $this->get(route('chat.index', ['panel' => 1]))->assertOk()->assertSee('communications-embedded', false)
            ->assertDontSee('workspace-topbar', false)->assertDontSee('id="message-drawer"', false)
            ->assertSee(route('chat.show', [$conversation, 'panel' => 1]));
        $this->get(route('chat.show', [$conversation, 'panel' => 1, 'order_id' => $order->id]))->assertOk()
            ->assertSee('name="panel" value="1"', false)->assertSee('target="_top"', false)->assertDontSee('workspace-topbar', false);
    }

    public function test_sending_in_the_drawer_preserves_compact_mode_and_existing_permissions(): void
    {
        $order = $this->order();
        $conversation = app(DirectMessagingService::class)->start($order->buyer, $order->seller);
        $this->signIn($order->buyer)->post(route('chat.store', $conversation), ['body' => 'Sent from the side panel.', 'panel' => 1])
            ->assertRedirectToRoute('chat.show', [$conversation, 'panel' => 1]);
        $this->assertDatabaseHas('direct_messages', ['sender_id' => $order->buyer_id, 'recipient_id' => $order->seller_id, 'body' => 'Sent from the side panel.']);
        $this->signIn($this->user('courier'))->get(route('chat.show', [$conversation, 'panel' => 1]))->assertForbidden();
    }

    private function signIn(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function notification(User $user, string $message, string $type = OrderStatusNotification::class, string $url = '')
    {
        return $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => $type, 'data' => ['message' => $message, 'url' => $url], 'read_at' => null]);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.com', 'password' => 'password', 'role' => $role, 'status' => 'approved', 'first_name' => 'Connect', 'last_name' => 'User', 'business_name' => 'Connect Shop', 'contact_no' => '09123456789']);
    }

    private function order(): Order
    {
        return Order::forceCreate(['order_number' => 'CHAT-'.Str::random(12), 'buyer_id' => $this->user('buyer')->id, 'seller_id' => $this->user('seller')->id, 'recipient_name' => 'Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Street', 'subtotal' => 100, 'total_amount' => 150, 'payment_method' => 'COD', 'status' => 'PLACED']);
    }
}
