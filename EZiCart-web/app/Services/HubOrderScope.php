<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class HubOrderScope
{
    public function isPickupHub(Order $order, User $operator): bool
    {
        return $order->pickupCourier()->where(fn (Builder $rider) => $rider->where('logistics_owner_id', $operator->id)
            ->orWhereHas('serviceAreas', fn (Builder $area) => $area->where('sorting_center_id', $operator->id)))->exists();
    }

    public function query(User $operator): Builder
    {
        $singleHub = User::where('role', 'sorting_center')->where('status', 'approved')->limit(2)->count() < 2;

        return Order::query()->where(function (Builder $query) use ($operator, $singleHub): void {
            $query->where('sorting_center_id', $operator->id)
                ->orWhere(function (Builder $unreceived) use ($operator, $singleHub): void {
                    $unreceived->whereNull('sorting_center_id')->where(function (Builder $areaQuery) use ($operator, $singleHub): void {
                        $areaQuery->whereHas('destinationArea', fn (Builder $area) => $area->where('sorting_center_id', $operator->id));
                        $areaQuery->orWhereHas('pickupCourier', fn (Builder $rider) => $rider->where('logistics_owner_id', $operator->id)
                            ->orWhereHas('serviceAreas', fn (Builder $area) => $area->where('sorting_center_id', $operator->id)));
                        if ($singleHub) {
                            $areaQuery->orWhereHas('destinationArea', fn (Builder $area) => $area->whereNull('sorting_center_id'))->orWhereDoesntHave('destinationArea');
                        }
                    });
                });
        });
    }
}
