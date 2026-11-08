<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

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
                        // Before pickup, a known seller municipality owns the request. Destination
                        // ownership remains the fallback for older, unmapped pickup addresses.
                        $areaQuery->where(function (Builder $destination) use ($operator): void {
                            $destination->where(fn (Builder $stage) => $stage->where('status', '!=', 'READY_FOR_PICKUP')
                                ->orWhereDoesntHave('seller', fn (Builder $seller) => $this->sellerOrigin($seller, null)))
                                ->whereHas('destinationArea', fn (Builder $area) => $area->where('sorting_center_id', $operator->id));
                        })->orWhere(fn (Builder $pickup) => $pickup->where('status', 'READY_FOR_PICKUP')
                            ->whereHas('seller', fn (Builder $seller) => $this->sellerOrigin($seller, $operator->id)));
                        $areaQuery->orWhereHas('pickupCourier', fn (Builder $rider) => $rider->where('logistics_owner_id', $operator->id)
                            ->orWhereHas('serviceAreas', fn (Builder $area) => $area->where('sorting_center_id', $operator->id)));
                        if ($singleHub) {
                            $areaQuery->orWhereHas('destinationArea', fn (Builder $area) => $area->whereNull('sorting_center_id'))->orWhereDoesntHave('destinationArea');
                        }
                    });
                });
        });
    }

    private function sellerOrigin(Builder $seller, ?int $hubId): Builder
    {
        return $seller->whereExists(function (QueryBuilder $mapping) use ($hubId): void {
            $mapping->selectRaw('1')->from('area_municipalities as origin_mapping')->join('areas as origin_area', 'origin_area.id', '=', 'origin_mapping.area_id')
                ->where('origin_area.is_active', true)->whereNotNull('origin_area.sorting_center_id')
                ->whereColumn('origin_mapping.province_normalized', DB::raw('LOWER(TRIM(users.province))'))
                ->whereColumn('origin_mapping.municipality_normalized', DB::raw('LOWER(TRIM(users.municipality))'));
            if ($hubId !== null) {
                $mapping->where('origin_area.sorting_center_id', $hubId);
            }
        });
    }
}
