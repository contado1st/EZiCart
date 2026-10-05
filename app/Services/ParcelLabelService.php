<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ParcelLabelService
{
    public function reprint(Order $order, User $seller, string $reason): Order
    {
        return DB::transaction(function () use ($order, $seller, $reason): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->seller_id === $seller->id && $seller->role === 'seller', 403);
            abort_unless(
                in_array($lockedOrder->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true)
                    && $lockedOrder->pickup_claimed_at === null
                    && $lockedOrder->seller_handover_at === null,
                422,
                'A parcel label can only be replaced before physical pickup begins.',
            );

            $previousParcelCode = $lockedOrder->parcel_code;
            $previousVersion = (int) $lockedOrder->parcel_code_version;
            $replacementParcelCode = Str::upper(Str::random(24));
            $replacementVersion = $previousVersion + 1;

            $lockedOrder->rotateParcelCodeForReprint($replacementParcelCode, $replacementVersion, $seller);

            DB::table('parcel_label_reprints')->insert([
                'order_id' => $lockedOrder->id,
                'actor_id' => $seller->id,
                'previous_parcel_code' => $previousParcelCode,
                'replacement_parcel_code' => $replacementParcelCode,
                'previous_version' => $previousVersion,
                'replacement_version' => $replacementVersion,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('scan_events')->insert([
                'order_id' => $lockedOrder->id,
                'actor_id' => $seller->id,
                'station' => 'parcel_label_reprint',
                'result' => 'accepted',
                'method' => 'manual',
                'created_at' => now(),
            ]);

            return $lockedOrder->refresh();
        });
    }
}
