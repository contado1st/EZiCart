<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BuyerCartService;
use App\Services\CheckoutPricingService;
use App\Services\PlacePurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BuyerCartRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_cart_survives_logout_and_real_login_and_is_private_to_each_buyer(): void
    {
        $buyer = $this->buyer();
        $product = $this->product('Saved after logout');
        $this->actingAs($buyer)->post(route('cart.add', $product), ['quantity' => 2])->assertRedirect();
        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
        $this->post(route('login.post'), ['email' => $buyer->email, 'password' => 'Password123!'])->assertRedirect(route('buyer.dashboard'));
        $this->get(route('cart.index'))->assertOk()->assertSee('Saved after logout')->assertSessionHas('cart.'.$product->id.'.quantity', 2);
        $this->flushSession();
        $other = $this->buyer();
        $this->actingAs($other)->get(route('cart.index'))->assertOk()->assertDontSee('Saved after logout');
        $this->assertSame([], app(BuyerCartService::class)->read($other));
        $this->assertSame(2, app(BuyerCartService::class)->read($buyer)[$product->id]['quantity']);
    }

    public function test_buy_now_reviews_one_product_and_cancellation_never_adds_it_to_saved_cart(): void
    {
        $buyer = $this->buyer();
        $saved = $this->product('Saved bag');
        $instant = $this->product('Instant shoes', 200);
        $this->actingAs($buyer)->post(route('cart.add', $saved), ['quantity' => 3]);
        $this->post(route('cart.add', $instant), ['quantity' => 2, 'intent' => 'buy_now'])->assertRedirect(route('checkout.index'));
        $this->get(route('checkout.index'))->assertOk()->assertViewHas('checkoutMode', 'buy_now')->assertViewHas('cart', fn ($lines) => count($lines) === 1 && reset($lines)['product_id'] === $instant->id)->assertViewHas('total', 450);
        $this->post(route('checkout.cancel'))->assertRedirect(route('product.show', $instant))->assertSessionMissing('checkout_context');
        $this->assertSame([$saved->id], array_keys(app(BuyerCartService::class)->read($buyer)));
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $instant->fresh()->stock);
    }

    public function test_buy_now_purchase_leaves_existing_quantities_of_the_same_product_and_other_products_untouched(): void
    {
        $buyer = $this->buyer();
        $product = $this->product('Same product');
        $other = $this->product('Other saved product');
        $this->actingAs($buyer)->post(route('cart.add', $product), ['quantity' => 3]);
        $this->post(route('cart.add', $other), ['quantity' => 2]);
        $this->post(route('cart.add', $product), ['quantity' => 1, 'intent' => 'buy_now']);
        $this->get(route('checkout.index'))->assertOk();
        $this->post(route('checkout.process'), $this->payload())->assertRedirect(route('buyer.dashboard'))->assertSessionHasNoErrors();
        $cart = app(BuyerCartService::class)->read($buyer);
        $this->assertSame(3, $cart[$product->id]['quantity']);
        $this->assertSame(2, $cart[$other->id]['quantity']);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_only_checked_products_are_ordered_and_replay_does_not_clear_unselected_items(): void
    {
        $buyer = $this->buyer();
        $a = $this->product('Selected item');
        $b = $this->product('Keep for next time', 250);
        $this->actingAs($buyer)->post(route('cart.add', $a), ['quantity' => 2]);
        $this->post(route('cart.add', $b), ['quantity' => 1]);
        $this->post(route('cart.checkout'), ['selected_keys' => [(string) $a->id]])->assertRedirect(route('checkout.index'));
        $this->get(route('checkout.index'))->assertOk()->assertViewHas('total', 250)->assertDontSee('Keep for next time');
        $payload = $this->payload();
        $this->post(route('checkout.process'), $payload)->assertRedirect(route('buyer.dashboard'))->assertSessionHasNoErrors();
        $this->post(route('checkout.process'), $payload)->assertRedirect(route('buyer.dashboard'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseHas('order_items', ['product_id' => $a->id, 'quantity' => 2]);
        $this->assertSame([$b->id], array_keys(app(BuyerCartService::class)->read($buyer)));
        $this->assertSame(10, $b->fresh()->stock);
    }

    public function test_empty_and_foreign_selections_are_rejected_and_cannot_create_a_draft(): void
    {
        $product = $this->product('Not in this cart');
        $this->actingAs($this->buyer())->post(route('cart.checkout'), ['selected_keys' => []])->assertSessionHasErrors('selected_keys');
        $this->post(route('cart.checkout'), ['selected_keys' => [(string) $product->id]])->assertSessionHasErrors('selected_keys');
        $this->assertNull(session('checkout_context'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unavailable_unselected_items_remain_visible_and_do_not_block_other_purchases(): void
    {
        $buyer = $this->buyer();
        $available = $this->product('Available item');
        $unavailable = $this->product('Sold out later');
        $this->actingAs($buyer)->post(route('cart.add', $available), ['quantity' => 1]);
        $this->post(route('cart.add', $unavailable), ['quantity' => 1]);
        $unavailable->update(['stock' => 0]);
        $this->get(route('cart.index'))->assertOk()->assertSee('Sold out later')->assertSee('Currently out of stock.');
        $this->post(route('cart.checkout'), ['selected_keys' => [(string) $available->id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('checkout.index'))->assertOk();
        $this->post(route('checkout.process'), $this->payload())->assertRedirect(route('buyer.dashboard'));
        $this->assertArrayHasKey($unavailable->id, app(BuyerCartService::class)->read($buyer));
    }

    public function test_a_later_addition_in_another_tab_is_preserved_after_reviewed_quantity_is_purchased(): void
    {
        $buyer = $this->buyer();
        $product = $this->product('Shared cart');
        $this->actingAs($buyer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->post(route('cart.checkout'), ['selected_keys' => [(string) $product->id]]);
        $this->get(route('checkout.index'))->assertOk();
        $payload = $this->payload();
        app(BuyerCartService::class)->add($buyer, $product->id, null, 2);
        $this->post(route('checkout.process'), $payload)->assertRedirect(route('buyer.dashboard'));
        $this->assertSame(2, app(BuyerCartService::class)->read($buyer)[$product->id]['quantity']);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_reduced_cart_quantity_after_review_blocks_order_without_reserving_stock(): void
    {
        $buyer = $this->buyer();
        $product = $this->product('Changed cart');
        $this->actingAs($buyer)->post(route('cart.add', $product), ['quantity' => 2]);
        $this->post(route('cart.checkout'), ['selected_keys' => [(string) $product->id]]);
        $this->get(route('checkout.index'))->assertOk();
        $payload = $this->payload();
        app(BuyerCartService::class)->update($buyer, (string) $product->id, 1);
        $this->post(route('checkout.process'), $payload)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_vouchers_use_only_selected_items_and_variations_stay_separate(): void
    {
        $buyer = $this->buyer();
        $product = $this->product('Variant item');
        $red = $product->variations()->create(['type' => 'Color', 'value' => 'Red', 'stock' => 5, 'is_active' => true]);
        $blue = $product->variations()->create(['type' => 'Color', 'value' => 'Blue', 'stock' => 5, 'is_active' => true]);
        $this->actingAs($buyer)->post(route('cart.add', $product), ['quantity' => 1, 'variation_id' => $red->id]);
        $this->post(route('cart.add', $product), ['quantity' => 2, 'variation_id' => $blue->id]);
        $voucher = Voucher::create(['seller_id' => $product->user_id, 'code' => 'SELECTED-ONLY', 'type' => 'fixed', 'value' => 10, 'min_spend' => 200, 'is_active' => true]);
        $key = BuyerCartService::key($product->id, $red->id);
        $this->post(route('cart.checkout'), ['selected_keys' => [$key]]);
        $this->post(route('checkout.voucher.apply'), ['voucher_code' => $voucher->code])->assertSessionHasErrors('voucher_code');
        $this->get(route('checkout.index'))->assertOk()->assertSee('Color: Red')->assertDontSee('Color: Blue');
        $this->post(route('checkout.process'), $this->payload())->assertRedirect(route('buyer.dashboard'));
        $this->assertSame(2, app(BuyerCartService::class)->read($buyer)[BuyerCartService::key($product->id, $blue->id)]['quantity']);
        $this->assertDatabaseHas('order_items', ['variation_id' => $red->id, 'quantity' => 1]);
    }

    public function test_legacy_session_cart_is_imported_once_and_cannot_resurrect_removed_items(): void
    {
        $buyer = $this->buyer();
        $product = $this->product('Legacy item');
        $legacy = [$product->id => ['product_id' => $product->id, 'quantity' => 2, 'variation_id' => null, 'price' => .01]];
        $this->actingAs($buyer)->withSession(['cart' => $legacy])->get(route('cart.index'))->assertOk()->assertSee('100.00');
        $this->delete(route('cart.remove', $product->id))->assertRedirect();
        $this->withSession(['cart' => $legacy])->get(route('cart.index'))->assertOk()->assertDontSee('Legacy item');
        $this->assertSame([], app(BuyerCartService::class)->read($buyer));
    }

    public function test_ajax_add_to_cart_counts_distinct_lines_and_ignores_client_prices(): void
    {
        $buyer = $this->buyer();
        $product = $this->product('Ajax product');
        $this->actingAs($buyer)->postJson(route('cart.add', $product), ['quantity' => 2, 'price' => .01, 'seller_id' => $buyer->id])->assertOk()->assertJsonPath('count', 1);
        $this->postJson(route('cart.add', $product), ['quantity' => 3])->assertOk()->assertJsonPath('count', 1);
        $this->get(route('cart.index'))->assertOk()->assertViewHas('cart', fn ($cart) => $cart[$product->id]['price'] === 100 && $cart[$product->id]['quantity'] === 5);
        $this->assertSame(5, app(BuyerCartService::class)->read($buyer)[$product->id]['quantity']);
    }

    public function test_bulk_checkout_accepts_multiple_products_from_one_shop_and_keeps_other_shops_saved(): void
    {
        $buyer = $this->buyer();
        $a = $this->product('Bulk first');
        $b = $this->product('Bulk second', 200);
        $b->update(['user_id' => $a->user_id]);
        $other = $this->product('Other shop');
        $this->actingAs($buyer);
        foreach ([$a, $b, $other] as $product) {
            $this->post(route('cart.add', $product), ['quantity' => 2]);
        }
        $this->post(route('cart.checkout'), ['selected_keys' => [(string) $a->id, (string) $b->id]])->assertRedirect(route('checkout.index'))->assertSessionHasNoErrors();
        $this->get(route('checkout.index'))->assertOk()->assertViewHas('total', 650);
        $this->post(route('checkout.process'), $this->payload())->assertRedirect(route('buyer.dashboard'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertSame([$other->id], array_keys(app(BuyerCartService::class)->read($buyer)));
    }

    public function test_mixed_shop_selection_and_legacy_checkout_are_rejected(): void
    {
        $buyer = $this->buyer();
        $a = $this->product('First shop');
        $b = $this->product('Second shop');
        $this->actingAs($buyer)->post(route('cart.add', $a), ['quantity' => 1]);
        $this->post(route('cart.add', $b), ['quantity' => 1]);
        $this->post(route('cart.checkout'), ['selected_keys' => [(string) $a->id, (string) $b->id]])->assertSessionHasErrors('selected_keys');
        $this->get(route('checkout.index'))->assertRedirect(route('cart.index'))->assertSessionHas('error');
        $this->assertDatabaseCount('orders', 0);
        $this->assertCount(2, app(BuyerCartService::class)->read($buyer));
    }

    public function test_direct_order_submission_cannot_bypass_the_one_shop_rule(): void
    {
        $buyer = $this->buyer();
        $a = $this->product('Protected first shop');
        $b = $this->product('Protected second shop');
        $this->actingAs($buyer)->post(route('cart.add', $a), ['quantity' => 1]);
        $this->post(route('cart.add', $b), ['quantity' => 1]);
        $this->post(route('cart.checkout'), ['selected_keys' => [(string) $a->id]]);
        $this->get(route('checkout.index'))->assertOk();
        $items = app(BuyerCartService::class)->read($buyer);
        $quote = app(CheckoutPricingService::class)->quote($items);
        $payload = array_replace($this->payload(), ['quote_hash' => $quote['hash']]);
        $this->withSession(['checkout_context' => ['mode' => 'cart', 'items' => $items], 'checkout_quote_hash' => $quote['hash']])->post(route('checkout.process'), $payload)->assertSessionHasErrors('selected_keys');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $a->fresh()->stock);
        $this->assertSame(10, $b->fresh()->stock);
    }

    public function test_shared_purchase_service_rejects_multiple_shops_for_any_checkout_caller(): void
    {
        $buyer = $this->buyer();
        $a = $this->product('Service first');
        $b = $this->product('Service second');
        $items = [['product_id' => $a->id, 'quantity' => 1], ['product_id' => $b->id, 'quantity' => 1]];
        $quote = app(CheckoutPricingService::class)->quote($items);
        $this->expectException(ValidationException::class);
        try {
            app(PlacePurchaseService::class)->place($buyer, (string) Str::uuid(), str_repeat('a', 64), $items, $quote['hash'], null, []);
        } finally {
            $this->assertDatabaseCount('purchases', 0);
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame(10, $a->fresh()->stock);
            $this->assertSame(10, $b->fresh()->stock);
        }
    }

    private function buyer(): User
    {
        $email = fake()->unique()->safeEmail();

        return User::forceCreate(['email' => $email, 'canonical_email' => $email, 'password' => 'Password123!', 'first_name' => 'Cart', 'last_name' => 'Buyer', 'role' => 'buyer', 'status' => 'approved', 'email_verified_at' => now(), 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Test Road']);
    }

    private function product(string $name, float $price = 100): Product
    {
        $seller = User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'role' => 'seller', 'status' => 'approved', 'first_name' => 'Cart', 'last_name' => 'Seller', 'contact_no' => '09123456789', 'business_name' => 'Shop '.$name]);

        return Product::forceCreate(['user_id' => $seller->id, 'name' => $name, 'price' => $price, 'stock' => 10, 'category' => 'Home and Garden', 'compliance_status' => 'approved']);
    }

    private function payload(): array
    {
        return ['checkout_token' => session('checkout_token'), 'quote_hash' => session('checkout_quote_hash'), 'payment_method' => 'COD', 'recipient_name' => 'Cart Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Test Road'];
    }
}
