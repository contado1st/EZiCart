<?php

namespace App\Services;

use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DirectMessagingService
{
    public const ROLES = ['buyer', 'seller', 'courier', 'sorting_center', 'admin', 'ecommerce_admin', 'logistics_admin'];

    public function __construct(private TransactionAwareNotificationSender $notifications) {}

    public function contacts(User $actor): Builder
    {
        abort_unless($actor->status === 'approved' && in_array($actor->role, self::ROLES, true), 403);

        return User::query()->where('status', 'approved')->whereKeyNot($actor->id)->whereIn('role', self::ROLES)
            ->where(function (Builder $contacts) use ($actor) {
                $contacts->whereRaw('1 = 0');
                $adminRoles = ['admin', 'ecommerce_admin', 'logistics_admin'];
                if ($actor->isCommerceAdmin() || $actor->isLogisticsAdmin()) {
                    $contacts->orWhereIn('role', array_merge($actor->managedAccountRoles(), $adminRoles));
                } else {
                    $contacts->orWhereIn('role', in_array($actor->role, ['buyer', 'seller'], true) ? ['admin', 'ecommerce_admin'] : ['logistics_admin']);
                }
                if ($actor->role === 'buyer') {
                    $contacts->orWhere('role', 'seller');
                }
                // Sellers may reply to buyers who initiated a pre-sale conversation.
                if ($actor->role === 'seller') {
                    $contacts->orWhere(fn ($q) => $q->where('role', 'buyer')->whereExists(function ($pairs) use ($actor) {
                        $pairs->selectRaw('1')->from('direct_conversations')->where(fn ($p) => $p
                            ->where(fn ($p) => $p->where('first_user_id', $actor->id)->whereColumn('second_user_id', 'users.id'))
                            ->orWhere(fn ($p) => $p->where('second_user_id', $actor->id)->whereColumn('first_user_id', 'users.id')));
                    }));
                }
                $contacts->orWhereExists(function ($orders) use ($actor) {
                    $orders->selectRaw('1')->from('orders')
                        ->where(function ($q) use ($actor) {
                            foreach (['buyer_id', 'seller_id', 'pickup_courier_id', 'delivery_courier_id', 'sorting_center_id'] as $column) {
                                $q->orWhere($column, $actor->id);
                            }
                            if ($actor->role === 'sorting_center') {
                                $q->orWhereExists(fn ($events) => $events->selectRaw('1')->from('parcel_tracking_events')->whereColumn('order_id', 'orders.id')->where('actor_id', $actor->id));
                            }
                        })->where(function ($q) {
                            foreach (['buyer_id', 'seller_id', 'pickup_courier_id', 'delivery_courier_id', 'sorting_center_id'] as $column) {
                                $q->orWhereColumn('orders.'.$column, 'users.id');
                            }
                            $q->orWhere(fn ($centers) => $centers->where('users.role', 'sorting_center')->whereExists(fn ($events) => $events->selectRaw('1')->from('parcel_tracking_events')->whereColumn('order_id', 'orders.id')->whereColumn('actor_id', 'users.id')));
                        });
                });
                if ($actor->role === 'courier') {
                    $contacts->orWhere(fn ($q) => $q->where('role', 'sorting_center')->whereIn('id', $actor->serviceAreas()->select('areas.sorting_center_id')));
                } elseif ($actor->role === 'sorting_center') {
                    $contacts->orWhere(fn ($q) => $q->where('role', 'courier')->whereHas('serviceAreas', fn ($a) => $a->where('areas.sorting_center_id', $actor->id)));
                }
            });
    }

    public function canContact(User $actor, User $recipient): bool
    {
        return $this->contacts($actor)->whereKey($recipient->id)->exists();
    }

    public function authorizeOrder(Order $order, User $actor): void
    {
        abort_unless($actor->isCommerceAdmin() || $actor->isLogisticsAdmin() || $order->messageParticipants()->contains('id', $actor->id), 403);
    }

    public function start(User $actor, User $recipient): DirectConversation
    {
        return DB::transaction(function () use ($actor, $recipient) {
            $users = User::query()->whereKey([$actor->id, $recipient->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($this->canContact($users[$actor->id], $users[$recipient->id]), 403);

            return DirectConversation::firstOrCreate(['first_user_id' => min($actor->id, $recipient->id), 'second_user_id' => max($actor->id, $recipient->id)]);
        });
    }

    public function send(DirectConversation $conversation, User $actor, string $body, ?Order $order = null): DirectMessage
    {
        abort_unless($conversation->hasParticipant($actor), 403);

        return DB::transaction(function () use ($conversation, $actor, $body, $order) {
            $recipient = $conversation->otherUser($actor);
            $users = User::query()->whereKey([$actor->id, $recipient->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($this->canContact($users[$actor->id], $users[$recipient->id]), 403);
            if ($order) {
                $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                $this->authorizeOrder($order, $actor);
                $this->authorizeOrder($order, $recipient);
            }
            $conversation = DirectConversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $message = $conversation->messages()->create(['sender_id' => $actor->id, 'recipient_id' => $recipient->id, 'body' => trim($body), 'order_id' => $order?->id]);
            $conversation->touch();
            $this->notifications->send($recipient, new DirectMessageNotification($message, trim($actor->first_name.' '.$actor->last_name)));

            return $message;
        });
    }
}
