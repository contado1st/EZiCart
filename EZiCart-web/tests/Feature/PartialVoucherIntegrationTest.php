<?php

namespace Tests\Feature;

use App\Models\LedgerTransaction;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\OrderWorkflowNotification;
use App\Services\CheckoutPricingService;
use App\Services\DemoWalletFundingService;
use App\Services\FinancialLedgerService;
use App\Services\OrderTransitionService;
use App\Services\PlacePurchaseService;
use App\Services\VoucherClaimService;
use App\Services\VoucherEligibilityService;
use App\Services\VoucherFundingService;
use App\Services\WalletFundingService;
use App\Services\WalletOrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PartialVoucherIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['financial.sandbox_enabled' => true]);
    }

    public function test_future_start_blocks_claim_and_pricing_and_is_not_offered_on_product_page(): void
    {
        $this->freezeTime();
        $seller = $this->user('seller');
        $voucher = $this->voucher($seller, ['starts_at' => now()->addDay()]);
        $product = $this->product($seller);
        $buyer = $this->user('buyer');
        $this->actingAs($buyer)->post(route('buyer.vouchers.claim', $voucher))->assertSessionHasErrors(['voucher_code' => 'This voucher has not started yet.']);
        $this->assertDatabaseCount('voucher_claims', 0);
        $this->get(route('buyer.vouchers.index'))->assertOk()->assertSee('This voucher has not started yet.')->assertDontSee('Claim voucher');
        $this->get(route('product.show', $product))->assertOk()->assertDontSee($voucher->code);
        $this->expectException(ValidationException::class);
        app(CheckoutPricingService::class)->quote([['product_id' => $product->id, 'quantity' => 1]], $voucher);
    }

    public function test_exact_start_is_eligible_and_exact_expiry_is_not(): void
    {
        $this->freezeTime();
        $voucher = $this->voucher($this->user('seller'), ['starts_at' => now(), 'expires_at' => now()->addHour()]);
        $eligibility = app(VoucherEligibilityService::class);
        $this->assertNull($eligibility->reason($voucher));
        $this->travel(1)->hours();
        $this->assertSame('expired', $eligibility->reason($voucher)['code']);
    }

    public function test_minimum_spend_message_reports_one_cent_and_allows_exact_boundary(): void
    {
        $seller = $this->user('seller');
        $voucher = $this->voucher($seller, ['min_spend' => 300]);
        $product = $this->product($seller, 299.99);
        $cart = [['product_id' => $product->id, 'quantity' => 1]];
        try {
            app(CheckoutPricingService::class)->quote($cart, $voucher);
            $this->fail('Minimum spend was bypassed.');
        } catch (ValidationException $exception) {
            $this->assertSame('Spend ₱0.01 more on eligible items to use this voucher.', $exception->errors()['voucher_code'][0]);
        }
        foreach ([300, 300.01] as $price) {
            $product->update(['price' => $price]);
            $this->assertSame(5000, app(CheckoutPricingService::class)->quote($cart, $voucher)['discount_cents']);
        }
        $this->assertSame(0, (int) $voucher->fresh()->used_count);
    }

    public function test_inactive_expired_and_exhausted_offers_have_separate_reasons(): void
    {
        $this->freezeTime();
        $voucher = $this->voucher($this->user('seller'));
        $eligibility = app(VoucherEligibilityService::class);
        $voucher->is_active = false;
        $this->assertSame('inactive', $eligibility->reason($voucher)['code']);
        $voucher->expires_at = now();
        $this->assertSame('expired', $eligibility->reason($voucher)['code']);
        $voucher->is_active = true;
        $voucher->expires_at = null;
        $voucher->used_count = 1;
        $voucher->usage_limit = 1;
        $this->assertSame('exhausted', $eligibility->reason($voucher)['code']);
    }

    public function test_seller_field_bounds_fail_before_creating_or_reserving_an_offer(): void
    {
        $this->freezeTime();
        $seller = $this->user('seller');
        $payload = ['code' => 'BOUNDARY', 'type' => 'fixed', 'value' => 50, 'usage_limit' => 1, 'per_buyer_limit' => 2];
        $this->actingAs($seller)->post(route('seller.vouchers.store'), $payload)->assertSessionHasErrors('per_buyer_limit');
        $this->post(route('seller.vouchers.store'), [...$payload, 'per_buyer_limit' => 1, 'starts_at' => now()->addDays(2)->toIso8601String(), 'expires_at' => now()->addDay()->toIso8601String()])->assertSessionHasErrors('expires_at');
        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('voucher_budgets', 0);
    }

    public function test_capped_hundred_percent_scheduled_offer_is_preserved_and_funded_immediately(): void
    {
        $this->freezeTime();
        $seller = $this->user('seller');
        $this->fund($seller, 20000);
        $this->actingAs($seller)->post(route('seller.vouchers.store'), ['code' => 'FUTURE100', 'type' => 'percent', 'value' => 100, 'max_discount' => 50, 'usage_limit' => 2,
            'starts_at' => now()->addDay()->toIso8601String(), 'expires_at' => now()->addDays(2)->toIso8601String(), 'per_buyer_limit' => 1,
        ])->assertSessionHasNoErrors();
        $voucher = Voucher::sole();
        $this->assertSame(10000, $voucher->budget->reserveAccount->balance_cents);
        $this->assertSame(10000, app(FinancialLedgerService::class)->wallet($seller)->balance_cents);
        $this->assertSame('scheduled', app(VoucherEligibilityService::class)->reason($voucher)['code']);
        $this->get(route('seller.vouchers.index'))->assertOk()->assertSee('data-voucher-budget', false)->assertSee('This voucher has not started yet.');
    }

    public function test_platform_schedule_and_cap_are_validated_and_non_admin_cannot_create(): void
    {
        $admin = $this->user('ecommerce_admin');
        $data = ['code' => 'PLATFORM', 'type' => 'percent', 'value' => 10, 'min_spend' => 0, 'usage_limit' => 2];
        $this->actingAs($admin)->post(route('admin.vouchers.store'), $data)->assertSessionHasErrors('max_discount');
        $this->post(route('admin.vouchers.store'), [...$data, 'max_discount' => 20, 'starts_at' => now()->addDays(2)->toIso8601String(), 'expires_at' => now()->addDay()->toIso8601String()])->assertSessionHasErrors('expires_at');
        $this->flushSession();
        $this->actingAs($this->user('seller'))->post(route('admin.vouchers.store'), $data)->assertForbidden();
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_existing_claim_cannot_be_rescheduled_and_limits_use_the_existing_total(): void
    {
        $seller = $this->user('seller');
        $voucher = $this->voucher($seller, ['usage_limit' => 1]);
        app(VoucherClaimService::class)->claim($this->user('buyer'), $voucher);
        $this->actingAs($seller)->patch(route('seller.vouchers.limits', $voucher), ['per_buyer_limit' => 2])->assertSessionHasErrors('per_buyer_limit');
        $this->patch(route('seller.vouchers.limits', $voucher), ['starts_at' => now()->addDay()->toIso8601String()])->assertSessionHasErrors('starts_at');
        $this->assertNull($voucher->fresh()->starts_at);
    }

    public function test_saved_unavailable_offers_remain_history_without_being_claimable(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $voucher = $this->voucher($seller);
        app(VoucherClaimService::class)->claim($buyer, $voucher);
        $voucher->update(['is_active' => false]);
        $other = $this->voucher($seller, ['code' => 'SOMEONE-ELSES-SAVED']);
        app(VoucherClaimService::class)->claim($this->user('buyer'), $other);
        $other->update(['is_active' => false]);
        $this->actingAs($buyer)->get(route('buyer.vouchers.index', ['view' => 'saved']))->assertOk()->assertSee($voucher->code)->assertSee('This voucher is paused or has ended.')->assertDontSee($other->code)->assertDontSee('Claim voucher');
        $this->assertDatabaseCount('voucher_claims', 2);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_approved_wallet_cancellation_restores_stock_usage_and_exhausted_funded_offer_once(bool $internalDemo): void
    {
        Notification::fake();
        [$buyer, $seller, $voucher, $order, $product] = $this->fundedPurchase(internalDemo: $internalDemo);
        $service = app(OrderTransitionService::class);
        $this->assertFalse($voucher->fresh()->is_active);
        $this->assertSame('exhausted', $voucher->fresh()->disabled_reason);
        $service->requestCancellation($order, $buyer, 'Changed purchase decision.');
        $this->assertSame(4, (int) $product->fresh()->stock);
        $this->assertSame(1, (int) $voucher->fresh()->used_count);
        $this->assertSame(10000, app(FinancialLedgerService::class)->wallet($buyer)->balance_cents);
        $this->assertTrue($service->resolveCancellation($order, $seller, true, 'Approved before pickup.'));
        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertSame(0, (int) $voucher->fresh()->used_count);
        $this->assertTrue($voucher->fresh()->is_active);
        $this->assertSame(5000, $voucher->fresh()->budget->reserveAccount->balance_cents);
        $this->assertSame(40000, app(FinancialLedgerService::class)->wallet($buyer)->balance_cents);
        $this->assertFalse($service->resolveCancellation($order, $seller, true, 'Duplicate decision.'));
        $this->assertSame(40000, app(FinancialLedgerService::class)->wallet($buyer)->balance_cents);
        Notification::assertSentTo($buyer, OrderWorkflowNotification::class, fn ($notice) => $notice->eventType === 'cancellation_approved' && str_contains($notice->message, 'wallet payment credited back: PHP 300.00'));
        $this->assertTrue(app(FinancialLedgerService::class)->reconcile()['balanced']);
    }

    public function test_manually_paused_offer_stays_paused_after_cancellation_and_money_returns_to_sponsor(): void
    {
        [$buyer, $seller, $voucher, $order] = $this->fundedPurchase(2);
        app(VoucherFundingService::class)->release($voucher, $seller);
        $this->assertSame('paused', $voucher->fresh()->disabled_reason);
        $service = app(OrderTransitionService::class);
        $service->requestCancellation($order, $buyer, 'Cancel before shipment.');
        $service->resolveCancellation($order, $seller, true, 'Approved before pickup.');
        $this->assertFalse($voucher->fresh()->is_active);
        $this->assertSame(10000, app(FinancialLedgerService::class)->wallet($seller)->balance_cents);
        $this->assertSame(0, $voucher->fresh()->budget->reserveAccount->balance_cents);
    }

    public function test_expired_offer_is_not_renewed_by_cancellation(): void
    {
        [$buyer, $seller, $voucher, $order] = $this->fundedPurchase();
        $voucher->update(['expires_at' => now()->subMinute()]);
        $service = app(OrderTransitionService::class);
        $service->requestCancellation($order, $buyer, 'Cancel before shipment.');
        $service->resolveCancellation($order, $seller, true, 'Approved before pickup.');
        $this->assertFalse($voucher->fresh()->is_active);
        $this->assertSame('expired', app(VoucherEligibilityService::class)->reason($voucher->fresh())['code']);
        $this->assertSame(5000, app(FinancialLedgerService::class)->wallet($seller)->balance_cents);
    }

    public function test_returned_discount_below_cap_does_not_reactivate_an_underfunded_offer(): void
    {
        [$buyer, $seller, $voucher, $order] = $this->fundedPurchase(1, 20);
        $service = app(OrderTransitionService::class);
        $service->requestCancellation($order, $buyer, 'Cancel before shipment.');
        $service->resolveCancellation($order, $seller, true, 'Approved before pickup.');
        $this->assertSame(0, (int) $voucher->fresh()->used_count);
        $this->assertFalse($voucher->fresh()->is_active);
        $this->assertSame('funding', app(VoucherEligibilityService::class)->reason($voucher->fresh())['code']);
        $this->assertSame(5000, app(FinancialLedgerService::class)->wallet($seller)->balance_cents);
        $this->assertTrue(app(FinancialLedgerService::class)->reconcile()['balanced']);
    }

    public function test_frozen_reserved_budget_is_not_offered_on_product_page_or_claimable(): void
    {
        $seller = $this->user('seller');
        $this->fund($seller, 5000);
        $voucher = $this->voucher($seller, ['usage_limit' => 1]);
        app(VoucherFundingService::class)->reserve($voucher, $seller);
        $product = $this->product($seller);
        $this->get(route('product.show', $product))->assertOk()->assertSee($voucher->code);
        $voucher->budget->reserveAccount->update(['frozen' => true]);
        $this->get(route('product.show', $product))->assertOk()->assertDontSee($voucher->code);
        $this->actingAs($this->user('buyer'))->post(route('buyer.vouchers.claim', $voucher))->assertSessionHasErrors('voucher_code');
        $this->assertDatabaseCount('voucher_claims', 0);
        $this->assertSame(0, (int) $voucher->fresh()->used_count);
    }

    #[TestWith(['cancel'])]
    #[TestWith(['expire'])]
    public function test_unpaid_reservation_releases_voucher_budget_usage_and_inventory_once(string $action): void
    {
        [$buyer, $seller, $voucher, $order, $product] = $this->fundedPurchase(internalDemo: true, payNow: false);
        $this->assertTrue($order->isAwaitingWalletPayment());
        $this->assertSame(40000, app(FinancialLedgerService::class)->wallet($buyer)->balance_cents);
        if ($action === 'cancel') {
            app(WalletOrderPaymentService::class)->cancel($order, $buyer);
        } else {
            $this->travel(30)->minutes();
            $this->artisan('wallet:expire-orders')->assertSuccessful();
        }
        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(0, $voucher->fresh()->used_count);
        $this->assertTrue($voucher->fresh()->is_active);
        $this->assertSame(5000, $voucher->fresh()->budget->reserveAccount->balance_cents);
        $this->assertSame(40000, app(FinancialLedgerService::class)->wallet($buyer)->balance_cents);
        $this->assertFalse(app(OrderTransitionService::class)->expireWalletPayment($order));
        $this->assertSame(0, LedgerTransaction::where('type', 'cancelled_order_refund')->count());
        $this->assertTrue(app(FinancialLedgerService::class)->reconcile()['balanced']);
    }

    private function fundedPurchase(int $limit = 1, float $price = 300, bool $internalDemo = false, bool $payNow = true): array
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $this->fund($seller, 5000 * $limit, $internalDemo);
        $this->fund($buyer, 40000, $internalDemo);
        $voucher = $this->voucher($seller, ['usage_limit' => $limit]);
        app(VoucherFundingService::class)->reserve($voucher, $seller);
        $product = $this->product($seller, $price);
        $cart = [['product_id' => $product->id, 'quantity' => 1]];
        $address = ['recipient_name' => 'Voucher Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Synthetic test address'];
        $quote = app(CheckoutPricingService::class)->quote($cart, $voucher);
        $order = app(PlacePurchaseService::class)->place($buyer, (string) Str::uuid(), 'voucher-test', $cart, $quote['hash'], $voucher->id, $address, 'WALLET', $internalDemo || ! $payNow)->orders()->firstOrFail();
        if ($internalDemo && $payNow) {
            app(WalletOrderPaymentService::class)->pay($order, $buyer);
        }

        return [$buyer, $seller, $voucher, $order, $product];
    }

    private function fund(User $owner, int $cents, bool $internalDemo = false): void
    {
        if ($internalDemo) {
            $funding = app(DemoWalletFundingService::class);
            $intent = $funding->request($owner, $cents, (string) Str::uuid(), 'demo_credit', ['reason' => 'School demo voucher funding.']);
            $funding->review($intent, $this->user('ecommerce_admin'), 'approve', 'Independent demo funding review.');

            return;
        }
        $funding = app(WalletFundingService::class);
        $intent = $funding->create($owner, $cents, (string) Str::uuid());
        $funding->simulate($intent, $this->user('ecommerce_admin'), 'paid');
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Voucher', 'last_name' => 'Tester', 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion']);
    }

    private function voucher(User $seller, array $extra = []): Voucher
    {
        return Voucher::create(['seller_id' => $seller->id, 'code' => 'VOUCHER-'.Str::random(6), 'type' => 'fixed', 'value' => 50, 'min_spend' => 0, 'usage_limit' => 3, 'is_active' => true, ...$extra]);
    }

    private function product(User $seller, float $price = 300): Product
    {
        return Product::forceCreate(['user_id' => $seller->id, 'name' => 'Voucher boundary product', 'category' => 'Pet Supplies', 'price' => $price, 'stock' => 5, 'weight_grams' => 1000, 'compliance_status' => 'approved', 'is_archived' => false]);
    }
}
