<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Services\BuyerCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CartVariationChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_switch_reprices_the_saved_line_preserves_quantity_and_invalidates_cart_checkout(): void
    {
        [$buyer, $product, $red, $blue] = $this->fixture();
        $carts = app(BuyerCartService::class);
        $carts->add($buyer, $product->id, $red->id, 2);
        $old = BuyerCartService::key($product->id, $red->id);
        $new = BuyerCartService::key($product->id, $blue->id);

        $this->actingAs($buyer)->withSession(['checkout_context' => ['mode' => 'cart', 'items' => $carts->read($buyer)],
            'checkout_token' => 'old-token', 'checkout_quote_hash' => 'old-quote', 'applied_voucher' => ['code' => 'OLD']])
            ->patch(route('cart.variation', $old), ['variation_id' => $blue->id, 'price' => .01, 'quantity' => 19])
            ->assertRedirect(route('cart.index'))->assertSessionHasNoErrors()
            ->assertSessionMissing('checkout_context')->assertSessionMissing('checkout_token')
            ->assertSessionMissing('checkout_quote_hash')->assertSessionMissing('applied_voucher')
            ->assertSessionHas('cart_variation_change', ['previous_key' => $old, 'new_key' => $new]);

        $this->assertSame([$new => ['product_id' => $product->id, 'variation_id' => $blue->id, 'quantity' => 2]], $carts->read($buyer));
        $line = $carts->display($buyer)[$new];
        $this->assertSame(15000, $line['unit_cents']);
        $this->assertSame(30000, $line['item_cents']);
        $this->assertSame('Color: Blue', $line['variation_info']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(30, $product->fresh()->stock);
        $this->get(route('cart.index'))->assertOk()->assertSee('Change variation')->assertSee('Save variation');
        $this->assertSame($blue->id, $carts->selected($buyer, [$new])[$new]['variation_id']);
    }

    public function test_switch_merges_an_existing_option_without_losing_units_or_other_lines(): void
    {
        [$buyer, $product, $red, $blue] = $this->fixture();
        $carts = app(BuyerCartService::class);
        $carts->add($buyer, $product->id, $red->id, 2);
        $carts->add($buyer, $product->id, $blue->id, 3);
        $other = $this->product();
        $carts->add($buyer, $other->id, null, 1);
        $otherLine = $carts->read($buyer)[$other->id];

        $this->actingAs($buyer)->patch(route('cart.variation', BuyerCartService::key($product->id, $red->id)), ['variation_id' => $blue->id])->assertSessionHasNoErrors();
        $saved = $carts->read($buyer);
        $this->assertCount(2, $saved);
        $this->assertSame(5, $saved[BuyerCartService::key($product->id, $blue->id)]['quantity']);
        $this->assertSame($otherLine, $saved[$other->id]);
    }

    #[TestWith(['foreign', 'variation_id'])]
    #[TestWith(['inactive', 'variation_id'])]
    #[TestWith(['sold_out', 'cart'])]
    #[TestWith(['short_stock', 'cart'])]
    #[TestWith(['merged_stock', 'cart'])]
    #[TestWith(['merged_limit', 'cart'])]
    #[TestWith(['product_stock', 'cart'])]
    #[TestWith(['base_mode', 'variation_id'])]
    #[TestWith(['hidden', 'cart'])]
    #[TestWith(['closed', 'cart'])]
    #[TestWith(['purchase_limit', 'quantity'])]
    public function test_invalid_switch_keeps_the_saved_cart_and_draft_unchanged(string $case, string $error): void
    {
        [$buyer, $product, $red, $blue] = $this->fixture();
        $carts = app(BuyerCartService::class);
        $carts->add($buyer, $product->id, $red->id, 2);
        if ($case === 'merged_stock') {
            $carts->add($buyer, $product->id, $blue->id, 3);
            $blue->forceFill(['stock' => 4])->save();
        }
        if ($case === 'merged_limit') {
            $carts->add($buyer, $product->id, $blue->id, 19);
        }
        match ($case) {
            'foreign' => $blue = $this->option($this->product(), 'Foreign'),
            'inactive' => $blue->forceFill(['is_active' => false])->save(),
            'sold_out' => $blue->forceFill(['stock' => 0])->save(),
            'short_stock' => $blue->forceFill(['stock' => 1])->save(),
            'product_stock' => $product->forceFill(['stock' => 1])->save(),
            'base_mode' => $product->forceFill(['variations_enabled' => false])->save(),
            'hidden' => $product->forceFill(['is_archived' => true])->save(),
            'closed' => $product->seller->forceFill(['shop_availability' => 'closed'])->save(),
            'purchase_limit' => $product->forceFill(['purchase_limit' => 1])->save(),
            default => null,
        };
        $before = $carts->read($buyer);
        $draft = ['mode' => 'cart', 'items' => $before];

        $this->actingAs($buyer)->withSession(['checkout_context' => $draft])
            ->patch(route('cart.variation', BuyerCartService::key($product->id, $red->id)), ['variation_id' => $blue->id])
            ->assertSessionHasErrors($error)->assertSessionHas('checkout_context', $draft);
        $this->assertSame($before, $carts->read($buyer));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_other_buyers_cannot_replace_a_line_they_do_not_own(): void
    {
        [$buyer, $product, $red, $blue] = $this->fixture();
        $carts = app(BuyerCartService::class);
        $carts->add($buyer, $product->id, $red->id, 2);
        $before = $carts->read($buyer);
        $other = $this->buyer();
        $this->actingAs($other)->patch(route('cart.variation', BuyerCartService::key($product->id, $red->id)), ['variation_id' => $blue->id])->assertNotFound();
        $this->assertSame($before, $carts->read($buyer));
        $this->assertSame([], $carts->read($other));
    }

    public function test_guest_and_non_buyer_are_rejected(): void
    {
        [$buyer, $product, $red, $blue] = $this->fixture();
        $key = BuyerCartService::key($product->id, $red->id);
        $this->patch(route('cart.variation', $key), ['variation_id' => $blue->id])->assertRedirect(route('login'));
        $this->actingAs($product->seller)->patch(route('cart.variation', $key), ['variation_id' => $blue->id])->assertForbidden();
    }

    #[TestWith([[]])]
    #[TestWith([['variation_id' => 0]])]
    #[TestWith([['variation_id' => ['invalid']]])]
    #[TestWith([['variation_id' => 'invalid']])]
    public function test_malformed_option_input_is_rejected(array $input): void
    {
        [$buyer, $product, $red] = $this->fixture();
        $carts = app(BuyerCartService::class);
        $carts->add($buyer, $product->id, $red->id, 2);
        $before = $carts->read($buyer);
        $this->actingAs($buyer)->patch(route('cart.variation', BuyerCartService::key($product->id, $red->id)), $input)->assertSessionHasErrors('variation_id');
        $this->assertSame($before, $carts->read($buyer));
    }

    public function test_display_exposes_only_this_products_active_options_and_hides_base_mode_controls(): void
    {
        [$buyer, $product, $red, $blue] = $this->fixture();
        $this->option($product, 'Retired')->forceFill(['is_active' => false])->save();
        $blue->forceFill(['stock' => 0])->save();
        $this->option($this->product(), 'Foreign');
        $carts = app(BuyerCartService::class);
        $carts->add($buyer, $product->id, $red->id, 2);
        $options = $carts->display($buyer)[BuyerCartService::key($product->id, $red->id)]['variation_options'];
        $this->assertSame([$red->id, $blue->id], array_column($options, 'id'));
        $this->assertFalse($options[1]['selectable']);
        $product->forceFill(['variations_enabled' => false])->save();
        $this->assertSame([], $carts->display($buyer)[BuyerCartService::key($product->id, $red->id)]['variation_options']);
    }

    public function test_retired_saved_option_can_be_replaced_and_buy_now_draft_stays_separate(): void
    {
        [$buyer, $product, $red, $blue] = $this->fixture();
        $carts = app(BuyerCartService::class);
        $carts->add($buyer, $product->id, $red->id, 2);
        $red->forceFill(['is_active' => false])->save();
        $draft = ['mode' => 'buy_now', 'items' => ['unrelated' => ['product_id' => 999, 'quantity' => 1]]];
        $this->actingAs($buyer)->withSession(['checkout_context' => $draft, 'checkout_token' => 'instant-token'])
            ->patch(route('cart.variation', BuyerCartService::key($product->id, $red->id)), ['variation_id' => $blue->id])
            ->assertSessionHasNoErrors()->assertSessionHas('checkout_context', $draft)->assertSessionHas('checkout_token', 'instant-token');
        $this->assertSame(2, $carts->read($buyer)[BuyerCartService::key($product->id, $blue->id)]['quantity']);
    }

    public function test_cart_preview_options_use_the_variant_photo_and_product_fallback(): void
    {
        [$buyer, $product, $red, $blue] = $this->fixture();
        $product->forceFill(['image_path' => 'products/base.png'])->save();
        $red->forceFill(['image_path' => 'products/red.png'])->save();
        $variantPhotoUrl = asset('storage/'.$red->image_path);
        $productPhotoUrl = asset('storage/'.$product->image_path);
        $carts = app(BuyerCartService::class);
        $carts->add($buyer, $product->id, $red->id, 1);
        $options = $carts->display($buyer)[BuyerCartService::key($product->id, $red->id)]['variation_options'];
        $this->assertSame($variantPhotoUrl, $options[0]['image_url']);
        $this->assertTrue($options[0]['has_own_image']);
        $this->assertSame($productPhotoUrl, $options[1]['image_url']);
        $this->assertFalse($options[1]['has_own_image']);
        $this->actingAs($buyer)->get(route('cart.index'))->assertOk()->assertSee('data-cart-variation-preview', false)
            ->assertSee('data-preview-image="'.$variantPhotoUrl.'"', false)->assertSee('Variation photo: Color: Red');
    }

    private function fixture(): array
    {
        $buyer = $this->buyer();
        $product = $this->product();
        $red = $this->option($product, 'Red');
        $blue = $this->option($product, 'Blue', 50);

        return [$buyer, $product, $red, $blue];
    }

    private function buyer(): User
    {
        return User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'role' => 'buyer', 'status' => 'approved', 'email_verified_at' => now(), 'first_name' => 'Cart', 'last_name' => 'Buyer', 'contact_no' => '09123456789']);
    }

    private function product(): Product
    {
        $seller = User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'role' => 'seller', 'status' => 'approved', 'business_name' => 'Variation shop', 'first_name' => 'Cart', 'last_name' => 'Seller', 'contact_no' => '09123456789']);

        return Product::forceCreate(['user_id' => $seller->id, 'name' => 'Variation product', 'price' => 100, 'stock' => 30, 'category' => 'Home and Garden', 'compliance_status' => 'approved']);
    }

    private function option(Product $product, string $value, int $adjustment = 0): ProductVariation
    {
        return ProductVariation::forceCreate(['product_id' => $product->id, 'type' => 'Color', 'value' => $value, 'price_adjustment' => $adjustment, 'stock' => 25, 'is_active' => true]);
    }
}
