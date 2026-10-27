<?php

namespace App\Services;

use App\Models\BuyerAddress;
use App\Models\User;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BuyerAddressService
{
    public const LOCATION_FIELDS = ['region', 'province', 'municipality', 'barangay', 'house_number', 'street_address', 'postal_code'];

    /** Preserve registration/profile addresses when a buyer first uses the address book. */
    public function importProfileAddress(User $buyer): void
    {
        if ($buyer->addresses()->exists() || ! $buyer->province || ! $buyer->municipality) {
            return;
        }
        $this->withBuyerLock($buyer, function (User $locked): void {
            if ($locked->addresses()->exists() || ! $locked->province || ! $locked->municipality) {
                return;
            }

            $locked->addresses()->create([
                ...$locked->only(self::LOCATION_FIELDS),
                'label' => 'Home',
                'recipient_name' => trim($locked->first_name.' '.$locked->last_name),
                'recipient_contact' => $locked->contact_no,
                'is_default' => true,
            ]);
        });
    }

    public function save(User $buyer, array $data, ?int $addressId = null): BuyerAddress
    {
        return $this->withBuyerLock($buyer, function (User $locked) use ($data, $addressId): BuyerAddress {
            $address = $addressId === null ? $locked->addresses()->make() : $locked->addresses()->findOrFail($addressId);
            $makeDefault = ! $locked->addresses()->exists() || $address->is_default || ($data['is_default'] ?? false);
            $address->fill(Arr::only($data, [...self::LOCATION_FIELDS, 'label', 'recipient_name', 'recipient_contact']));

            if ($makeDefault) {
                $locked->addresses()->where('is_default', true)
                    ->when($address->exists, fn ($query) => $query->whereKeyNot($address->id))
                    ->update(['is_default' => false]);
            }
            $address->is_default = $makeDefault;
            $address->save();
            if ($makeDefault) {
                $this->syncProfile($locked, $address);
            }

            return $address;
        });
    }

    public function makeDefault(User $buyer, int $addressId): void
    {
        $this->withBuyerLock($buyer, function (User $locked) use ($addressId): void {
            $address = $locked->addresses()->findOrFail($addressId);
            $locked->addresses()->where('is_default', true)->whereKeyNot($address->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
            $this->syncProfile($locked, $address);
        });
    }

    public function delete(User $buyer, int $addressId): void
    {
        $this->withBuyerLock($buyer, function (User $locked) use ($addressId): void {
            $address = $locked->addresses()->findOrFail($addressId);
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $replacement = $locked->addresses()->oldest('id')->first();
                $replacement?->update(['is_default' => true]);
                $this->syncProfile($locked, $replacement);
            }
        });
    }

    /** Keep the existing profile-address editor consistent with the address book. */
    public function syncProfileLocation(User $buyer): void
    {
        $this->withBuyerLock($buyer, function (User $locked): void {
            if (! $locked->province || ! $locked->municipality) {
                return;
            }
            $default = $locked->addresses()->where('is_default', true)->first();
            if ($default) {
                $default->update($locked->only(self::LOCATION_FIELDS));
            } else {
                $this->importProfileAddress($locked);
            }
        });
    }

    private function syncProfile(User $buyer, ?BuyerAddress $address): void
    {
        $buyer->update($address ? $address->only(self::LOCATION_FIELDS) : array_fill_keys(self::LOCATION_FIELDS, null));
    }

    private function withBuyerLock(User $buyer, Closure $callback): mixed
    {
        abort_unless($buyer->role === 'buyer', 403);

        return DB::transaction(function () use ($buyer, $callback) {
            if (DB::getDriverName() === 'sqlite') {
                DB::table('users')->where('id', $buyer->id)->update(['updated_at' => DB::raw('updated_at')]);
            }
            $locked = User::query()->whereKey($buyer->id)->lockForUpdate()->firstOrFail();

            return $callback($locked);
        }, 3);
    }
}
