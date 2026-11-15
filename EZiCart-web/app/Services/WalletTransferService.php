<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletTransferService
{
    public const USER_ROLES = ['buyer', 'seller', 'courier', 'sorting_center'];

    public function __construct(private FinancialLedgerService $ledger, private WalletFundingService $sandbox,
        private AdministrativeAuditService $audits,
        private TransactionAwareNotificationSender $notifications) {}

    public function isAdmin(User $actor): bool
    {
        return $actor->isCommerceAdmin() || $actor->isLogisticsAdmin();
    }

    public function source(User $actor): FinancialAccount
    {
        abort_unless($actor->status === 'approved' && (in_array($actor->role, self::USER_ROLES, true) || $this->isAdmin($actor)), 403);

        return $actor->isCommerceAdmin() ? $this->ledger->treasury() : $this->ledger->wallet($actor);
    }

    public function reference(User $recipient): string
    {
        abort_unless($recipient->status === 'approved' && in_array($recipient->role, self::USER_ROLES, true), 422, 'This account does not receive personal wallet transfers.');

        return 'EZW:'.$recipient->id.':'.substr(hash_hmac('sha256', 'wallet-recipient:'.$recipient->id, (string) config('app.key')), 0, 24);
    }

    public function recipient(User $actor, string $reference): User
    {
        $this->source($actor);
        preg_match('/^EZW:(\d+):([a-f0-9]{24})$/', trim($reference), $parts);
        $recipient = isset($parts[1]) ? User::find((int) $parts[1]) : null;
        abort_unless($recipient && $recipient->status === 'approved' && in_array($recipient->role, self::USER_ROLES, true), 422, 'Recipient reference is unavailable. Ask the recipient to copy their Receive reference.');
        abort_unless(hash_equals($this->reference($recipient), trim($reference)), 422, 'Recipient reference is unavailable.');
        abort_if($actor->id === $recipient->id, 422, 'Choose another account; you cannot send funds to yourself.');

        // Admin allocations can reach approved user modules; treasury recipients remain excluded.
        return $recipient;
    }

    public function review(User $actor, string $reference, int $amount, string $key, string $note): array
    {
        $this->sandbox->assertSandbox();
        abort_unless($amount > 0 && $amount <= config('financial.maximum_transaction_cents') && Str::isUuid($key) && mb_strlen($note) <= 200, 422);
        $recipient = $this->recipient($actor, $reference);
        abort_if($this->source($actor)->frozen || $this->ledger->wallet($recipient)->frozen, 422, 'A wallet is frozen. This transfer cannot proceed.');
        $expires = now()->addMinutes(10)->timestamp;

        return ['recipient' => $recipient, 'amount_cents' => $amount, 'reference' => $reference, 'request_key' => $key, 'note' => $note,
            'review_expires' => $expires, 'review_hash' => $this->signature($actor, $recipient, $amount, $key, $note, $expires)];
    }

    private function signature(User $actor, User $recipient, int $amount, string $key, string $note, int $expires): string
    {
        return hash_hmac('sha256', json_encode([$actor->id, $recipient->id, $amount, $key, $note, $expires], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function send(User $actor, string $reference, int $amount, string $key, string $note, int $expires, string $signature): LedgerTransaction
    {
        $this->sandbox->assertSandbox();
        abort_unless($amount > 0 && $amount <= config('financial.maximum_transaction_cents') && Str::isUuid($key) && mb_strlen($note) <= 200, 422);

        return DB::transaction(function () use ($actor, $reference, $amount, $key, $note, $expires, $signature): LedgerTransaction {
            // SQLite has no row locks; acquire its writer lock before reading the transfer state.
            if (DB::getDriverName() === 'sqlite') {
                DB::table('users')->where('id', $actor->id)->update(['updated_at' => DB::raw('updated_at')]);
            }
            $recipient = $this->recipient($actor, $reference);
            $users = User::whereIn('id', [$actor->id, $recipient->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($actor->id);
            $recipient = $this->recipient($actor, $reference);
            abort_unless(hash_equals($this->signature($actor, $recipient, $amount, $key, $note, $expires), $signature), 422, 'Transfer details changed. Review the recipient and amount again.');
            $ledgerKey = 'wallet-send:'.$actor->id.':'.$key;
            $existing = LedgerTransaction::where('idempotency_key', $ledgerKey)->first();
            abort_unless($existing || ($expires > now()->timestamp && $expires <= now()->addMinutes(10)->timestamp), 422, 'This transfer review expired. Review the details again.');
            $from = $this->source($actor);
            $to = $this->ledger->wallet($recipient);
            $transaction = $this->ledger->transfer($ledgerKey, $this->isAdmin($actor) ? 'admin_wallet_allocation' : 'wallet_transfer', $from, $to, $amount, $actor,
                ['sender_id' => $actor->id, 'recipient_id' => $recipient->id, 'amount_cents' => $amount, 'note' => $note, 'demo' => true,
                    'sender_name' => $existing?->metadata['sender_name'] ?? trim($actor->first_name.' '.$actor->last_name),
                    'recipient_name' => $existing?->metadata['recipient_name'] ?? trim($recipient->first_name.' '.$recipient->last_name)]);
            if (! $existing) {
                $this->audits->record($actor, $transaction, $transaction->type, [], ['amount_cents' => $amount, 'recipient_id' => $recipient->id], $note ?: 'Confirmed demo wallet transfer.');
                foreach ([$actor, $recipient] as $user) {
                    $this->notifications->send($user, new WebOperationsNotification([
                        'message' => 'A demo wallet transfer of PHP '.number_format($amount / 100, 2).' was posted. Reference '.$transaction->reference,
                        'url' => route('wallet.transfer.receipt', $transaction->reference),
                    ]));
                }
            }

            return $transaction;
        }, 3);
    }
}
