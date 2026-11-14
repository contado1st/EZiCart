<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutPricingService;
use App\Services\FinancialLedgerService;
use App\Services\OrderTransitionService;
use App\Services\PlacePurchaseService;
use App\Services\ProductPriceService;
use App\Services\ProductSaleService;
use App\Services\VoucherClaimService;
use App\Services\VoucherFundingService;
use App\Services\WalletOrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MissingSalesVoucherLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->freezeSecond();
        Notification::fake();
        config(['financial.sandbox_enabled' => true]);
    }

    public function test_sale_starts_and_ends_at_exact_boundaries_in_product_search_cart_and_quote(): void
    {
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $product = $this->product($seller);
        $sale = $this->sale($seller, $product);
        $this->assertSame(45000, $this->quote($product)['lines'][0]['unit_cents']);
        $this->as($buyer)->post(route('cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->travelTo($sale->starts_at);
        $this->assertSame(36000, $this->quote($product)['lines'][0]['unit_cents']);
        $this->get(route('product.show', $product))->assertOk()->assertSee('360.00')->assertSee('450.00')->assertSee('20% off');
        $this->get(route('search.index', ['search' => $product->name, 'min_price' => 359, 'max_price' => 361]))->assertOk()->assertViewHas('products', fn ($rows) => $rows->total() === 1);
        $this->get(route('cart.index'))->assertOk()->assertViewHas('cart', fn ($cart) => array_values($cart)[0]['unit_cents'] === 36000);
        $this->travelTo($sale->ends_at);
        $this->assertSame(45000, $this->quote($product)['lines'][0]['unit_cents']);
        $this->get(route('product.show', $product))->assertOk()->assertDontSee('20% off');
    }

    #[TestWith(['percent', 0])]
    #[TestWith(['percent', 100])]
    #[TestWith(['percent', 10.5])]
    #[TestWith(['fixed', 450])]
    #[TestWith(['fixed', 451])]
    public function test_invalid_sale_values_leave_no_offer(string $kind, float $value): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $this->as($seller)->post(route('seller.sales.store'), [...$this->saleData($product), 'kind' => $kind, 'value' => $value])->assertSessionHasErrors('value');
        $this->assertDatabaseCount('product_sales', 0);
    }

    public function test_sale_dates_overlap_and_repeated_submit_are_checked_under_product_ownership(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $data = $this->saleData($product);
        $this->as($seller)->post(route('seller.sales.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('seller.sales.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_sales', 1);
        $this->post(route('seller.sales.store'), [...$data, 'value' => 30])->assertConflict();
        $this->post(route('seller.sales.store'), [...$data, 'operation_key' => (string) Str::uuid()])->assertSessionHasErrors('starts_at');
        $this->post(route('seller.sales.store'), [...$data, 'operation_key' => (string) Str::uuid(), 'ends_at' => now()->toIso8601String()])->assertSessionHasErrors('ends_at');
        $this->as($this->user())->post(route('seller.sales.store'), [...$data, 'operation_key' => (string) Str::uuid()])->assertNotFound();
        $this->as($seller)->get(route('seller.sales.index'))->assertOk()->assertSee($product->name)->assertSee('Asia/Manila');
    }

    public function test_adjacent_sales_do_not_overlap_and_fixed_price_can_reach_one_peso(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $sale = $this->sale($seller, $product);
        $next = app(ProductSaleService::class)->save($seller, [...$this->saleData($product), 'kind' => 'fixed', 'value' => 1, 'starts_at' => $sale->ends_at->toIso8601String(), 'ends_at' => $sale->ends_at->copy()->addHour()->toIso8601String()]);
        $this->travelTo($next->starts_at);
        $this->assertSame(100, $this->quote($product)['lines'][0]['unit_cents']);
    }

    public function test_variant_ranges_use_effective_prices_and_fixed_price_must_be_lower_than_all_variants(): void
    {
        $seller = $this->user();
        $product = $this->product($seller, ['price' => 199]);
        foreach ([0, 50, 100] as $adjustment) {
            $product->variations()->create(['type' => 'Size', 'value' => 'Option '.$adjustment, 'price_adjustment' => $adjustment, 'stock' => 5]);
        }
        $sale = $this->sale($seller, $product, ['value' => 10]);
        $this->travelTo($sale->starts_at);
        $product->refresh()->load('variations');
        $this->assertSame('₱179.10 – ₱269.10', $product->display_price_label);
        foreach ($product->variations as $variant) {
            $quote = app(CheckoutPricingService::class)->quote([['product_id' => $product->id, 'variation_id' => $variant->id, 'quantity' => 1]]);
            $this->assertSame(app(ProductPriceService::class)->resolve($product, $variant)['unit_cents'], $quote['lines'][0]['unit_cents']);
        }
        $this->as($seller)->patch(route('seller.sales.update', $sale), [...$this->saleData($product), 'kind' => 'fixed', 'value' => 200, 'starts_at' => $sale->starts_at->toIso8601String(), 'version' => 1])->assertSessionHasErrors('value');
    }

    public function test_running_sale_edit_and_end_preserve_old_order_snapshots_and_reject_stale_quotes(): void
    {
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $product = $this->product($seller);
        $sale = $this->sale($seller, $product);
        $this->travelTo($sale->starts_at);
        $quote = $this->quote($product);
        $purchase = $this->place($buyer, $product, $quote);
        $item = OrderItem::sole();
        $this->assertSame(360.0, (float) $item->unit_price);
        $this->assertSame($sale->id, $item->pricing_snapshot['sale_id']);
        $edit = [...$this->saleData($product), 'value' => 30, 'starts_at' => $sale->starts_at->toIso8601String(), 'version' => 1];
        $this->as($seller)->patch(route('seller.sales.update', $sale), $edit)->assertSessionHasNoErrors();
        $this->assertSame(31500, $this->quote($product)['lines'][0]['unit_cents']);
        $this->assertSame(360.0, (float) $item->fresh()->unit_price);
        try {
            $this->place($buyer, $product, $quote);
            $this->fail('Stale quote accepted.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('quote', $error->errors());
        }
        $this->post(route('seller.sales.end', $sale))->assertSessionHasNoErrors();
        $this->post(route('seller.sales.end', $sale))->assertSessionHasNoErrors();
        $this->assertSame(45000, $this->quote($product)['lines'][0]['unit_cents']);
        $this->assertDatabaseCount('purchases', 1);
    }

    public function test_selected_product_voucher_uses_discounted_eligible_subtotal_and_allocates_only_to_eligible_lines(): void
    {
        $seller = $this->user();
        $eligible = $this->product($seller);
        $other = $this->product($seller, ['price' => 1000]);
        $sale = $this->sale($seller, $eligible);
        $this->travelTo($sale->starts_at);
        $voucher = $this->voucher($seller, ['value' => 30, 'min_spend' => 300, 'product_scoped' => true]);
        $voucher->products()->attach($eligible);
        $cart = [['product_id' => $eligible->id, 'quantity' => 1], ['product_id' => $other->id, 'quantity' => 1]];
        $quote = app(CheckoutPricingService::class)->quote($cart, $voucher);
        $this->assertSame(3000, $quote['discount_cents']);
        $this->assertSame(3000, $quote['lines'][0]['pricing_snapshot']['voucher_discount_cents']);
        $this->assertSame(0, $quote['lines'][1]['pricing_snapshot']['voucher_discount_cents']);
        $voucher->update(['min_spend' => 400]);
        try {
            app(CheckoutPricingService::class)->quote($cart, $voucher->fresh());
            $this->fail('Ineligible item counted toward minimum spend.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('40.00', $error->errors()['voucher_code'][0]);
        }
        $this->as($this->user('buyer'))->get(route('product.show', $other))->assertOk()->assertDontSee($voucher->code);
    }

    public function test_code_only_voucher_is_hidden_from_public_web_lists_and_guessed_claims_but_manual_lowercase_code_works(): void
    {
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $product = $this->product($seller);
        $voucher = $this->voucher($seller, ['code' => 'HIDDEN100', 'visibility' => 'code_only']);
        $this->as($buyer)->get(route('product.show', $product))->assertOk()->assertDontSee('HIDDEN100');
        $this->get(route('buyer.vouchers.index'))->assertOk()->assertDontSee('HIDDEN100');
        $this->post(route('buyer.vouchers.claim', $voucher))->assertNotFound();
        $token = ApiToken::createTokenForUser($buyer)['plainTextToken'];
        $this->withToken($token)->getJson('/api/vouchers')->assertOk()->assertDontSee('HIDDEN100');
        $this->withToken($token)->postJson('/api/vouchers/'.$voucher->id.'/claim')->assertNotFound();
        $this->post(route('cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->post(route('checkout.voucher.apply'), ['voucher_code' => 'hidden100'])->assertSessionHasNoErrors()->assertSessionHas('applied_voucher.code', 'HIDDEN100');
        $this->get(route('buyer.vouchers.index', ['view' => 'saved']))->assertOk()->assertSee('HIDDEN100');
    }

    public function test_new_voucher_product_scope_ownership_and_visibility_persist_with_funding(): void
    {
        $seller = $this->user();
        $own = $this->product($seller);
        $other = $this->product($this->user());
        $this->fund($seller, 100000);
        $payload = ['code' => 'SCOPE30', 'type' => 'fixed', 'value' => 30, 'usage_limit' => 2, 'product_scoped' => 1, 'product_ids' => [$other->id], 'visibility' => 'code_only'];
        $this->as($seller)->post(route('seller.vouchers.store'), $payload)->assertSessionHasErrors('product_ids');
        $this->post(route('seller.vouchers.store'), [...$payload, 'product_ids' => [$own->id]])->assertSessionHasNoErrors();
        $voucher = Voucher::sole();
        $this->assertSame('code_only', $voucher->visibility);
        $this->assertSame(2, (int) $voucher->terms_version);
        $this->assertSame([$own->id], $voucher->products->modelKeys());
        $this->get(route('seller.vouchers.index'))->assertOk()->assertSee('Code only');
    }

    public function test_claimed_voucher_expiry_and_funded_limit_increase_are_atomic_and_terms_are_locked(): void
    {
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $this->fund($seller, 10000);
        $voucher = $this->voucher($seller, ['value' => 10, 'usage_limit' => 2, 'expires_at' => now()->addDay(), 'terms_version' => 2]);
        app(VoucherFundingService::class)->reserve($voucher, $seller);
        app(VoucherClaimService::class)->claim($buyer, $voucher);
        $wallet = app(FinancialLedgerService::class)->wallet($seller);
        $before = $wallet->balance_cents;
        $this->as($seller)->patch(route('seller.vouchers.limits', $voucher), ['usage_limit' => 4, 'expires_at' => now()->addDays(2)->toIso8601String()])->assertSessionHasNoErrors();
        $this->assertSame($before - 2000, $wallet->fresh()->balance_cents);
        $this->assertSame(4000, $voucher->fresh()->budget->maximum_cents);
        $this->patch(route('seller.vouchers.limits', $voucher), ['usage_limit' => 4])->assertSessionHasNoErrors();
        $this->assertSame($before - 2000, $wallet->fresh()->balance_cents);
        $this->patch(route('seller.vouchers.limits', $voucher), ['recipient_limit' => 20])->assertSessionHasErrors('recipient_limit');
        $this->patch(route('seller.vouchers.limits', $voucher), ['min_spend' => 1])->assertSessionHasErrors('min_spend');
        $this->patch(route('seller.vouchers.limits', $voucher), ['expires_at' => now()->addDay()->toIso8601String()])->assertSessionHasErrors('expires_at');
        $this->patch(route('seller.vouchers.limits', $voucher), ['usage_limit' => 100])->assertSessionHasErrors('balance');
        $this->assertSame(4, $voucher->fresh()->usage_limit);
        $this->assertSame(4000, $voucher->fresh()->budget->maximum_cents);
        $this->assertTrue(app(FinancialLedgerService::class)->reconcile()['balanced']);
    }

    public function test_purchase_limit_is_shared_across_variants_and_cancelled_orders_release_capacity(): void
    {
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $product = $this->product($seller);
        $this->as($seller)->patch(route('seller.products.purchase-limit', $product), ['purchase_limit' => 2])->assertSessionHasNoErrors();
        $quote = $this->quote($product, 2);
        $purchase = $this->place($buyer, $product, $quote, 2);
        $this->as($buyer)->post(route('cart.add', $product), ['quantity' => 1])->assertSessionHasErrors('quantity');
        try {
            $this->place($buyer, $product, $this->quote($product));
            $this->fail('Placement exceeded lifetime cap.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('quantity', $error->errors());
        }
        $purchase->orders()->firstOrFail()->forceFill(['status' => 'CANCELLED'])->save();
        $this->post(route('cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->as($this->user())->patch(route('seller.products.purchase-limit', $product), ['purchase_limit' => 10])->assertNotFound();
    }

    public function test_multi_variant_cart_cannot_bypass_product_limit_and_different_buyers_have_independent_caps(): void
    {
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $product = $this->product($seller, ['purchase_limit' => 2]);
        $red = $product->variations()->create(['type' => 'Color', 'value' => 'Red', 'stock' => 5]);
        $blue = $product->variations()->create(['type' => 'Color', 'value' => 'Blue', 'stock' => 5]);
        $this->as($buyer)->post(route('cart.add', $product), ['quantity' => 2, 'variation_id' => $red->id])->assertSessionHasNoErrors();
        $this->post(route('cart.add', $product), ['quantity' => 1, 'variation_id' => $blue->id])->assertSessionHasErrors('quantity');
        $this->as($this->user('buyer'))->post(route('cart.add', $product), ['quantity' => 2, 'variation_id' => $blue->id])->assertSessionHasNoErrors();
    }

    private function user(string $role = 'seller'): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.com', 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Sale', 'last_name' => 'Tester', 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion']);
    }

    public function test_unpaid_sale_reservation_keeps_agreed_price_after_sale_ends_and_expiry_releases_purchase_limit(): void
    {
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $product = $this->product($seller, ['purchase_limit' => 1]);
        $sale = $this->sale($seller, $product);
        $this->travelTo($sale->starts_at);
        $this->fund($buyer, 100000);
        $quote = $this->quote($product);
        $token = (string) Str::uuid();
        $purchase = app(PlacePurchaseService::class)->place($buyer, $token, hash('sha256', $token), [['product_id' => $product->id, 'quantity' => 1]], $quote['hash'], null,
            ['recipient_name' => 'Buyer Tester', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Demo Lane'], 'WALLET', true);
        $order = $purchase->orders()->firstOrFail();
        app(ProductSaleService::class)->end($seller, $sale);
        $this->assertSame(45000, $this->quote($product)['lines'][0]['unit_cents']);
        app(WalletOrderPaymentService::class)->pay($order, $buyer);
        $this->assertSame(360.0, (float) $order->items()->sole()->unit_price);
        $this->assertSame(36000, $order->items()->sole()->pricing_snapshot['unit_cents']);
        $this->assertSame(41000, $order->fresh()->wallet_paid_cents);
        $this->assertTrue(app(FinancialLedgerService::class)->reconcile()['balanced']);
        $secondBuyer = $this->user('buyer');
        $quote = $this->quote($product);
        $token = (string) Str::uuid();
        $reservation = app(PlacePurchaseService::class)->place($secondBuyer, $token, hash('sha256', $token), [['product_id' => $product->id, 'quantity' => 1]], $quote['hash'], null,
            ['recipient_name' => 'Buyer Tester', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Demo Lane'], 'WALLET', true)->orders()->firstOrFail();
        $this->travelTo($reservation->wallet_payment_due_at);
        $this->assertTrue(app(OrderTransitionService::class)->expireWalletPayment($reservation));
        $this->as($secondBuyer)->post(route('cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
    }

    public function test_new_sales_reject_past_or_zero_length_schedules_and_enforce_percent_boundaries(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $this->as($seller)->post(route('seller.sales.store'), [...$this->saleData($product), 'starts_at' => now()->subMinute()->toIso8601String()])->assertSessionHasErrors('ends_at');
        $start = now()->addHour()->toIso8601String();
        $this->post(route('seller.sales.store'), [...$this->saleData($product), 'starts_at' => $start, 'ends_at' => $start])->assertSessionHasErrors('ends_at');
        $sale = $this->sale($seller, $product, ['value' => 99]);
        $this->travelTo($sale->starts_at);
        $this->assertSame(450, $this->quote($product)['lines'][0]['unit_cents']);
        $this->post(route('seller.sales.end', $sale))->assertSessionHasNoErrors();
        $next = $this->sale($seller, $product, ['value' => 1]);
        $this->travelTo($next->starts_at);
        $this->assertSame(44550, $this->quote($product)['lines'][0]['unit_cents']);
    }

    public function test_targeted_voucher_references_do_not_break_removal_and_schema_roundtrip_keeps_legacy_amounts(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $voucher = $this->voucher($seller, ['product_scoped' => true]);
        $voucher->products()->attach($product);
        $this->as($seller)->delete(route('seller.products.destroy', $product))->assertSessionHasNoErrors();
        $this->assertTrue((bool) $product->fresh()->is_archived);
        $migration = require database_path('migrations/2026_11_14_214751_add_scheduled_sales_voucher_scope_and_purchase_limits.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('product_sales'));
        $this->assertSame(450.0, (float) $product->fresh()->price);
        $this->assertSame(50.0, (float) $voucher->fresh()->value);
        $migration->up();
        $this->assertTrue(Schema::hasColumn('order_items', 'pricing_snapshot'));
        $this->assertSame('public', $voucher->fresh()->visibility);
    }

    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function product(User $seller, array $data = []): Product
    {
        return Product::forceCreate(['user_id' => $seller->id, 'name' => 'Sale item '.Str::random(4), 'category' => 'Home and Garden', 'price' => 450, 'stock' => 20, 'weight_grams' => 1000, 'compliance_status' => 'approved', 'is_archived' => false, ...$data]);
    }

    private function saleData(Product $product): array
    {
        return ['product_id' => $product->id, 'kind' => 'percent', 'value' => 20, 'starts_at' => now()->addMinute()->toIso8601String(), 'ends_at' => now()->addHour()->toIso8601String(), 'operation_key' => (string) Str::uuid()];
    }

    private function sale(User $seller, Product $product, array $overrides = []): ProductSale
    {
        return app(ProductSaleService::class)->save($seller, [...$this->saleData($product), ...$overrides]);
    }

    private function quote(Product $product, int $quantity = 1): array
    {
        return app(CheckoutPricingService::class)->quote([['product_id' => $product->id, 'quantity' => $quantity]]);
    }

    private function place(User $buyer, Product $product, array $quote, int $quantity = 1)
    {
        $token = (string) Str::uuid();

        return app(PlacePurchaseService::class)->place($buyer, $token, hash('sha256', $token), [['product_id' => $product->id, 'quantity' => $quantity]], $quote['hash'], null,
            ['recipient_name' => 'Buyer Tester', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Demo Lane']);
    }

    private function voucher(User $seller, array $data = []): Voucher
    {
        return Voucher::create(['seller_id' => $seller->id, 'code' => 'SALE'.Str::upper(Str::random(5)), 'type' => 'fixed', 'value' => 50, 'min_spend' => 0, 'usage_limit' => 20, 'used_count' => 0, 'is_active' => true, 'expires_at' => now()->addDays(3), ...$data]);
    }

    private function fund(User $seller, int $cents): void
    {
        $ledger = app(FinancialLedgerService::class);
        $wallet = $ledger->wallet($seller);
        $source = $ledger->account('sandbox:source', 'sandbox', null, 'source', true);
        $ledger->post('sale-fixture-'.Str::uuid(), 'sandbox_funding', [$source->id => -$cents, $wallet->id => $cents], $seller);
    }
}
