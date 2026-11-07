<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RiderBadgeService
{
    public function ensure(User $rider): string
    {
        abort_unless($rider->role === 'courier', 404);

        if (filled($rider->badge_code)) {
            return $rider->badge_code;
        }

        return DB::transaction(function () use ($rider): string {
            $lockedRider = User::query()->whereKey($rider->id)->lockForUpdate()->firstOrFail();
            if (blank($lockedRider->badge_code)) {
                $lockedRider->forceFill(['badge_code' => Str::random(48)])->save();
            }

            return $lockedRider->badge_code;
        });
    }

    public function rotate(User $rider): string
    {
        abort_unless($rider->role === 'courier', 404);

        return DB::transaction(function () use ($rider): string {
            $lockedRider = User::query()->whereKey($rider->id)->lockForUpdate()->firstOrFail();
            $badgeCode = Str::random(48);
            $lockedRider->forceFill(['badge_code' => $badgeCode])->save();

            return $badgeCode;
        });
    }
}
