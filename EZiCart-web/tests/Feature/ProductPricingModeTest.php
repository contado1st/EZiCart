<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDraft;
use App\Models\ReturnInspectionItem;
use App\Models\User;
use App\Services\BuyerCartService;
use App\Services\CheckoutPricingService;
use App\Services\InventoryRestorationService;
use App\Services\StockSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductPricingModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $seller = User::forceCreate(['email' => 'pricing@example.test', 'password' => 'Password123!', 'role' => 'seller', 'status' => 'approved', 'first_name' => 'Pricing', 'last_name' => 'Seller', 'contact_no' => '09123456789']);
        $this->actingAs($seller);
    }

    public function test_base_mode_uses_single_price_and_stock_and_ignores_hidden_rows(): void
    {
        $this->post(route('seller.products.store'), [...$this->base(), 'variations_enabled' => 0, 'variations' => [['price' => -1]]])->assertSessionHasNoErrors();
        $product = Product::sole();
        $this->assertEquals(100, $product->price);
        $this->assertSame(5, $product->stock);
        $this->assertSame(0, $product->variations()->count());
        $this->assertFalse($product->variations_enabled);
    }

    public function test_variations_derive_price_and_combined_stock_without_base_inputs(): void
    {
        $this->post(route('seller.products.store'), $this->optionPayload())->assertSessionHasNoErrors();
        $product = Product::with('variations')->sole();
        $this->assertEquals(120.25, $product->price);
        $this->assertSame(10, $product->stock);
        $this->assertTrue($product->variations_enabled);
        $this->assertEquals([0, 80.25], $product->variations->pluck('price_adjustment')->all());
        $html = Blade::render('<x-product-price :product="$product" />', compact('product'));
        $this->assertStringContainsString('120.25', $html);
        $this->assertStringContainsString('200.50', $html);
        $this->assertStringContainsString('10 in stock', $html);
    }

    public function test_enabled_empty_options_and_missing_option_price_or_stock_are_rejected(): void
    {
        $this->post(route('seller.products.store'), [...$this->optionPayload(), 'variations' => []])->assertSessionHasErrors('variations');
        $payload = $this->optionPayload();
        unset($payload['variations'][0]['price'], $payload['variations'][1]['stock']);
        $this->post(route('seller.products.store'), $payload)->assertSessionHasErrors(['variations.0.price', 'variations.1.stock']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_base_inputs_cannot_override_variation_prices_and_stock(): void
    {
        $this->post(route('seller.products.store'), [...$this->optionPayload(), 'price' => 9999, 'stock' => 9999])->assertSessionHasNoErrors();
        $this->assertEquals(120.25, Product::sole()->price);
        $this->assertSame(10, Product::sole()->stock);
    }

    public function test_base_mode_requires_its_own_price_and_stock(): void
    {
        $this->post(route('seller.products.store'), ['name' => 'Missing base values', 'category' => 'Pet Supplies', 'weight_kg' => 1, 'variations_enabled' => 0])->assertSessionHasErrors(['price', 'stock']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_editing_full_variation_prices_keeps_option_ids_and_updates_totals(): void
    {
        $this->post(route('seller.products.store'), $this->optionPayload())->assertSessionHasNoErrors();
        $product = Product::with('variations')->sole();
        $payload = $this->optionPayload();
        foreach ($product->variations as $index => $variation) {
            $payload['variations'][$index]['id'] = $variation->id;
        }
        $payload['variations'][0]['price'] = 160;
        $payload['variations'][1]['price'] = 250;
        $payload['variations'][1]['stock'] = 1;
        $this->put(route('seller.products.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertEquals(160, $product->fresh()->price);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertEquals([0, 90], $product->variations()->orderBy('id')->pluck('price_adjustment')->all());
        $this->assertSame($product->variations->modelKeys(), $product->variations()->orderBy('id')->pluck('id')->all());
    }

    public function test_private_draft_retains_mode_and_full_prices_for_submission(): void
    {
        $this->post(route('seller.product-drafts.store'), $this->optionPayload())->assertSessionHasNoErrors();
        $draft = ProductDraft::sole();
        $this->assertEquals(1, $draft->payload['variations_enabled']);
        $this->assertEquals(200.50, $draft->payload['variations'][1]['price']);
        $this->get(route('seller.product-drafts.edit', $draft))->assertOk()->assertSee('Enable variations')->assertSee('200.5');
        $this->post(route('seller.products.store'), ['draft_id' => $draft->id])->assertSessionHasNoErrors();
        $this->assertSame(10, Product::sole()->stock);
    }

    public function test_switching_back_to_base_retires_options_without_hiding_base_listing(): void
    {
        $this->post(route('seller.products.store'), $this->optionPayload())->assertSessionHasNoErrors();
        $product = Product::sole();
        $this->put(route('seller.products.update', $product), [...$this->base(), 'variations_enabled' => 0])->assertSessionHasNoErrors();
        $this->assertSame(0, $product->variations()->where('is_active', true)->count());
        $this->assertSame(2, $product->variations()->count());
        $this->assertSame(5, $product->fresh()->stock);
        $product->forceFill(['compliance_status' => 'approved'])->save();
        $this->assertTrue(Product::availableForMarketplace()->whereKey($product->id)->exists());
        $this->flushSession();
        $buyer = User::forceCreate(['email' => 'mode-buyer@example.test', 'password' => 'Password123!', 'role' => 'buyer', 'status' => 'approved', 'first_name' => 'Mode', 'last_name' => 'Buyer', 'contact_no' => '09123456789']);
        $this->actingAs($buyer)->get(route('product.show', $product))->assertOk();
        $this->post(route('cart.add', $product), ['quantity' => 1])->assertRedirect(route('cart.index'))->assertSessionHasNoErrors();
        $cart = app(BuyerCartService::class)->display($buyer);
        $this->assertTrue(collect($cart)->sole()['available']);
        $this->assertSame(10000, collect($cart)->sole()['unit_cents']);
        $quote = app(CheckoutPricingService::class)->quote([['product_id' => $product->id, 'variation_id' => null, 'quantity' => 1]], context: ['buyer' => $buyer]);
        $this->assertSame(10000, $quote['subtotal_cents']);
        $this->assertSame('low', app(StockSubscriptionService::class)->state($product->fresh()));
        $this->post(route('cart.add', $product), ['quantity' => 1, 'variation_id' => $product->variations()->first()->id])->assertUnprocessable();
    }

    public function test_legacy_retired_only_products_remain_hidden_and_cannot_be_bought_as_base(): void
    {
        $product = Product::forceCreate([...array_diff_key($this->base(), ['weight_kg' => true]), 'user_id' => auth()->id(), 'weight_grams' => 1000, 'compliance_status' => 'approved', 'is_archived' => false]);
        $product->variations()->forceCreate(['type' => 'Size', 'value' => 'Old', 'stock' => 5, 'is_active' => false]);
        $this->assertNull($product->fresh()->variations_enabled);
        $this->assertFalse(Product::availableForMarketplace()->whereKey($product->id)->exists());
        $this->get(route('product.show', $product))->assertNotFound();
        $this->assertSame('unavailable', app(StockSubscriptionService::class)->state($product->fresh()));
        $this->expectException(ValidationException::class);
        app(CheckoutPricingService::class)->quote([['product_id' => $product->id, 'variation_id' => null, 'quantity' => 1]]);
    }

    public function test_retired_option_cancellation_restores_units_without_inflating_active_stock_and_is_once(): void
    {
        $this->post(route('seller.products.store'), $this->optionPayload())->assertSessionHasNoErrors();
        $product = Product::sole();
        $retired = $product->variations()->orderBy('id')->first();
        $retired->forceFill(['is_active' => false, 'stock' => 0])->save();
        $product->update(['stock' => 7]);
        $order = $this->restorationOrder($product, $retired->id);
        app(InventoryRestorationService::class)->restore($order);
        app(InventoryRestorationService::class)->restore($order);
        $this->assertSame(2, $retired->fresh()->stock);
        $this->assertFalse((bool) $retired->fresh()->is_active);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(7, (int) $product->variations()->where('is_active', true)->sum('stock'));
        $this->assertSame(1, DB::table('administrative_audits')->where('action', 'retired_variation_stock_restored')->count());
        $this->assertNotNull($order->fresh()->inventory_restored_at);
    }

    public function test_active_option_restoration_updates_product_and_option_once(): void
    {
        $this->post(route('seller.products.store'), $this->optionPayload())->assertSessionHasNoErrors();
        $product = Product::sole();
        $option = $product->variations()->first();
        $order = $this->restorationOrder($product, $option->id);
        app(InventoryRestorationService::class)->restore($order);
        app(InventoryRestorationService::class)->restore($order);
        $this->assertSame(12, $product->fresh()->stock);
        $this->assertSame(5, $option->fresh()->stock);
        $this->assertSame(12, (int) $product->variations()->where('is_active', true)->sum('stock'));
    }

    public function test_retired_restoration_after_base_conversion_does_not_add_units_to_base_pool(): void
    {
        $this->post(route('seller.products.store'), $this->optionPayload())->assertSessionHasNoErrors();
        $product = Product::sole();
        $option = $product->variations()->first();
        $order = $this->restorationOrder($product, $option->id);
        $this->put(route('seller.products.update', $product), [...$this->base(), 'variations_enabled' => 0])->assertSessionHasNoErrors();
        app(InventoryRestorationService::class)->restore($order);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(5, $option->fresh()->stock);
        $this->assertFalse($product->fresh()->usesVariations());
    }

    public function test_mode_migration_roundtrip_preserves_products_and_legacy_fallback(): void
    {
        $this->post(route('seller.products.store'), $this->optionPayload())->assertSessionHasNoErrors();
        $product = Product::sole();
        $migration = require database_path('migrations/2026_11_15_062908_add_variations_enabled_to_products_table.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseCount('products', 1);
        $this->assertNull($product->fresh()->variations_enabled);
        $this->assertTrue($product->fresh()->usesVariations());
        $this->assertSame(2, $product->variations()->count());
    }

    public function test_explicit_conversion_of_retired_legacy_listing_requires_compliance_review(): void
    {
        $product = Product::forceCreate([...array_diff_key($this->base(), ['weight_kg' => true]), 'user_id' => auth()->id(), 'weight_grams' => 1000, 'compliance_status' => 'approved', 'is_archived' => false]);
        $product->variations()->forceCreate(['type' => 'Size', 'value' => 'Old', 'stock' => 5, 'is_active' => false]);
        $this->put(route('seller.products.update', $product), [...$this->base(), 'variations_enabled' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($product->fresh()->variations_enabled);
        $this->assertSame('pending_review', $product->fresh()->compliance_status);
        $this->assertFalse(Product::availableForMarketplace()->whereKey($product->id)->exists());
    }

    public function test_inspected_partial_return_restores_only_approved_retired_units(): void
    {
        $this->post(route('seller.products.store'), $this->optionPayload())->assertSessionHasNoErrors();
        $product = Product::sole();
        $retired = $product->variations()->first();
        $retired->forceFill(['is_active' => false, 'stock' => 0])->save();
        $product->update(['stock' => 7]);
        $order = $this->restorationOrder($product, $retired->id);
        $order->forceFill(['status' => 'RETURNED_TO_SELLER', 'restock_inspected_at' => now(), 'restock_inspected_by' => auth()->id(), 'restock_approved' => true])->save();
        ReturnInspectionItem::create(['order_id' => $order->id, 'order_item_id' => $order->items()->sole()->id, 'expected_quantity' => 2, 'restock_quantity' => 1, 'excluded_quantity' => 1, 'inspected_by' => auth()->id(), 'created_at' => now()]);
        app(InventoryRestorationService::class)->restore($order);
        app(InventoryRestorationService::class)->restore($order);
        $this->assertSame(1, $retired->fresh()->stock);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertFalse((bool) $retired->fresh()->is_active);
        $this->assertNotNull($order->fresh()->inventory_restored_at);
    }

    private function restorationOrder(Product $product, int $optionId): Order
    {
        $order = Order::forceCreate(['order_number' => 'MODE-'.Str::uuid(), 'buyer_id' => auth()->id(), 'seller_id' => $product->user_id, 'recipient_name' => 'Test Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Test only', 'subtotal' => 200, 'shipping_fee' => 50, 'total_amount' => 250, 'payment_method' => 'COD', 'status' => 'CANCELLED']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'variation_id' => $optionId, 'product_name' => $product->name, 'quantity' => 2, 'unit_price' => 100, 'item_total' => 200]);

        return $order;
    }

    private function base(): array
    {
        return ['name' => 'Pricing mode bag', 'category' => 'Pet Supplies', 'weight_kg' => 1, 'price' => 100, 'stock' => 5];
    }

    private function optionPayload(): array
    {
        return ['name' => 'Pricing mode bag', 'category' => 'Pet Supplies', 'weight_kg' => 1, 'variations_enabled' => 1, 'variations' => [
            ['type' => 'Size', 'value' => 'Small', 'price' => 120.25, 'stock' => 3],
            ['type' => 'Size', 'value' => 'Large', 'price' => 200.50, 'stock' => 7],
        ]];
    }
}
