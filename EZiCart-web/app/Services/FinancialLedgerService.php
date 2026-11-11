<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialLedgerService
{
    public function account(string $key, string $type = 'system', ?int $userId = null, string $bucket = 'available', bool $allowNegative = false): FinancialAccount
    {
        return FinancialAccount::firstOrCreate(['key' => $key], ['type' => $type, 'user_id' => $userId, 'bucket' => $bucket, 'allow_negative' => $allowNegative])->refresh();
    }

    public function wallet(User $user, string $bucket = 'available'): FinancialAccount
    {
        abort_unless(in_array($bucket, ['available', 'pending', 'held'], true), 422);

        return $this->account('user:'.$user->id.':'.$bucket, $user->role, $user->id, $bucket);
    }

    public function treasury(): FinancialAccount
    {
        return $this->account('platform:treasury', 'treasury');
    }

    public function escrow(int $orderId): FinancialAccount
    {
        return $this->account('order:'.$orderId.':escrow', 'order_escrow', null, 'held');
    }

    public function transfer(string $key, string $type, FinancialAccount $from, FinancialAccount $to, int $amount, ?User $actor, array $metadata = []): LedgerTransaction
    {
        abort_unless($amount > 0, 422, 'A ledger transfer must be positive.');

        return $this->post($key, $type, [$from->id => -$amount, $to->id => $amount], $actor, $metadata);
    }

    /** Only trusted domain services construct the posting map; no request accepts ledger account IDs. */
    public function post(string $key, string $type, array $deltas, ?User $actor, array $metadata = [], ?int $reversalOf = null): LedgerTransaction
    {
        $deltas = array_filter($deltas, fn ($amount) => $amount !== 0);
        ksort($deltas, SORT_NUMERIC);
        abort_unless(count($deltas) >= 2 && array_sum($deltas) === 0 && strlen($key) <= 160, 422, 'Ledger entries must balance exactly.');
        foreach ($deltas as $amount) {
            abort_unless(is_int($amount) && abs($amount) <= config('financial.maximum_transaction_cents'), 422, 'Unsupported ledger amount.');
        }
        $hash = hash('sha256', json_encode([$type, $deltas, $actor?->id, $metadata, $reversalOf], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($key, $type, $deltas, $actor, $metadata, $hash, $reversalOf): LedgerTransaction {
            if (DB::getDriverName() === 'sqlite') {
                DB::table('financial_accounts')->whereIn('id', array_keys($deltas))->update(['balance_cents' => DB::raw('balance_cents')]);
            }
            $accounts = FinancialAccount::whereIn('id', array_keys($deltas))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($accounts->count() === count($deltas), 422, 'A ledger account is unavailable.');
            $existing = LedgerTransaction::where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'This financial reference was already used with different details.');

                return $existing;
            }
            foreach ($deltas as $id => $delta) {
                $account = $accounts[$id];
                abort_unless($account->currency === 'PHP' && ! $account->frozen, 422, 'This financial account is unavailable or under investigation.');
                if (! $account->allow_negative && $account->balance_cents + $delta < 0) {
                    throw ValidationException::withMessages(['balance' => 'Insufficient available funds. Required: PHP '.number_format(abs($delta) / 100, 2).'; available: PHP '.number_format($account->balance_cents / 100, 2).'; additional funds required: PHP '.number_format(-($account->balance_cents + $delta) / 100, 2).'.']);
                }
            }
            $transaction = LedgerTransaction::create(['reference' => (string) Str::uuid(), 'idempotency_key' => $key, 'payload_hash' => $hash, 'type' => $type, 'actor_id' => $actor?->id, 'reversal_of' => $reversalOf, 'metadata' => $metadata, 'created_at' => now()]);
            foreach ($deltas as $id => $delta) {
                $account = $accounts[$id];
                LedgerEntry::create(['transaction_id' => $transaction->id, 'account_id' => $id, 'delta_cents' => $delta, 'balance_before_cents' => $account->balance_cents, 'balance_after_cents' => $account->balance_cents + $delta, 'created_at' => now()]);
                $account->increment('balance_cents', $delta);
            }

            return $transaction;
        }, 3);
    }

    public function reverse(LedgerTransaction $transaction, ?User $actor, string $reason): LedgerTransaction
    {
        abort_unless(strlen(trim($reason)) >= 5 && $transaction->reversal_of === null, 422);
        $deltas = $transaction->entries()->get()->mapWithKeys(fn ($entry) => [$entry->account_id => -(int) $entry->delta_cents])->all();

        return $this->post('reversal:'.$transaction->id, 'reversal', $deltas, $actor, ['reason' => $reason], $transaction->id);
    }

    public function reconcile(): array
    {
        $imbalanced = DB::table('ledger_entries')->select('transaction_id')->groupBy('transaction_id')->havingRaw('SUM(delta_cents) <> 0')->pluck('transaction_id')->all();
        $mismatches = FinancialAccount::query()->get()->filter(fn ($account) => $account->balance_cents !== (int) $account->entries()->sum('delta_cents'))->pluck('id')->all();

        return ['balanced' => $imbalanced === [] && $mismatches === [], 'imbalanced_transaction_ids' => $imbalanced, 'account_mismatch_ids' => $mismatches];
    }
}
