<?php

namespace App\Services;

use App\Models\DispatchLocation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DispatchLocationService
{
    public function __construct(private AdministrativeAuditService $audits) {}

    public function rider(User $actor, array $data): void
    {
        abort_unless($actor->role === 'courier' && $actor->status === 'approved', 403);
        validator($data, ['latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180'], 'accuracy_meters' => ['required', 'numeric', 'min:0', 'max:'.config('dispatch.maximum_accuracy_meters')], 'captured_at' => ['required', 'date']])->validate();
        $captured = Carbon::parse($data['captured_at']);
        abort_unless($captured->gte(now()->subSeconds(90)) && $captured->lte(now()->addSeconds(10)), 422, 'Location timestamp is stale or your device clock needs correction. Request a fresh position.');
        DB::transaction(function () use ($actor, $data) {
            app(ParcelLoadService::class)->lockVehicle($actor->id);
            DispatchLocation::updateOrCreate(['user_id' => $actor->id], ['latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'accuracy_meters' => (int) ceil($data['accuracy_meters']), 'source' => 'device_reported', 'address_hash' => null, 'verified_by' => null, 'recorded_at' => now()]);
        });
    }

    public function pin(User $actor, User $target, array $data): void
    {
        abort_unless($actor->status === 'approved' && ($actor->isLogisticsAdmin() || $actor->role === 'sorting_center'), 403);
        abort_unless(in_array($target->role, ['seller', 'sorting_center'], true) && $target->status === 'approved', 404);
        if (! $actor->isLogisticsAdmin()) {
            abort_unless(($target->role === 'sorting_center' && $actor->id === $target->id) || ($target->role === 'seller' && app(HubOrderScope::class)->query($actor)->where('seller_id', $target->id)->exists()), 404);
        }
        validator($data, ['latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180'], 'reason' => ['required', 'string', 'min:10', 'max:500']])->validate();
        DB::transaction(function () use ($actor, $target, $data) {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $point = DispatchLocation::updateOrCreate(['user_id' => $target->id], ['latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'accuracy_meters' => null, 'source' => 'hub_verified', 'address_hash' => self::addressHash($target), 'verified_by' => $actor->id, 'recorded_at' => now()]);
            $this->audits->record($actor, $point, 'dispatch_origin_verified', [], ['user_id' => $target->id, 'address_hash' => $point->address_hash], $data['reason']);
        });
    }

    public static function addressHash(User $user): string
    {
        return hash('sha256', json_encode($user->only(['province', 'municipality', 'barangay', 'street_address', 'house_number', 'postal_code']), JSON_THROW_ON_ERROR));
    }

    public function origin(User $user): ?DispatchLocation
    {
        return DispatchLocation::where('user_id', $user->id)->where('source', 'hub_verified')->where('address_hash', self::addressHash($user))->lockForUpdate()->first();
    }

    public function fresh(User $rider): ?DispatchLocation
    {
        return DispatchLocation::where('user_id', $rider->id)->where('source', 'device_reported')->where('recorded_at', '>=', now()->subSeconds((int) config('dispatch.position_fresh_seconds')))->where('recorded_at', '<=', now())
            ->where('accuracy_meters', '<=', (int) config('dispatch.maximum_accuracy_meters'))->lockForUpdate()->first();
    }
}
