<?php

namespace App\Services;

use App\Models\DirectConversation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class MessagingInboxService
{
    public function threads(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $actor = $request->user();
        $query = DirectConversation::query()->forUser($actor);
        if (filled($filters['q'] ?? null)) {
            $pattern = '%'.strtr(trim($filters['q']), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(function ($q) use ($pattern, $actor) {
                foreach (['firstUser', 'secondUser'] as $relation) {
                    $q->orWhereHas($relation, fn ($user) => $user->whereKeyNot($actor->id)->where(fn ($names) => $names
                        ->whereRaw("first_name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("last_name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("business_name LIKE ? ESCAPE '!'", [$pattern])));
                }
            });
        }

        return $query->with(['firstUser', 'secondUser', 'latestMessage.sender'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('recipient_id', $actor->id)->whereNull('read_at')])
            ->latest('updated_at')->latest('id')->paginate(15, ['*'], 'threads_page')->withQueryString();
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
