<?php

namespace Tests\Feature;

use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\WalletFundingIntent;
use App\Notifications\WebOperationsNotification;
use App\Services\FinancialLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DemoWalletFundingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->freezeTime();
        config(['financial.sandbox_enabled' => true]);
        Storage::fake('private');
        Notification::fake();
    }

    #[TestWith(['demo_credit'])]
    #[TestWith(['demo_cash_deposit'])]
    public function test_independent_approval_credits_demo_funds_once_and_preserves_immutable_history(string $kind): void
    {
        $buyer = $this->user('buyer');
        $admin = $this->user('ecommerce_admin');
        $data = $this->data($kind);
        $this->signIn($buyer)->post(route('wallet.demo-funding.store'), $data)->assertRedirect();
        $intent = WalletFundingIntent::firstOrFail();
        $ledger = app(FinancialLedgerService::class);
        $this->assertSame(0, $ledger->wallet($buyer)->balance_cents);
        $this->assertSame('internal_demo', $intent->provider);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->get(route('finance.wallet'))->assertOk()->assertSee('Add demo funds')->assertSee('PENDING');
        $this->signIn($admin)->post(route('wallet.demo-funding.review', $intent), ['decision' => 'approve', 'reason' => 'Checked school demo funding request.'])->assertRedirect();
        $this->post(route('wallet.demo-funding.review', $intent), ['decision' => 'approve', 'reason' => 'Replay must not issue another credit.'])->assertRedirect();
        $this->assertSame(20000, $ledger->wallet($buyer)->balance_cents);
        $this->assertSame('PAID', $intent->fresh()->status);
        $this->assertSame($admin->id, $intent->fresh()->reviewed_by);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertSame('demo_wallet_credit', LedgerTransaction::firstOrFail()->type);
        $this->assertTrue($ledger->reconcile()['balanced']);
        Notification::assertSentToTimes($buyer, WebOperationsNotification::class, 1);
        $this->postJson(route('wallet.demo-funding.review', $intent), ['decision' => 'reject', 'reason' => 'Conflicting later decision.'])->assertConflict();
        $this->expectException(\LogicException::class);
        $intent->fresh()->update(['amount_cents' => 1]);
    }

    public function test_demo_receipt_is_private_and_reused_reference_or_changed_evidence_cannot_be_credited(): void
    {
        $buyer = $this->user('buyer');
        $admin = $this->user('ecommerce_admin');
        $other = $this->user('buyer');
        $this->signIn($buyer)->post(route('wallet.demo-funding.store'), $this->data('demo_cash_deposit'))->assertRedirect();
        $intent = WalletFundingIntent::firstOrFail();
        $this->get(route('wallet.demo-funding.evidence', $intent))->assertOk()->assertDownload();
        $this->signIn($other)->get(route('wallet.demo-funding.evidence', $intent))->assertNotFound();
        $this->postJson(route('wallet.demo-funding.store'), $this->data('demo_cash_deposit'))->assertUnprocessable();
        $this->assertDatabaseCount('wallet_funding_intents', 1);
        $this->assertCount(1, Storage::disk('private')->allFiles('wallet-demo-deposits'));
        Storage::disk('private')->put($intent->request_details['evidence_path'], 'changed bytes');
        $this->signIn($admin)->postJson(route('wallet.demo-funding.review', $intent), ['decision' => 'approve', 'reason' => 'Must verify the original receipt.'])->assertUnprocessable();
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertSame('PENDING', $intent->fresh()->status);
    }

    #[TestWith(['buyer', 'logistics_admin'])]
    #[TestWith(['courier', 'ecommerce_admin'])]
    #[TestWith(['seller', 'courier'])]
    public function test_review_and_evidence_access_follow_the_account_administrator(string $ownerRole, string $reviewerRole): void
    {
        $owner = $this->user($ownerRole);
        $reviewer = $this->user($reviewerRole);
        $this->signIn($owner)->post(route('wallet.demo-funding.store'), $this->data('demo_cash_deposit'))->assertRedirect();
        $intent = WalletFundingIntent::firstOrFail();
        $this->signIn($reviewer)->postJson(route('wallet.demo-funding.review', $intent), ['decision' => 'approve', 'reason' => 'Wrong administrator role.'])->assertForbidden();
        $this->get(route('wallet.demo-funding.evidence', $intent))->assertNotFound();
        $this->get(route('finance.wallet'))->assertOk()->assertDontSee($intent->reference);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_logistics_admin_can_approve_rider_funds_and_an_admin_cannot_approve_own_treasury_request(): void
    {
        $rider = $this->user('courier');
        $admin = $this->user('logistics_admin');
        $this->signIn($rider)->post(route('wallet.demo-funding.store'), $this->data())->assertRedirect();
        $intent = WalletFundingIntent::firstOrFail();
        $this->signIn($admin)->post(route('wallet.demo-funding.review', $intent), ['decision' => 'approve', 'reason' => 'Approved demo rider funding.'])->assertRedirect();
        $this->assertSame(20000, app(FinancialLedgerService::class)->wallet($rider)->balance_cents);
        $commerce = $this->user('ecommerce_admin');
        $this->signIn($commerce)->post(route('wallet.demo-funding.store'), $this->data())->assertRedirect();
        $own = WalletFundingIntent::latest('id')->firstOrFail();
        $this->postJson(route('wallet.demo-funding.review', $own), ['decision' => 'approve', 'reason' => 'Self approval must be blocked.'])->assertForbidden();
        $this->assertSame(0, app(FinancialLedgerService::class)->treasury()->balance_cents);
    }

    public function test_rejection_and_expiry_do_not_credit_and_duplicate_requests_do_not_add_history(): void
    {
        $buyer = $this->user('buyer');
        $admin = $this->user('ecommerce_admin');
        $data = $this->data();
        $this->signIn($buyer)->post(route('wallet.demo-funding.store'), $data)->assertRedirect();
        $this->post(route('wallet.demo-funding.store'), $data)->assertRedirect();
        $this->postJson(route('wallet.demo-funding.store'), [...$data, 'amount' => 201])->assertConflict();
        $this->assertDatabaseCount('wallet_funding_intents', 1);
        $intent = WalletFundingIntent::firstOrFail();
        $this->signIn($admin)->post(route('wallet.demo-funding.review', $intent), ['decision' => 'reject', 'reason' => 'Demo request declined for review.'])->assertRedirect();
        $this->assertSame('REJECTED', $intent->fresh()->status);
        $this->signIn($buyer)->post(route('wallet.demo-funding.store'), $this->data())->assertRedirect();
        $expired = WalletFundingIntent::latest('id')->firstOrFail();
        $this->travel(24)->hours();
        $this->signIn($admin)->postJson(route('wallet.demo-funding.review', $expired), ['decision' => 'approve', 'reason' => 'Expired request cannot be credited.'])->assertUnprocessable();
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    #[TestWith(['disabled'])]
    #[TestWith(['live'])]
    #[TestWith(['production'])]
    public function test_cash_deposit_requires_receipt_fields_and_disabled_demo_mode_cleans_up_uploads(string $mode): void
    {
        $buyer = $this->user('buyer');
        $data = $this->data();
        $this->signIn($buyer)->post(route('wallet.demo-funding.store'), [...$data, 'kind' => 'demo_cash_deposit'])->assertSessionHasErrors(['evidence', 'receipt_reference']);
        if ($mode === 'disabled') {
            config(['financial.sandbox_enabled' => false]);
        }
        if ($mode === 'live') {
            config(['financial.mode' => 'live']);
        }
        if ($mode === 'production') {
            $this->app['env'] = 'production';
        }
        $csrf = Str::random(40);
        $this->withSession(['_token' => $csrf])->postJson(route('wallet.demo-funding.store'), [...$this->data('demo_cash_deposit'), '_token' => $csrf])->assertStatus(503);
        $this->assertSame([], Storage::disk('private')->allFiles('wallet-demo-deposits'));
        $this->assertDatabaseCount('wallet_funding_intents', 0);
    }

    public function test_legacy_mock_simulation_cannot_bypass_independent_review_for_an_internal_request(): void
    {
        $admin = $this->user('ecommerce_admin');
        $this->signIn($admin)->post(route('wallet.demo-funding.store'), $this->data())->assertRedirect();
        $intent = WalletFundingIntent::firstOrFail();
        $this->postJson(route('finance.wallet.simulate', $intent), ['result' => 'paid'])->assertUnprocessable();
        $this->assertSame('PENDING', $intent->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    private function data(string $kind = 'demo_credit'): array
    {
        return ['amount' => 200, 'kind' => $kind, 'request_key' => (string) Str::uuid(), 'reason' => 'School wallet flow testing.']
            + ($kind === 'demo_cash_deposit' ? ['receipt_reference' => 'DEMO-RECEIPT-ONE', 'evidence' => UploadedFile::fake()->image('demo-receipt.png')] : []);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.test', 'password' => 'Password123!', 'first_name' => 'Demo', 'last_name' => 'Tester', 'role' => $role, 'status' => 'approved',
            'email_verified_at' => now(), 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro']);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();
        Auth::forgetGuards();

        return $this->actingAs($user);
    }
}
