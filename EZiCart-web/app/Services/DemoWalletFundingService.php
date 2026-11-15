<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\User;
use App\Models\WalletFundingIntent;
use App\Notifications\WebOperationsNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoWalletFundingService
{
    public function __construct(private FinancialLedgerService $ledger, private WalletFundingService $sandbox,
        private AdministrativeAuditService $audits, private TransactionAwareNotificationSender $notifications) {}

    public function reviewRoles(User $actor): array
    {
        return $actor->isCommerceAdmin() ? ['buyer', 'seller', 'admin', 'ecommerce_admin']
            : ($actor->isLogisticsAdmin() ? ['courier', 'sorting_center', 'logistics_admin'] : []);
    }

    public function visible(User $actor): Builder
    {
        return WalletFundingIntent::where(function (Builder $q) use ($actor): void {
            $q->where('user_id', $actor->id)->orWhere(function (Builder $review) use ($actor): void {
                $review->where('provider', 'internal_demo')->whereHas('user', fn ($owner) => $owner->whereIn('role', $this->reviewRoles($actor)));
            });
            if ($actor->isCommerceAdmin()) {
                $q->orWhere('provider', 'sandbox'); // Keep the existing mock funding history/controls.
            }
        });
    }

    public function canReview(User $actor, WalletFundingIntent $intent): bool
    {
        return $actor->status === 'approved' && $actor->id !== (int) $intent->user_id && $intent->provider === 'internal_demo'
            && in_array($intent->user->role, $this->reviewRoles($actor), true);
    }

    public function request(User $actor, int $amount, string $key, string $kind, array $details): WalletFundingIntent
    {
        $this->sandbox->assertSandbox();
        abort_unless($actor->status === 'approved' && in_array($actor->role, ['buyer', 'seller', 'courier', 'sorting_center', 'admin', 'ecommerce_admin', 'logistics_admin'], true), 403);
        abort_unless($amount > 0 && $amount <= config('financial.maximum_transaction_cents') && Str::isUuid($key)
            && in_array($kind, ['demo_credit', 'demo_cash_deposit'], true) && mb_strlen(trim($details['reason'] ?? '')) >= 5, 422);
        if ($kind === 'demo_cash_deposit') {
            abort_unless(filled($details['receipt_reference'] ?? null) && $this->validEvidence($details), 422, 'A recorded demo deposit requires a receipt reference and valid private evidence.');
        }

        return DB::transaction(function () use ($actor, $amount, $key, $kind, $details): WalletFundingIntent {
            $owner = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($owner->status === 'approved' && $owner->role === $actor->role, 403);
            $existing = WalletFundingIntent::where('user_id', $actor->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                // Upload paths are generated; stable receipt contents, kind and amount define the request.
                $expected = $existing->request_details ?? [];
                foreach (['evidence_path'] as $field) {
                    unset($expected[$field], $details[$field]);
                }
                abort_unless($existing->provider === 'internal_demo' && $existing->amount_cents === $amount && $existing->request_kind === $kind && $expected === $details, 409, 'This request key has different funding details.');

                return $existing;
            }
            $account = $actor->isCommerceAdmin() ? $this->ledger->treasury() : $this->ledger->wallet($owner);
            abort_if($account->frozen, 422, 'This account is under investigation.');
            $receiptHash = $kind === 'demo_cash_deposit' ? hash('sha256', mb_strtolower(trim($details['receipt_reference']))) : null;
            abort_if($receiptHash && WalletFundingIntent::where('deposit_reference_hash', $receiptHash)->exists(), 422, 'This demo receipt reference is already recorded. Use its original request.');
            $intent = WalletFundingIntent::create(['reference' => (string) Str::uuid(), 'user_id' => $owner->id, 'account_id' => $account->id,
                'amount_cents' => $amount, 'idempotency_key' => $key, 'provider' => 'internal_demo', 'request_kind' => $kind,
                'request_details' => $details, 'deposit_reference_hash' => $receiptHash, 'expires_at' => now()->addDay()]);
            $this->audits->record($owner, $intent, 'demo_funding_requested', [], ['amount_cents' => $amount, 'kind' => $kind], $details['reason']);

            return $intent;
        }, 3);
    }

    public function review(WalletFundingIntent $intent, User $actor, string $decision, string $reason): WalletFundingIntent
    {
        $this->sandbox->assertSandbox();
        abort_unless(in_array($decision, ['approve', 'reject'], true) && mb_strlen(trim($reason)) >= 5, 422);

        return DB::transaction(function () use ($intent, $actor, $decision, $reason): WalletFundingIntent {
            $users = User::whereIn('id', [$actor->id, $intent->user_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $reviewer = $users->get($actor->id);
            abort_unless($reviewer, 403);
            $intent = WalletFundingIntent::whereKey($intent->id)->lockForUpdate()->firstOrFail();
            $intent->setRelation('user', $users->get($intent->user_id));
            abort_unless($this->canReview($reviewer, $intent), 403, 'An independent administrator for this account must review the request.');
            $status = $decision === 'approve' ? 'PAID' : 'REJECTED';
            if ($intent->reviewed_at !== null) {
                abort_unless($intent->status === $status, 409, 'This funding request already has a different decision.');

                return $intent;
            }
            abort_unless($intent->status === 'PENDING' && $intent->expires_at->isFuture(), 422, 'This funding request is expired or finalized.');
            $owner = $intent->user;
            abort_unless($owner->status === 'approved', 422, 'The requesting account is no longer approved.');
            $transaction = null;
            if ($decision === 'approve') {
                abort_unless($intent->currency === 'PHP' && $intent->amount_cents > 0 && $intent->amount_cents <= config('financial.maximum_transaction_cents')
                    && in_array($intent->request_kind, ['demo_credit', 'demo_cash_deposit'], true), 422);
                if ($intent->request_kind === 'demo_cash_deposit') {
                    abort_unless($this->validEvidence($intent->request_details), 422, 'Deposit evidence is missing or changed.');
                }
                $account = FinancialAccount::findOrFail($intent->account_id);
                $expectedKey = $owner->isCommerceAdmin() ? 'platform:treasury' : 'user:'.$owner->id.':available';
                abort_unless($account->key === $expectedKey && ($owner->isCommerceAdmin() || (int) $account->user_id === $owner->id), 422);
                $transaction = $this->ledger->transfer('demo-funding:'.$intent->id, 'demo_wallet_credit',
                    $this->ledger->account('demo:issuance', 'demo_issuance', null, 'available', true), $account, $intent->amount_cents, $reviewer,
                    ['intent_id' => $intent->id, 'kind' => $intent->request_kind, 'demo' => true]);
            }
            $intent->forceFill(['status' => $status, 'transaction_id' => $transaction?->id, 'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(), 'review_reason' => $reason])->save();
            $this->audits->record($reviewer, $intent, 'demo_funding_'.$decision, ['status' => 'PENDING'], ['status' => $status, 'transaction_id' => $transaction?->id], $reason);
            $this->notifications->send($owner, new WebOperationsNotification(['type' => 'wallet_funding', 'title' => $decision === 'approve' ? 'Demo wallet credited' : 'Demo funding declined',
                'message' => $decision === 'approve' ? 'PHP '.number_format($intent->amount_cents / 100, 2).' demo funds are available. No real money was transferred.' : $reason,
                'url' => route('finance.wallet')]));

            return $intent;
        }, 3);
    }

    public function validEvidence(array $details): bool
    {
        $path = $details['evidence_path'] ?? '';
        if (! str_starts_with($path, 'wallet-demo-deposits/') || ! Storage::disk('private')->exists($path)) {
            return false;
        }
        $stream = Storage::disk('private')->readStream($path);
        if (! is_resource($stream)) {
            return false;
        }
        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);

            return hash_equals($details['evidence_sha256'] ?? '', hash_final($hash));
        } finally {
            fclose($stream);
        }
    }
}
