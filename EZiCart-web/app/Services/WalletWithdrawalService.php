<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletWithdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletWithdrawalService
{
    public function __construct(private FinancialLedgerService $ledger, private AdministrativeAuditService $audits) {}

    public function request(User $actor, int $amount, string $destination, string $key): WalletWithdrawal
    {
        abort_unless($actor->status === 'approved' && in_array($actor->role, ['buyer', 'seller', 'courier', 'sorting_center'], true), 403);
        abort_unless($amount > 0 && strlen(trim($destination)) >= 5 && strlen($destination) <= 500 && strlen($key) >= 8 && strlen($key) <= 100, 422);

        return DB::transaction(function () use ($actor, $amount, $destination, $key): WalletWithdrawal {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $existing = WalletWithdrawal::where('user_id', $actor->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless($existing->amount_cents === $amount && $existing->destination === $destination, 409);

                return $existing;
            }
            $withdrawal = WalletWithdrawal::create(['reference' => (string) Str::uuid(), 'user_id' => $actor->id, 'amount_cents' => $amount, 'destination' => $destination, 'idempotency_key' => $key]);
            $this->ledger->transfer('withdrawal:'.$withdrawal->id.':hold', 'withdrawal_reservation', $this->ledger->wallet($actor), $this->ledger->wallet($actor, 'held'), $amount, $actor, ['withdrawal_id' => $withdrawal->id]);

            return $withdrawal;
        }, 3);
    }

    public function review(WalletWithdrawal $withdrawal, User $actor, string $decision, string $reason): void
    {
        DB::transaction(function () use ($withdrawal, $actor, $decision, $reason): void {
            $locked = WalletWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            $owner = User::findOrFail($locked->user_id);
            abort_unless($actor->status === 'approved' && $actor->id !== $owner->id && (in_array($owner->role, ['courier', 'sorting_center'], true) ? $actor->isLogisticsAdmin() : $actor->isCommerceAdmin()), 403);
            abort_unless($locked->status === 'REQUESTED' && in_array($decision, ['approve', 'reject'], true) && strlen(trim($reason)) >= 5, 422);
            if ($decision === 'reject') {
                $this->ledger->transfer('withdrawal:'.$locked->id.':release', 'withdrawal_release', $this->ledger->wallet($owner, 'held'), $this->ledger->wallet($owner), $locked->amount_cents, $actor, ['reason' => $reason]);
            }
            $locked->update(['status' => $decision === 'approve' ? 'APPROVED' : 'REJECTED', 'approved_by' => $actor->id]);
            $this->audits->record($actor, $locked, 'withdrawal_'.$decision, ['status' => 'REQUESTED'], ['status' => $locked->status], $reason);
        });
    }

    public function settle(WalletWithdrawal $withdrawal, User $actor, string $reference, string $reason): void
    {
        app(WalletFundingService::class)->assertSandbox();
        DB::transaction(function () use ($withdrawal, $actor, $reference, $reason): void {
            $locked = WalletWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            $owner = User::findOrFail($locked->user_id);
            abort_unless($actor->status === 'approved' && $actor->id !== $owner->id && (in_array($owner->role, ['courier', 'sorting_center'], true) ? $actor->isLogisticsAdmin() : $actor->isCommerceAdmin()), 403);
            abort_unless($locked->status === 'APPROVED' && strlen($reference) >= 5 && strlen($reason) >= 5, 422);
            $this->ledger->transfer('withdrawal:'.$locked->id.':settle', 'sandbox_withdrawal', $this->ledger->wallet($owner, 'held'), $this->ledger->account('sandbox:clearing', 'sandbox_clearing', null, 'available', true), $locked->amount_cents, $actor, ['reference' => $reference, 'sandbox' => true]);
            $locked->update(['status' => 'SETTLED', 'executed_by' => $actor->id, 'external_reference' => $reference]);
            $this->audits->record($actor, $locked, 'sandbox_withdrawal_settled', ['status' => 'APPROVED'], ['status' => 'SETTLED'], $reason);
        });
    }
}
