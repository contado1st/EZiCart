<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LogisticsExceptionService
{
    public function __construct(private TransactionAwareNotificationSender $notifications) {}

    public function openForRiderSuspension(User $rider, User $actor, string $reason): void
    {
        DB::transaction(fn () => $this->openLocked($rider, $actor, $reason));
    }

    private function openLocked(User $rider, User $actor, string $reason): void
    {
        $activeParcels = Order::query()
            ->where(function (Builder $query) use ($rider): void {
                $query->where(fn (Builder $deliveryQuery) => $deliveryQuery->where('delivery_courier_id', $rider->id)
                    ->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT']))
                    ->orWhere(fn (Builder $pickupQuery) => $pickupQuery->where('pickup_courier_id', $rider->id)
                        ->where(fn ($pickup) => $pickup->where('status', 'PICKED_UP')->orWhere(fn ($accepted) => $accepted->where('status', 'READY_FOR_PICKUP')->whereNotNull('pickup_claimed_at'))))
                    ->orWhereHas('activeTransfer', fn ($transfer) => $transfer->where('carrier_rider_id', $rider->id)->where('status', 'IN_TRANSIT'));
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
                'type' => (int) $parcel->activeTransfer?->carrier_rider_id === $rider->id ? 'HUB_CARRIER_SUSPENDED_WITH_PARCEL' : ($parcel->pickup_courier_id === $rider->id ? 'SUSPENDED_RIDER_PICKUP_ACCEPTED' : 'RIDER_SUSPENDED_WITH_ACTIVE_PARCEL'),
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
            $operators = collect([$parcel->sortingCenter, $parcel->activeTransfer?->destinationHub])->filter()
                ->merge(User::where('role', 'logistics_admin')->where('status', 'approved')->get())->unique('id');
            $this->notifications->send($operators, new WebOperationsNotification([
                'message' => 'Rider suspension requires custody recovery for '.$parcel->order_number.'. Confirm the physical parcel before reassignment.',
                'url' => route($parcel->activeTransfer ? 'parcel-transfers.index' : 'load-planning.index'),
            ]));
        }
    }
}
