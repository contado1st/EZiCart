<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class LogisticsQueueService
{
    public const STATUSES = ['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER', 'DELIVERED', 'COMPLETED'];

    public const QUEUES = [
        'inbound' => ['PICKED_UP'], 'at_center' => ['AT_SORTING_CENTER'], 'sorted' => ['SORTED'],
        'assigned' => ['ASSIGNED_TO_RIDER'], 'out' => ['OUT_FOR_DELIVERY'],
        'delivered_today' => ['DELIVERED', 'COMPLETED'], 'failed' => ['DELIVERY_FAILED'],
        'returned' => ['RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'],
    ];

    public function filter(Builder $query, string $queue): Builder
    {
        abort_unless(isset(self::QUEUES[$queue]), 422, 'Choose a known logistics queue.');
        $query->whereIn('status', self::QUEUES[$queue]);
        if ($queue === 'delivered_today') {
            $query->where('delivered_at', '>=', today())->where('delivered_at', '<', today()->addDay());
        }

        return $query;
    }

    /** Fresh counts deliberately share exactly the linked queue predicate; no stale cache. */
    public function counts(User $hub): array
    {
        $base = app(HubOrderScope::class)->query($hub);

        return collect(array_keys(self::QUEUES))->mapWithKeys(fn ($key) => [$key => $this->filter(clone $base, $key)->count()])->all();
    }
}
