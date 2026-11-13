<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class VoucherScheduleRules
{
    public function fields(): array
    {
        return ['starts_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after:now']];
    }

    public function assertBounds(array $values): void
    {
        if (isset($values['per_buyer_limit'], $values['usage_limit']) && $values['per_buyer_limit'] > $values['usage_limit']) {
            throw ValidationException::withMessages(['per_buyer_limit' => 'Uses per buyer cannot exceed the total redemption limit.']);
        }
        if (isset($values['starts_at'], $values['expires_at']) && Carbon::parse($values['expires_at'])->lte(Carbon::parse($values['starts_at']))) {
            throw ValidationException::withMessages(['expires_at' => 'Expiry must be later than the start time.']);
        }
    }
}
