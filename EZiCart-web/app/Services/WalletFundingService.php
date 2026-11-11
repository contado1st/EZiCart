<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\WalletFundingIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletFundingService
{
    public function __construct(private FinancialLedgerService $ledger, private AdministrativeAuditService $audits) {}

    public function assertSandbox(): void
    {
        $enabled = config('financial.sandbox_enabled') || (int) DB::table('commerce_settings')->where('key', 'financial_sandbox_enabled')->value('value') === 1;
        abort_unless(config('financial.mode') === 'sandbox' && $enabled && app()->environment(['local', 'testing']), 503, 'Mock wallet funding is disabled. No live-money provider is configured.');
    }

    public function secret(): string
    {
        return config('financial.webhook_secret') ?: hash_hmac('sha256', 'ezicart-sandbox-funding', (string) config('app.key'));
    }

    public function create(User $actor, int $amount, string $key, bool $treasury = false): WalletFundingIntent
    {
        $this->assertSandbox();
        abort_unless($actor->status === 'approved' && ($treasury ? $actor->isCommerceAdmin() : in_array($actor->role, ['buyer', 'seller'], true)), 403);
        abort_unless($amount > 0 && $amount <= config('financial.maximum_transaction_cents') && strlen($key) >= 8 && strlen($key) <= 100, 422);

        return DB::transaction(function () use ($actor, $amount, $key, $treasury): WalletFundingIntent {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $account = $treasury ? $this->ledger->treasury() : $this->ledger->wallet($actor);
            $existing = WalletFundingIntent::where('user_id', $actor->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless($existing->amount_cents === $amount && $existing->account_id === $account->id, 409, 'Funding request reference already used with different details.');

                return $existing;
            }

            return WalletFundingIntent::create(['reference' => (string) Str::uuid(), 'user_id' => $actor->id, 'account_id' => $account->id, 'amount_cents' => $amount, 'idempotency_key' => $key, 'expires_at' => now()->addHour()]);
        });
    }

    /** Signed mock-provider callback; this is not a PayMongo live adapter. */
    public function callback(string $rawBody, string $signature, int $timestamp): WalletFundingIntent
    {
        $this->assertSandbox();
        abort_unless(abs(now()->timestamp - $timestamp) <= config('financial.callback_tolerance_seconds')
            && hash_equals(hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->secret()), $signature), 401, 'Invalid or expired funding callback signature.');
        $data = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        validator($data, ['event_id' => ['required', 'string', 'max:120'], 'intent' => ['required', 'uuid'], 'amount_cents' => ['required', 'integer', 'min:1'], 'currency' => ['required', 'in:PHP'], 'status' => ['required', 'in:paid,failed,reversed'], 'external_reference' => ['required', 'string', 'max:120']])->validate();
        $payloadHash = hash('sha256', $rawBody);

        return DB::transaction(function () use ($data, $payloadHash): WalletFundingIntent {
            $intent = WalletFundingIntent::where('reference', $data['intent'])->lockForUpdate()->firstOrFail();
            abort_unless($intent->amount_cents === (int) $data['amount_cents'] && $intent->currency === $data['currency'] && $intent->provider === 'sandbox', 422, 'Callback does not match the funding intent.');
            $event = DB::table('financial_provider_events')->where('reference', $data['event_id'])->first();
            if ($event) {
                abort_unless($event->intent_id === $intent->id && hash_equals($event->payload_hash, $payloadHash), 409, 'Provider event reference already used.');

                return $intent;
            }
            if ($data['status'] === 'paid') {
                if ($intent->status === 'PAID') {
                    abort_unless($intent->external_reference === $data['external_reference'], 409);
                } else {
                    abort_unless($intent->status === 'PENDING' && $intent->expires_at->isFuture(), 422, 'Funding intent is expired or already finalized.');
                    $clearing = $this->ledger->account('sandbox:clearing', 'sandbox_clearing', null, 'available', true);
                    $transaction = $this->ledger->transfer('funding:'.$intent->id, 'sandbox_topup', $clearing, FinancialAccount::findOrFail($intent->account_id), $intent->amount_cents, null, ['intent_id' => $intent->id, 'external_reference' => $data['external_reference'], 'sandbox' => true]);
                    $intent->forceFill(['status' => 'PAID', 'external_reference' => $data['external_reference'], 'transaction_id' => $transaction->id])->save();
                }
            } elseif ($data['status'] === 'failed') {
                abort_unless(in_array($intent->status, ['PENDING', 'FAILED'], true), 422, 'A successful payment cannot be changed into a failure.');
                $intent->update(['status' => 'FAILED']);
            } else {
                abort_unless($intent->status === 'PAID' && $intent->external_reference === $data['external_reference'], 422, 'Only a matching funded payment may be reversed.');
                $account = FinancialAccount::whereKey($intent->account_id)->lockForUpdate()->firstOrFail();
                if ($account->balance_cents < $intent->amount_cents) {
                    $account->update(['frozen' => true]);
                    $intent->update(['status' => 'UNDER_INVESTIGATION']);
                    $this->audits->record(null, $intent, 'funding_reversal_requires_recovery', [], ['status' => 'UNDER_INVESTIGATION'], 'Provider reversal exceeds the remaining available balance. Spending is frozen; no negative balance or fabricated recovery was posted.');
                } else {
                    $this->ledger->reverse(LedgerTransaction::findOrFail($intent->transaction_id), null, 'Verified sandbox provider reversal.');
                    $intent->update(['status' => 'REVERSED']);
                }
            }
            DB::table('financial_provider_events')->insert(['reference' => $data['event_id'], 'intent_id' => $intent->id, 'payload_hash' => $payloadHash, 'type' => $data['status'], 'created_at' => now()]);

            return $intent->refresh();
        }, 3);
    }

    public function simulate(WalletFundingIntent $intent, User $admin, string $result): WalletFundingIntent
    {
        $this->assertSandbox();
        abort_unless($admin->isCommerceAdmin() && $admin->status === 'approved', 403);
        $payload = json_encode(['event_id' => 'sandbox:'.$intent->reference.':'.$result, 'intent' => $intent->reference, 'amount_cents' => $intent->amount_cents, 'currency' => 'PHP', 'status' => $result, 'external_reference' => $intent->external_reference ?? 'sandbox:'.$intent->reference], JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $result = $this->callback($payload, hash_hmac('sha256', $timestamp.'.'.$payload, $this->secret()), $timestamp);
        $this->audits->record($admin, $intent, 'sandbox_funding_simulated', [], ['status' => $result->status], 'Explicit mock-provider operation; no real money was transferred.');

        return $result;
    }
}
