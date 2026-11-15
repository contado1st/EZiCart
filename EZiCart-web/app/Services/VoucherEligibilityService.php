<?php

namespace App\Services;

use App\Models\Voucher;

class VoucherEligibilityService
{
    /** Monetary values are integer cents; null means no minimum-spend check. */
    public function reason(Voucher $voucher, ?int $eligibleCents = null): ?array
    {
        if (! $voucher->is_active) {
            if ($voucher->expires_at?->lte(now())) {
                return $this->failure('expired');
            }
            $code = match ($voucher->disabled_reason) {
                'expired' => 'expired',
                'funding' => 'funding',
                'exhausted' => $voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit ? 'exhausted' : 'funding',
                default => 'inactive',
            };

            return $this->failure($code);
        }
        if ($voucher->seller_id && $voucher->seller?->status !== 'approved') {
            return $this->failure('seller_unavailable');
        }
        if ($voucher->starts_at?->isFuture()) {
            return $this->failure('scheduled');
        }
        if ($voucher->expires_at?->lte(now())) {
            return $this->failure('expired');
        }
        if ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit) {
            return $this->failure('exhausted');
        }
        if ($voucher->budget && ($voucher->budget->status !== 'RESERVED'
            || $voucher->budget->reserveAccount?->frozen
            || (int) $voucher->budget->reserveAccount?->balance_cents < (int) $voucher->budget->cap_cents)) {
            return $this->failure('funding');
        }
        if ($eligibleCents !== null) {
            $shortfall = CheckoutPricingService::cents($voucher->min_spend ?? 0) - $eligibleCents;
            if ($shortfall > 0) {
                return ['code' => 'minimum_spend', 'message' => 'Spend ₱'.number_format($shortfall / 100, 2).' more on eligible items to use this voucher.', 'shortfall_cents' => $shortfall];
            }
        }

        return null;
    }

    private function failure(string $code): array
    {
        return ['code' => $code, 'message' => match ($code) {
            'scheduled' => 'This voucher has not started yet.',
            'expired' => 'This voucher has expired.',
            'exhausted' => 'This voucher has reached its total redemption limit.',
            'funding' => 'This voucher requires sponsor reactivation or sufficient reserved funding.',
            'seller_unavailable' => 'This shop is unavailable, so its voucher cannot be used.',
            default => 'This voucher is paused or has ended.',
        }, 'shortfall_cents' => 0];
    }
}
