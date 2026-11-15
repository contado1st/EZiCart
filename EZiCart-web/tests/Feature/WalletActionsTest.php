<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\DemoFundingCode;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use App\Services\DemoFundingCodeService;
use App\Services\FinancialLedgerService;
use App\Services\WalletTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class WalletActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->freezeTime();
        config(['financial.sandbox_enabled' => true]);
    }

    public function test_code_issuance_does_not_credit_until_the_named_recipient_redeems_once(): void
    {
        Notification::fake();
        $admin = $this->user('ecommerce_admin');
        $buyer = $this->user('buyer');
        $other = $this->user('buyer');
        $ledger = app(FinancialLedgerService::class);
        $service = app(DemoFundingCodeService::class);
        [$code, $secret] = $service->issue($admin, $buyer, 15000, (string) Str::uuid(), 60, 'School test funding');
        $this->assertSame(0, $ledger->wallet($buyer)->balance_cents);
        $this->assertSame(hash('sha256', $secret), $code->code_hash);
        $this->assertStringNotContainsString($secret, $code->toJson());
        $this->signIn($other)->postJson(route('wallet.codes.redeem'), ['code' => $secret])->assertUnprocessable();
        $this->signIn($buyer)->postJson(route('wallet.codes.redeem'), ['code' => $secret])->assertOk()->assertJsonPath('data.status', 'Redeemed');
        $this->postJson(route('wallet.codes.redeem'), ['code' => $secret])->assertOk();
        $this->assertSame(15000, $ledger->wallet($buyer)->balance_cents);
        $this->assertSame(1, LedgerTransaction::where('type', 'demo_code_credit')->count());
        $this->assertTrue($ledger->reconcile()['balanced']);
        Notification::assertSentTo($buyer, WebOperationsNotification::class);
    }

    #[TestWith(['expired'])]
    #[TestWith(['revoked'])]
    #[TestWith(['frozen'])]
    public function test_expired_revoked_or_frozen_codes_cannot_add_funds(string $condition): void
    {
        $admin = $this->user('ecommerce_admin');
        $buyer = $this->user('buyer');
        $service = app(DemoFundingCodeService::class);
        $ledger = app(FinancialLedgerService::class);
        [$code, $secret] = $service->issue($admin, $buyer, 5000, (string) Str::uuid(), 15, 'School test funding');
        if ($condition === 'expired') {
            $this->travel(16)->minutes();
        }
        if ($condition === 'revoked') {
            $service->revoke($admin, $code);
        }
        if ($condition === 'frozen') {
            $ledger->wallet($buyer)->update(['frozen' => true]);
        }
        $this->signIn($buyer)->postJson(route('wallet.codes.redeem'), ['code' => $secret])->assertUnprocessable();
        $this->assertSame(0, $ledger->wallet($buyer)->balance_cents);
        $this->assertSame(0, LedgerTransaction::where('type', 'demo_code_credit')->count());
    }

    public function test_issuer_sees_the_code_once_and_other_accounts_cannot_read_its_receipt(): void
    {
        $admin = $this->user('ecommerce_admin');
        $buyer = $this->user('buyer');
        $reference = app(WalletTransferService::class)->reference($buyer);
        $payload = ['recipient_reference' => $reference, 'amount' => '10.00', 'request_key' => (string) Str::uuid(), 'expires_minutes' => 60, 'reason' => 'Demonstration funding'];
        $this->signIn($admin)->post(route('wallet.codes.issue'), $payload)->assertRedirect();
        $code = DemoFundingCode::firstOrFail();
        $secret = session('demo_code_once.secret');
        $this->assertIsString($secret);
        $this->get(route('wallet.codes.receipt', $code->reference))->assertOk()->assertSee($secret)->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('wallet.codes.receipt', $code->reference))->assertOk()->assertDontSee($secret);
        $this->signIn($this->user('buyer'))->get(route('wallet.codes.receipt', $code->reference))->assertNotFound();
        $this->signIn($admin)->post(route('wallet.codes.issue'), $payload)->assertRedirect();
        $this->assertDatabaseCount('demo_funding_codes', 1);
    }

    public function test_transfers_review_the_identity_and_post_one_balanced_transaction_on_retry(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $this->fund($buyer, 10000);
        $payload = $this->review($buyer, $seller, '25.00');
        $this->postJson(route('wallet.transfer.send'), $payload)->assertOk()->assertJsonPath('available_cents', 7500);
        $this->postJson(route('wallet.transfer.send'), $payload)->assertOk()->assertJsonPath('available_cents', 7500);
        $ledger = app(FinancialLedgerService::class);
        $this->assertSame(2500, $ledger->wallet($seller)->balance_cents);
        $this->assertSame(1, LedgerTransaction::where('type', 'wallet_transfer')->count());
        $this->assertTrue($ledger->reconcile()['balanced']);
    }

    #[TestWith(['password'])]
    #[TestWith(['amount'])]
    #[TestWith(['recipient'])]
    #[TestWith(['expired'])]
    #[TestWith(['frozen'])]
    #[TestWith(['insufficient'])]
    public function test_invalid_transfer_leaves_both_wallets_unchanged(string $condition): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $this->fund($buyer, 10000);
        $payload = $this->review($buyer, $seller, $condition === 'insufficient' ? '200.00' : '25.00');
        if ($condition === 'password') {
            $payload['password'] = 'wrong';
        }
        if ($condition === 'amount') {
            $payload['amount'] = '24.00';
        }
        if ($condition === 'recipient') {
            $payload['recipient_reference'] = app(WalletTransferService::class)->reference($this->user('buyer'));
        }
        if ($condition === 'expired') {
            $this->travel(11)->minutes();
        }
        if ($condition === 'frozen') {
            app(FinancialLedgerService::class)->wallet($seller)->update(['frozen' => true]);
        }
        $this->postJson(route('wallet.transfer.send'), $payload)->assertUnprocessable();
        $this->assertSame(10000, app(FinancialLedgerService::class)->wallet($buyer)->balance_cents);
        $this->assertSame(0, app(FinancialLedgerService::class)->wallet($seller)->balance_cents);
        $this->assertSame(0, LedgerTransaction::where('type', 'wallet_transfer')->count());
    }

    public function test_admin_allocation_debits_treasury_and_can_reach_other_user_modules(): void
    {
        $admin = $this->user('ecommerce_admin');
        $buyer = $this->user('buyer');
        $rider = $this->user('courier');
        $ledger = app(FinancialLedgerService::class);
        $ledger->transfer('test-treasury', 'fixture', $ledger->account('demo:fixture', 'demo_issuance', null, 'available', true), $ledger->treasury(), 10000, $admin);
        $payload = $this->review($admin, $buyer, '30.00');
        $this->postJson(route('wallet.transfer.send'), $payload)->assertOk();
        $this->assertSame(7000, $ledger->treasury()->balance_cents);
        $this->assertSame(0, $ledger->wallet($admin)->balance_cents);
        $this->assertSame(3000, $ledger->wallet($buyer)->balance_cents);
        $riderAllocation = $this->review($admin, $rider, '10.00');
        $this->postJson(route('wallet.transfer.send'), $riderAllocation)->assertOk();
        $this->assertSame(6000, $ledger->treasury()->balance_cents);
        $this->assertSame(1000, $ledger->wallet($rider)->balance_cents);
    }

    public function test_buyer_cannot_issue_codes_and_wrong_admin_scope_is_refused(): void
    {
        $buyer = $this->user('buyer');
        $rider = $this->user('courier');
        $payload = ['recipient_reference' => app(WalletTransferService::class)->reference($rider), 'amount' => '10.00', 'request_key' => (string) Str::uuid(), 'expires_minutes' => 60, 'reason' => 'Demo code test'];
        $this->signIn($buyer)->postJson(route('wallet.codes.issue'), $payload)->assertForbidden();
        $this->signIn($this->user('ecommerce_admin'))->postJson(route('wallet.codes.issue'), $payload)->assertForbidden();
        $this->assertDatabaseCount('demo_funding_codes', 0);
    }

    public function test_mobile_wallet_api_is_additive_and_receipts_are_private(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $other = $this->user('buyer');
        $this->fund($buyer, 10000);
        $token = ApiToken::createTokenForUser($buyer)['plainTextToken'];
        foreach (['/api/wallet', '/api/v1/wallet'] as $walletUrl) {
            $this->withToken($token)->getJson($walletUrl)->assertOk()
                ->assertJsonPath('data.available_cents', 10000)->assertJsonPath('data.mode', 'demo')
                ->assertJsonPath('data.balances.available_cents', 10000)
                ->assertJsonPath('data.receive_reference', app(WalletTransferService::class)->reference($buyer))
                ->assertJsonStructure(['data' => ['balances', 'entries', 'funding_intents', 'withdrawals', 'pagination']])
                ->assertHeader('Cache-Control', 'no-store, private');
        }
        $data = ['recipient_reference' => app(WalletTransferService::class)->reference($seller), 'amount' => '25.00', 'request_key' => (string) Str::uuid()];
        $review = $this->postJson('/api/wallet/send/review', $data)->assertOk()->json('data');
        $result = $this->postJson('/api/wallet/send', $data + ['review_expires' => $review['review_expires'], 'review_hash' => $review['review_hash'], 'password' => 'Password123!'])->assertOk();
        $reference = $result->json('data.reference');
        $this->getJson('/api/wallet/transfers/'.$reference)->assertOk()->assertJsonPath('data.amount_cents', 2500);
        $this->withToken(ApiToken::createTokenForUser($other)['plainTextToken'])->getJson('/api/wallet/transfers/'.$reference)->assertNotFound();
    }

    public function test_wallets_render_with_short_tasks_and_the_sandbox_gate_refuses_new_writes(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $this->signIn($buyer)->get(route('finance.wallet'))->assertOk()->assertSee('data-task-open="wallet-send"', false)->assertSee('Your demo wallet')->assertSee('Code receipts');
        config(['financial.sandbox_enabled' => false, 'financial.mode' => 'live']);
        $this->postJson(route('wallet.transfer.preview'), ['recipient_reference' => app(WalletTransferService::class)->reference($seller), 'amount' => '10.00', 'request_key' => (string) Str::uuid()])->assertStatus(503);
        $this->assertSame(0, LedgerTransaction::where('type', 'wallet_transfer')->count());
    }

    private function review(User $sender, User $recipient, string $amount): array
    {
        $data = ['recipient_reference' => app(WalletTransferService::class)->reference($recipient), 'amount' => $amount, 'request_key' => (string) Str::uuid(), 'note' => 'School demo'];
        $review = $this->signIn($sender)->postJson(route('wallet.transfer.preview'), $data)->assertOk()->json('data');

        return $data + ['review_expires' => $review['review_expires'], 'review_hash' => $review['review_hash'], 'password' => 'Password123!'];
    }

    private function fund(User $user, int $amount): void
    {
        $ledger = app(FinancialLedgerService::class);
        $ledger->transfer('test-fund:'.$user->id, 'fixture', $ledger->account('demo:fixture', 'demo_issuance', null, 'available', true), $ledger->wallet($user), $amount, $user);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['first_name' => 'Wallet', 'last_name' => ucfirst($role), 'role' => $role, 'status' => 'approved', 'email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'contact_no' => '09'.fake()->unique()->numerify('#########')]);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();
        Auth::forgetGuards();

        return $this->actingAs($user);
    }
}
