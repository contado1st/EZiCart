<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutPricingService;
use App\Services\PlacePurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_multi_seller_page_total_equals_orders_and_seller_voucher_does_not_discount_other_store(): void
    {
        $buyer = $this->user('buyer');
        $a = $this->product(100);
        $b = $this->product(200);
        $voucher = Voucher::create(['seller_id' => $a->user_id, 'code' => 'ONLY-A', 'type' => 'fixed', 'value' => 30, 'min_spend' => 100, 'is_active' => true, 'usage_limit' => 1]);
        $page = $this->actingAs($buyer)->withSession(['cart' => $this->cart($a, $b), 'applied_voucher' => ['id' => $voucher->id]])->get(route('checkout.index'))->assertOk();
        $page->assertViewHas('total', 370)->assertViewHas('shippingFee', 100)->assertViewHas('discount', 30);
        $payload = $this->payload();
        $this->post(route('checkout.process'), $payload)->assertRedirect(route('buyer.dashboard'));
        $this->assertSame(37000, CheckoutPricingService::cents(Order::sum('total_amount')));
        $this->assertDatabaseHas('orders', ['seller_id' => $a->user_id, 'discount_amount' => 30, 'total_amount' => 120]);
        $this->assertDatabaseHas('orders', ['seller_id' => $b->user_id, 'discount_amount' => 0, 'total_amount' => 250]);
        $this->assertDatabaseCount('purchases', 1);
        $this->assertEquals(37000, Purchase::firstOrFail()->total_cents);
        $this->assertEquals(1, $voucher->fresh()->used_count);
        // Retrying after the cart was cleared must return the same purchase.
        $this->post(route('checkout.process'), $payload)->assertRedirect(route('buyer.dashboard'));
        $this->assertDatabaseCount('orders', 2);
        $this->assertEquals(1, $voucher->fresh()->used_count);
        $this->assertEquals(4, $a->fresh()->stock);
        $payload['recipient_name'] = 'Different recipient';
        $this->post(route('checkout.process'), $payload)->assertUnprocessable();
    }

    public function test_price_change_requires_review_without_creating_orders_or_changing_stock(): void
    {
        $product = $this->product(100);
        $this->actingAs($this->user('buyer'))->withSession(['cart' => $this->cart($product)])->get(route('checkout.index'))->assertOk();
        $payload = $this->payload();
        $product->update(['price' => 150]);
        $this->post(route('checkout.process'), $payload)->assertSessionHasErrors('quote');
        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(5, $product->fresh()->stock);
        $this->get(route('checkout.index'))->assertViewHas('total', 200);
        $this->post(route('checkout.process'), $this->payload())->assertRedirect(route('buyer.dashboard'));
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'unit_price' => 150]);
    }

    public function test_foreign_variation_is_rejected_in_both_cart_and_checkout(): void
    {
        $a = $this->product(100);
        $b = $this->product(100);
        $variant = $b->variations()->create(['type' => 'Color', 'value' => 'Blue', 'price_adjustment' => 10, 'stock' => 5]);
        $this->actingAs($this->user('buyer'))->post(route('cart.add', $a), ['quantity' => 1, 'variation_id' => $variant->id])->assertUnprocessable();
        $this->expectException(ValidationException::class);
        try {
            app(PlacePurchaseService::class)->place(auth()->user(), (string) Str::uuid(), str_repeat('a', 64), [['product_id' => $a->id, 'variation_id' => $variant->id, 'quantity' => 1]], str_repeat('b', 64), null, []);
        } finally {
            $this->assertDatabaseCount('orders', 0);
            $this->assertEquals(5, $a->fresh()->stock);
            $this->assertEquals(5, $variant->fresh()->stock);
        }
    }

    public function test_two_variant_lines_cannot_overdraw_the_shared_product_stock(): void
    {
        $product = $this->product(100);
        $product->update(['stock' => 1]);
        $a = $product->variations()->create(['type' => 'Color', 'value' => 'Blue', 'stock' => 2]);
        $b = $product->variations()->create(['type' => 'Color', 'value' => 'Red', 'stock' => 2]);
        $this->expectException(ValidationException::class);
        app(CheckoutPricingService::class)->quote([
            ['product_id' => $product->id, 'variation_id' => $a->id, 'quantity' => 1],
            ['product_id' => $product->id, 'variation_id' => $b->id, 'quantity' => 1],
        ]);
    }

    public function test_another_buyer_cannot_buy_the_last_unit_after_it_was_reserved(): void
    {
        $product = $this->product(100);
        $product->update(['stock' => 1]);
        $cart = $this->cart($product);
        $quote = app(CheckoutPricingService::class)->quote($cart);
        $address = $this->address();
        $service = app(PlacePurchaseService::class);
        $service->place($this->user('buyer'), (string) Str::uuid(), str_repeat('a', 64), $cart, $quote['hash'], null, $address);
        $this->expectException(ValidationException::class);
        try {
            $service->place($this->user('buyer'), (string) Str::uuid(), str_repeat('b', 64), $cart, $quote['hash'], null, $address);
        } finally {
            $this->assertDatabaseCount('orders', 1);
            $this->assertEquals(0, $product->fresh()->stock);
        }
    }

    public function test_voucher_exhausted_after_preview_cannot_be_redeemed_or_reserve_inventory(): void
    {
        $product = $this->product(100);
        $voucher = Voucher::create(['seller_id' => $product->user_id, 'code' => 'LAST', 'type' => 'fixed', 'value' => 5, 'min_spend' => 0, 'is_active' => true, 'usage_limit' => 1]);
        $this->actingAs($this->user('buyer'))->withSession(['cart' => $this->cart($product), 'applied_voucher' => ['id' => $voucher->id]])->get(route('checkout.index'))->assertOk();
        $voucher->update(['used_count' => 1]);
        $this->post(route('checkout.process'), $this->payload())->assertSessionHasErrors('voucher_code');
        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(5, $product->fresh()->stock);
    }

    public function test_platform_voucher_rounding_is_allocated_exactly_and_discount_cannot_exceed_items(): void
    {
        $a = $this->product(0.01);
        $b = $this->product(0.02);
        $voucher = Voucher::create(['seller_id' => null, 'code' => 'PLATFORM', 'type' => 'percent', 'value' => 100, 'max_discount' => 0.02, 'is_active' => true]);
        $quote = app(CheckoutPricingService::class)->quote($this->cart($a, $b), $voucher);
        $this->assertSame(2, array_sum(array_column($quote['groups'], 'discount_cents')));
        $this->assertSame(10001, $quote['total_cents']);
    }

    public function test_largest_remainder_discount_never_exceeds_a_small_seller_subtotal(): void
    {
        $voucher = Voucher::create(['seller_id' => null, 'code' => 'TWO-CENTS', 'type' => 'fixed', 'value' => 0.02, 'is_active' => true]);
        $quote = app(CheckoutPricingService::class)->quote($this->cart($this->product(0.01), $this->product(0.01), $this->product(0.01)), $voucher);
        $this->assertSame(2, array_sum(array_column($quote['groups'], 'discount_cents')));
        foreach ($quote['groups'] as $group) {
            $this->assertLessThanOrEqual($group['subtotal_cents'], $group['discount_cents']);
        }
        $this->assertSame(15001, $quote['total_cents']);
    }

    private function user(string $role): User
    {
        return User::forceCreate([
            'email' => fake()->unique()->safeEmail(), 'password' => 'password',
            'role' => $role, 'status' => 'approved', 'first_name' => 'Test', 'last_name' => 'User',
            'sex' => 'Other', 'birthday' => '1990-01-01', 'age' => 36, 'contact_no' => '09123456789',
            'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Test Street',
        ]);
    }

    private function product(float $price): Product
    {
        return Product::forceCreate(['user_id' => $this->user('seller')->id, 'name' => 'Test item', 'category' => 'Test', 'price' => $price, 'stock' => 5, 'is_archived' => false, 'compliance_status' => 'approved']);
    }

    private function cart(Product ...$products): array
    {
        return array_map(fn ($p) => ['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null, 'price' => $p->price, 'name' => $p->name], $products);
    }

    private function address(): array
    {
        return ['recipient_name' => 'Test Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Test Street'];
    }

    private function payload(): array
    {
        return [...$this->address(), 'checkout_token' => session('checkout_token'), 'quote_hash' => session('checkout_quote_hash'), 'payment_method' => 'COD'];
    }
}
