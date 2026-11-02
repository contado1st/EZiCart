<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParcelLoadService
{
    public const KG_RULES = ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:100000'];

    public static function grams(string|int|float $kg): int
    {
        return (int) round((float) $kg * 1000);
    }

    public static function kg(?int $grams): string
    {
        return $grams === null ? 'Not recorded' : number_format($grams / 1000, 3).' kg';
    }

    public function activeLoads(int $riderId): Builder
    {
        return Order::where(function (Builder $query) use ($riderId): void {
            $query->where(fn ($pickup) => $pickup->where('pickup_courier_id', $riderId)->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP']))
                ->orWhere(fn ($delivery) => $delivery->where('delivery_courier_id', $riderId)->activeCourierWorkload())
                ->orWhereHas('activeTransfer', fn ($transfer) => $transfer->where('carrier_rider_id', $riderId)->where('status', 'IN_TRANSIT'));
        });
    }

    public function summary(User $rider): array
    {
        $rows = $this->activeLoads($rider->id)->get(['id', 'parcel_weight_grams']);

        return $this->summarize($rider, $rows);
    }

    public function summaries($riders): array
    {
        $ids = $riders->modelKeys();
        $rows = Order::where(fn ($q) => $q
            ->where(fn ($p) => $p->whereIn('pickup_courier_id', $ids)->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP']))
            ->orWhere(fn ($d) => $d->whereIn('delivery_courier_id', $ids)->activeCourierWorkload())
            ->orWhereHas('activeTransfer', fn ($t) => $t->whereIn('carrier_rider_id', $ids)->where('status', 'IN_TRANSIT')))->with('activeTransfer')->get();
        $result = [];
        foreach ($riders as $rider) {
            $assigned = $rows->filter(fn ($row) => (in_array($row->status, ['READY_FOR_PICKUP', 'PICKED_UP'], true) && (int) $row->pickup_courier_id === $rider->id)
                || (in_array($row->status, ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT'], true) && (int) $row->delivery_courier_id === $rider->id && ! $row->return_handed_to_seller_at)
                || ((int) $row->activeTransfer?->carrier_rider_id === $rider->id && $row->activeTransfer?->status === 'IN_TRANSIT'));
            $result[$rider->id] = $this->summarize($rider, $assigned);
        }

        return $result;
    }

    private function summarize(User $rider, $rows): array
    {
        $unknown = $rows->filter(fn ($row) => ! $row->parcel_weight_grams)->count();
        $capacity = $rider->vehicle_capacity_verified_at ? $rider->vehicle_capacity_grams : null;
        $grams = (int) $rows->sum('parcel_weight_grams');

        return ['count' => $rows->count(), 'grams' => $grams, 'unknown' => $unknown, 'capacity' => $capacity,
            'remaining' => $capacity !== null && $unknown === 0 ? max(0, $capacity - $grams) : null,
            'overweight' => $capacity !== null && $grams > $capacity];
    }

    public function requireWeight(Order $order): int
    {
        if (! $order->parcel_weight_grams || $order->parcel_weight_grams < 1) {
            throw ValidationException::withMessages(['parcel_weight_kg' => 'Record the actual packed parcel weight in kg before pickup or dispatch.']);
        }

        return (int) $order->parcel_weight_grams;
    }

    /** Call within the existing operation transaction; reserve planned and physically held loads. */
    public function assertFits(Order $order, User $rider): void
    {
        $grams = $this->requireWeight($order);
        $rider = $this->lockVehicle($rider->id);
        abort_unless($rider->role === 'courier' && $rider->status === 'approved', 422, 'Choose an approved rider with a verified vehicle.');
        if (! $rider->vehicle_capacity_grams || ! $rider->vehicle_capacity_verified_at) {
            throw ValidationException::withMessages(['vehicle_capacity_kg' => 'A logistics admin must verify this vehicle’s maximum cargo capacity in kg before assignment or movement.']);
        }
        // Locking reads see current reservations even under MySQL repeatable-read.
        $rows = $this->activeLoads($rider->id)->whereKeyNot($order->id)->orderBy('id')->lockForUpdate()->get(['id', 'parcel_weight_grams']);
        if ($rows->contains(fn ($row) => ! $row->parcel_weight_grams)) {
            throw ValidationException::withMessages(['parcel_weight_kg' => 'Another parcel reserved for this rider has no recorded weight. Weigh all reserved parcels before adding or moving this load.']);
        }
        $reserved = (int) $rows->sum('parcel_weight_grams');
        if ($reserved + $grams > $rider->vehicle_capacity_grams) {
            throw ValidationException::withMessages(['vehicle_capacity_kg' => 'This vehicle would carry '.self::kg($reserved + $grams).' against a verified limit of '.self::kg($rider->vehicle_capacity_grams).'. Choose another vehicle or reduce its assigned load.']);
        }
    }

    public function lockVehicle(int $riderId): User
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::table('users')->where('id', $riderId)->update(['updated_at' => DB::raw('updated_at')]);
        }

        return User::whereKey($riderId)->lockForUpdate()->firstOrFail();
    }

    public function estimate(Order $order): ?int
    {
        $order->loadMissing('items');
        if ($order->items->isEmpty() || $order->items->contains(fn ($item) => ! $item->unit_weight_grams)) {
            return null;
        }

        return (int) $order->items->sum(fn ($item) => $item->unit_weight_grams * $item->quantity);
    }
}
