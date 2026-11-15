<?php

namespace App\Services;

use App\Models\DemoFundingCode;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DemoFundingCodeService
{
    public function __construct(private FinancialLedgerService $ledger, private WalletFundingService $sandbox,
        private DemoWalletFundingService $funding, private AdministrativeAuditService $audits,
        private TransactionAwareNotificationSender $notifications) {}

    private function ready(): void
    {
        $this->sandbox->assertSandbox();
        abort_unless(Schema::hasTable('demo_funding_codes'), 503, 'Demo funding codes are awaiting the database update. Existing wallet records remain available.');
    }

    public function visible(User $actor): Builder
    {
        return DemoFundingCode::where(fn (Builder $q) => $q->where('recipient_id', $actor->id)->orWhere('issuer_id', $actor->id));
    }

    public function issue(User $actor, User $recipient, int $amount, string $key, int $minutes, string $reason): array
    {
        $this->ready();
        abort_unless($amount > 0 && $amount <= config('financial.maximum_transaction_cents') && Str::isUuid($key)
            && $minutes >= 15 && $minutes <= 10080 && mb_strlen(trim($reason)) >= 5 && mb_strlen($reason) <= 500, 422);

        return DB::transaction(function () use ($actor, $recipient, $amount, $key, $minutes, $reason): array {
            if (DB::getDriverName() === 'sqlite') {
                DB::table('users')->where('id', $actor->id)->update(['updated_at' => DB::raw('updated_at')]);
            }
            $users = User::whereIn('id', [$actor->id, $recipient->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($actor->id);
            $recipient = $users->get($recipient->id);
            abort_unless($actor && $recipient && $actor->status === 'approved' && $recipient->status === 'approved'
                && in_array($recipient->role, WalletTransferService::USER_ROLES, true)
                && in_array($recipient->role, $this->funding->reviewRoles($actor), true), 403);
            abort_if($this->ledger->wallet($recipient)->frozen, 422, 'This recipient wallet is frozen.');
            abort_if(($actor->isCommerceAdmin() ? $this->ledger->treasury() : $this->ledger->wallet($actor))->frozen, 422, 'The issuing administration account is frozen.');
            $hash = hash('sha256', json_encode([$recipient->id, $amount, $minutes, $reason], JSON_THROW_ON_ERROR));
            $existing = DemoFundingCode::where('issuer_id', $actor->id)->where('request_key', $key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'This issuance key has different details.');

                return [$existing, null];
            }
            $secret = 'EZD-'.Str::upper(Str::random(32));
            $code = DemoFundingCode::create(['reference' => (string) Str::uuid(), 'issuer_id' => $actor->id, 'recipient_id' => $recipient->id,
                'amount_cents' => $amount, 'request_key' => $key, 'request_hash' => $hash, 'code_hash' => hash('sha256', $secret),
                'reason' => trim($reason), 'expires_at' => now()->addMinutes($minutes)]);
            $this->audits->record($actor, $code, 'demo_code_issued', [], ['recipient_id' => $recipient->id, 'amount_cents' => $amount, 'reference' => $code->reference], $reason);
            $this->notifications->send($recipient, new WebOperationsNotification(['message' => 'A recipient-bound demo funding code was issued for you. Obtain the code from the issuing administrator.', 'url' => route('wallet.codes.receipt', $code->reference)]));

            return [$code, $secret];
        }, 3);
    }

    public function redeem(User $actor, string $secret): DemoFundingCode
    {
        $this->ready();
        $hash = hash('sha256', Str::upper(preg_replace('/\s+/', '', trim($secret))));

        return DB::transaction(function () use ($actor, $hash): DemoFundingCode {
            if (DB::getDriverName() === 'sqlite') {
                DB::table('users')->where('id', $actor->id)->update(['updated_at' => DB::raw('updated_at')]);
            }
            $candidate = DemoFundingCode::where('code_hash', $hash)->first();
            abort_unless($candidate && (int) $candidate->recipient_id === $actor->id, 422, 'This code is unavailable for your account. Check the code and named recipient with its issuer.');
            $users = User::whereIn('id', [$actor->id, $candidate->issuer_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($actor->id);
            abort_unless($actor->status === 'approved' && in_array($actor->role, WalletTransferService::USER_ROLES, true), 403);
            $code = DemoFundingCode::where('code_hash', $hash)->lockForUpdate()->first();
            abort_unless($code && (int) $code->recipient_id === $actor->id, 422, 'This code is unavailable for your account. Check the code and named recipient with its issuer.');
            if ($code->redeemed_at) {
                return $code;
            }
            abort_unless(! $code->revoked_at && $code->expires_at->isFuture(), 422, 'This code is expired or revoked. Ask the issuing administrator for a new code.');
            $issuer = $users->get($code->issuer_id);
            abort_unless($issuer?->status === 'approved' && in_array($actor->role, $this->funding->reviewRoles($issuer), true), 422, 'The issuing administrator is no longer authorized.');
            $transaction = $this->ledger->transfer('demo-code:'.$code->id, 'demo_code_credit',
                $this->ledger->account('demo:issuance', 'demo_issuance', null, 'available', true), $this->ledger->wallet($actor), $code->amount_cents, $actor,
                ['funding_code_reference' => $code->reference, 'issuer_id' => $issuer->id, 'recipient_id' => $actor->id, 'demo' => true]);
            $code->forceFill(['redeemed_at' => now(), 'transaction_id' => $transaction->id])->save();
            $this->audits->record($actor, $code, 'demo_code_redeemed', [], ['transaction_reference' => $transaction->reference], 'Recipient redeemed the single-use demo funding code.');
            $this->notifications->send($actor, new WebOperationsNotification(['message' => 'Demo funds credited once: PHP '.number_format($code->amount_cents / 100, 2).'. Receipt '.$code->reference, 'url' => route('wallet.codes.receipt', $code->reference)]));

            return $code;
        }, 3);
    }

    public function revoke(User $actor, DemoFundingCode $code): void
    {
        $this->ready();
        DB::transaction(function () use ($actor, $code): void {
            if (DB::getDriverName() === 'sqlite') {
                DB::table('users')->where('id', $actor->id)->update(['updated_at' => DB::raw('updated_at')]);
            }
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $code = DemoFundingCode::whereKey($code->id)->lockForUpdate()->firstOrFail();
            $recipient = User::findOrFail($code->recipient_id);
            abort_unless($actor->status === 'approved' && (int) $code->issuer_id === $actor->id && in_array($recipient->role, $this->funding->reviewRoles($actor), true), 403);
            abort_if($code->redeemed_at, 422, 'Redeemed funds remain in the ledger; the code cannot be revoked.');
            if ($code->revoked_at) {
                return;
            }
            $code->forceFill(['revoked_at' => now()])->save();
            $this->audits->record($actor, $code, 'demo_code_revoked', [], ['reference' => $code->reference], 'Issuer revoked the unused demo code.');
        }, 3);
    }
}
