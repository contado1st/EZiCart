<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MessagingInboxService
{
    public function threads(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $actor = $request->user();
        $query = Order::query();
        if (! $actor->isCommerceAdmin() && ! $actor->isLogisticsAdmin()) {
            $query->where(function (Builder $orders) use ($actor) {
                match ($actor->role) {
                    'buyer' => $orders->where('buyer_id', $actor->id),
                    'seller' => $orders->where('seller_id', $actor->id),
                    'courier' => $orders->where('pickup_courier_id', $actor->id)->orWhere('delivery_courier_id', $actor->id),
                    'sorting_center' => $orders->where('sorting_center_id', $actor->id)->orWhereHas('trackingEvents', fn ($events) => $events->where('actor_id', $actor->id)),
                    default => abort(403),
                };
            });
        }
        if (filled($filters['q'] ?? null)) {
            $pattern = '%'.strtr(trim($filters['q']), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(fn ($orders) => $orders->whereRaw("order_number LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('seller', fn ($sellers) => $sellers->whereRaw("business_name LIKE ? ESCAPE '!'", [$pattern]))
                ->orWhereHas('buyer', fn ($buyers) => $buyers->whereRaw("first_name LIKE ? ESCAPE '!'", [$pattern])));
        }

        return $query->with(['seller:id,business_name,first_name,last_name', 'buyer:id,first_name,last_name', 'conversation.latestMessage.sender'])
            ->addSelect(['last_message_at' => OrderMessage::query()->select('order_messages.created_at')
                ->join('order_conversations', 'order_conversations.id', '=', 'order_messages.conversation_id')
                ->whereColumn('order_conversations.order_id', 'orders.id')->latest('order_messages.id')->limit(1)])
            ->orderByDesc('last_message_at')->orderByDesc('orders.id')->paginate(15, ['*'], 'threads_page')->withQueryString();
    }

    public function routePrefix(User $actor): string
    {
        return match ($actor->role) {
            'buyer' => 'buyer', 'seller' => 'seller', 'courier' => 'courier', 'sorting_center' => 'logistics',
            'admin', 'ecommerce_admin' => 'admin', 'logistics_admin' => 'logistics-admin', default => abort(403),
        };
    }

    public function orderUrl(User $actor, Order $order): string
    {
        return match ($actor->role) {
            'buyer', 'seller', 'courier' => route($actor->role.'.orders.show', $order),
            'sorting_center' => route('logistics.tracking', ['search' => $order->order_number]),
            'logistics_admin' => route('logistics-admin.dashboard', ['search' => $order->order_number]),
            default => route('admin.dashboard'),
        };
    }
}
