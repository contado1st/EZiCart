<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LogisticsExceptionService
{
    public function openForRiderSuspension(User $rider, User $actor, string $reason): void
    {
        $activeParcels = Order::query()
            ->where(function (Builder $query) use ($rider): void {
                $query->where(fn (Builder $deliveryQuery) => $deliveryQuery->where('delivery_courier_id', $rider->id)
                    ->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT']))
                    ->orWhere(fn (Builder $pickupQuery) => $pickupQuery->where('pickup_courier_id', $rider->id)
                        ->where('status', 'READY_FOR_PICKUP')->whereNotNull('pickup_claimed_at')->whereNull('seller_handover_at'));
            })
            ->lockForUpdate()
            ->get();

        foreach ($activeParcels as $parcel) {
            $alreadyOpen = DB::table('logistics_exceptions')
                ->where('order_id', $parcel->id)
                ->where('previous_rider_id', $rider->id)
                ->where('status', 'OPEN')
                ->exists();

            if ($alreadyOpen) {
                continue;
            }

            DB::table('logistics_exceptions')->insert([
                'order_id' => $parcel->id,
                'type' => $parcel->pickup_courier_id === $rider->id ? 'SUSPENDED_RIDER_PICKUP_ACCEPTED' : 'RIDER_SUSPENDED_WITH_ACTIVE_PARCEL',
                'reason' => $reason,
                'opened_by' => $actor->id,
                'previous_rider_id' => $rider->id,
                'new_rider_id' => null,
                'status' => 'OPEN',
                'resolution' => null,
                'resolved_by' => null,
                'resolved_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
