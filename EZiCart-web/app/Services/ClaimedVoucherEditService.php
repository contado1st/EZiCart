<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBudget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClaimedVoucherEditService
{
    public function __construct(private AdministrativeAuditService $audits, private FinancialLedgerService $ledger) {}

    public function update(User $seller, Voucher $voucher, array $data): void
    {
        DB::transaction(function () use ($seller, $voucher, $data) {
            $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            abort_unless($seller->role === 'seller' && $seller->status === 'approved' && $locked->seller_id === $seller->id, 403);
            app(VoucherClaimService::class)->synchronizeHistory($locked);
            $claimed = $locked->claimed_count > 0 || DB::table('voucher_claims')->where('voucher_id', $locked->id)->exists();
            foreach (['starts_at', 'expires_at'] as $field) {
                if (isset($data[$field])) {
                    $data[$field] = Carbon::parse($data[$field], config('app.timezone'))->setTimezone(config('app.timezone'));
                }
            }
            if ($claimed) {
                foreach (['recipient_limit', 'per_buyer_limit', 'starts_at'] as $field) {
                    if ($field !== 'starts_at' && $locked->terms_version < 2) {
                        continue;
                    }
                    $same = $field === 'starts_at' ? (($data[$field] ?? null)?->toDateTimeString() === $locked->$field?->toDateTimeString()) : ((string) ($data[$field] ?? '') === (string) $locked->$field);
                    if (array_key_exists($field, $data) && ! $same) {
                        throw ValidationException::withMessages([$field => 'Claimed voucher terms are locked. Only extend expiry or increase total usage.']);
                    }
                }
                if (array_key_exists('expires_at', $data) && (($locked->expires_at === null && $data['expires_at'] !== null) || ($locked->expires_at && $data['expires_at'] && $data['expires_at']->lt($locked->expires_at)))) {
                    throw ValidationException::withMessages(['expires_at' => 'A claimed voucher expiry can only be extended.']);
                }
            }
            $newLimit = (int) ($data['usage_limit'] ?? $locked->usage_limit);
            if (array_key_exists('usage_limit', $data) && (($locked->usage_limit !== null && $newLimit < $locked->usage_limit) || $newLimit < $locked->used_count)) {
                throw ValidationException::withMessages(['usage_limit' => 'Total redemptions can only be increased.']);
            }
            if (isset($data['recipient_limit']) && $data['recipient_limit'] < $locked->claimed_count) {
                throw ValidationException::withMessages(['recipient_limit' => 'The recipient limit cannot be lower than existing claims.']);
            }
            if (isset($data['per_buyer_limit']) && $data['per_buyer_limit'] < (int) DB::table('voucher_claims')->where('voucher_id', $locked->id)->max('redemption_count')) {
                throw ValidationException::withMessages(['per_buyer_limit' => 'The per-buyer limit cannot be below an existing redemption count.']);
            }
            app(VoucherScheduleRules::class)->assertBounds([...$locked->only(['starts_at', 'expires_at', 'usage_limit', 'per_buyer_limit']), ...$data]);
            $budget = VoucherBudget::where('voucher_id', $locked->id)->lockForUpdate()->first();
            if ($newLimit > (int) $locked->usage_limit) {
                if (! $budget) {
                    throw ValidationException::withMessages(['usage_limit' => 'This legacy voucher needs a funded campaign before its total limit can increase.']);
                }
                $maximum = $newLimit * $budget->cap_cents;
                if ($maximum > config('financial.maximum_transaction_cents')) {
                    throw ValidationException::withMessages(['usage_limit' => 'The campaign exceeds the supported funding limit.']);
                }
                $reserve = FinancialAccount::findOrFail($budget->reserve_account_id);
                $needed = max(0, $maximum - $budget->consumed_cents - $reserve->balance_cents);
                if ($needed > 0) {
                    $source = FinancialAccount::findOrFail($budget->source_account_id);
                    $this->ledger->transfer('voucher:'.$locked->id.':extend:'.$budget->maximum_cents.':'.$maximum.':'.$budget->released_cents, 'voucher_funding', $source, $reserve, $needed, $seller, ['voucher_id' => $locked->id]);
                }
                $budget->update(['maximum_cents' => $maximum, 'status' => 'RESERVED']);
            }
            $before = $locked->only(array_keys($data));
            $locked->update($data);
            $this->audits->record($seller, $locked, 'seller_voucher_terms_updated', $before, $locked->only(array_keys($data)), 'Safe schedule/limit edit with atomic sponsor funding. Availability requires separate activation.');
        }, 3);
    }
}
