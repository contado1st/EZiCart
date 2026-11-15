<?php

namespace Tests\Feature;

use App\Models\BundleDeal;
use App\Models\FlashSale;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\BundleDealService;
use App\Services\CheckoutPricingService;
use App\Services\FlashReminderService;
use App\Services\FlashSaleService;
use App\Services\InventoryRestorationService;
use App\Services\OrderTransitionService;
use App\Services\ParcelLoadService;
use App\Services\PlacePurchaseService;
use App\Services\ProductSaleService;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MissingFlashBundleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->freezeSecond();
        config(['financial.sandbox_enabled' => true]);
    }

    public function test_flash_slot_starts_at_boundary_splits_units_and_exhaustion_restores_fallback_price(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $slot = $this->flash($seller, $product, ['quantity_limit' => 1]);
        $this->assertSame(45000, $this->quote([$this->line($product)])['lines'][0]['unit_cents']);
        $this->travelTo($slot->starts_at);
        $quote = $this->quote([$this->line($product, 2)]);
        $this->assertSame([29900, 45000], array_column($quote['lines'], 'unit_cents'));
        $this->assertSame(74900, $quote['subtotal_cents']);
        $this->assertSame([1, 1], array_column($quote['lines'], 'quantity'));
        $this->get(route('product.show', $product))->assertOk()->assertSee('1 flash units left')->assertSee('data-flash-timer', false);
        $buyer = $this->user('buyer');
        $order = $this->place($buyer, [$this->line($product, 2)], $quote);
        $this->assertSame([299.0, 450.0], $order->items->map(fn ($item) => (float) $item->unit_price)->all());
        $this->assertSame(2, $order->items->sum('quantity'));
        $this->assertSame(1, $slot->fresh()->allocated_quantity);
        $this->assertSame(18, $product->fresh()->stock);
        $this->assertSame(45000, $this->quote([$this->line($product)])['lines'][0]['unit_cents']);
        $this->as($buyer)->get(route('buyer.orders.show', $order))->assertOk()->assertSee('Flash price')->assertSee('299.00');
        $this->as($seller)->get(route('seller.orders.show', $order))->assertOk()->assertSee('Flash price');
    }

    #[TestWith([0, 299, 'quantity_limit'])]
    #[TestWith([21, 299, 'quantity_limit'])]
    #[TestWith([1, 450, 'price'])]
    #[TestWith([1, 0.99, 'price'])]
    public function test_flash_quantity_and_price_boundaries(int $quantity, float $price, string $field): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $this->as($seller)->post(route('seller.flash-bundles.flash'), [...$this->slotData($product), 'quantity_limit' => $quantity, 'price' => $price])->assertSessionHasErrors($field);
        $this->assertDatabaseCount('flash_sales', 0);
    }

    public function test_full_quantity_and_valid_price_are_saved_once_and_overlaps_and_foreign_products_reject(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $data = [...$this->slotData($product), 'quantity_limit' => 20, 'price' => 449.99];
        $this->as($seller)->post(route('seller.flash-bundles.flash'), $data)->assertSessionHasNoErrors();
        $this->post(route('seller.flash-bundles.flash'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('flash_sales', 1);
        $this->post(route('seller.flash-bundles.flash'), [...$data, 'operation_key' => (string) Str::uuid()])->assertSessionHasErrors('starts_at');
        $this->as($this->user())->post(route('seller.flash-bundles.flash'), [...$data, 'operation_key' => (string) Str::uuid()])->assertNotFound();
        $this->as($seller)->get(route('seller.flash-bundles.index'))->assertOk()->assertSee('Upcoming')->assertSee($product->name);
    }

    public function test_store_sale_is_fallback_for_partial_flash_and_ends_at_slot_boundary(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $schedule = $this->slotData($product);
        app(ProductSaleService::class)->save($seller, ['product_id' => $product->id, 'kind' => 'percent', 'value' => 20, 'starts_at' => $schedule['starts_at'], 'ends_at' => $schedule['ends_at'], 'operation_key' => (string) Str::uuid()]);
        $slot = $this->flash($seller, $product, ['quantity_limit' => 1]);
        $this->travelTo($slot->starts_at);
        $quote = $this->quote([$this->line($product, 2)]);
        $this->assertSame([29900, 36000], array_column($quote['lines'], 'unit_cents'));
        $this->assertSame(65900, $quote['subtotal_cents']);
        $this->get(route('search.index', ['min_price' => 298, 'max_price' => 300]))->assertOk()->assertViewHas('products', fn ($rows) => $rows->total() === 1);
        $this->travelTo($slot->ends_at);
        $this->assertSame(45000, $this->quote([$this->line($product)])['lines'][0]['unit_cents']);
    }

    public function test_stale_flash_quote_cannot_consume_last_unit_twice(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $slot = $this->flash($seller, $product, ['quantity_limit' => 1]);
        $this->travelTo($slot->starts_at);
        $items = [$this->line($product)];
        $stale = $this->quote($items);
        $this->place($this->user('buyer'), $items, $stale);
        try {
            $this->place($this->user('buyer'), $items, $stale);
            $this->fail('Stale flash price accepted.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('quote', $error->errors());
        }
        $this->assertSame(1, $slot->fresh()->allocated_quantity);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('flash_reservations', 1);
    }

    public function test_active_unpaid_cancellation_restores_stock_and_flash_capacity_once(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $slot = $this->flash($seller, $product, ['quantity_limit' => 2]);
        $this->travelTo($slot->starts_at);
        $items = [$this->line($product, 2)];
        $order = $this->place($this->user('buyer'), $items, $this->quote($items), true);
        $this->assertSame(2, $slot->fresh()->allocated_quantity);
        $this->travelTo($order->wallet_payment_due_at);
        $this->assertTrue(app(OrderTransitionService::class)->expireWalletPayment($order));
        app(InventoryRestorationService::class)->restore($order->fresh());
        $this->assertFalse(app(OrderTransitionService::class)->expireWalletPayment($order->fresh()));
        $this->assertSame(20, $product->fresh()->stock);
        $this->assertSame(0, $slot->fresh()->allocated_quantity);
        $this->assertNotNull(DB::table('flash_reservations')->sole()->released_at);
    }

    public function test_ended_slot_does_not_regain_flash_capacity_when_its_unpaid_order_expires(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $slot = $this->flash($seller, $product, ['ends_at' => now()->addMinutes(10)->toIso8601String()]);
        $this->travelTo($slot->starts_at);
        $items = [$this->line($product)];
        $order = $this->place($this->user('buyer'), $items, $this->quote($items), true);
        $this->travelTo($order->wallet_payment_due_at);
        app(OrderTransitionService::class)->expireWalletPayment($order);
        $this->assertSame(20, $product->fresh()->stock);
        $this->assertSame(1, $slot->fresh()->allocated_quantity);
        $this->assertSame(45000, $this->quote($items)['lines'][0]['unit_cents']);
    }

    public function test_checkout_refresh_keeps_original_cart_keys_and_quantities_after_price_split(): void
    {
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $product = $this->product($seller);
        $slot = $this->flash($seller, $product, ['quantity_limit' => 1]);
        $this->travelTo($slot->starts_at);
        $this->as($buyer)->post(route('cart.add', $product), ['quantity' => 2])->assertSessionHasNoErrors();
        $key = (string) $product->id;
        $this->postJson(route('cart.selection-quote'), ['selected_keys' => [$key]])->assertOk()->assertJsonPath('subtotal_cents', 74900);
        $this->post(route('cart.checkout'), ['selected_keys' => [$key]])->assertSessionHasNoErrors();
        $this->get(route('checkout.index'))->assertOk()->assertSee('Flash price');
        $this->assertSame(2, session('checkout_context.items')[$key]['quantity']);
        $this->get(route('checkout.index'))->assertOk();
        $this->post(route('checkout.process'), [...$this->address(), 'payment_method' => 'COD', 'checkout_token' => session('checkout_token'), 'quote_hash' => session('checkout_quote_hash')])->assertSessionHasNoErrors();
        $this->assertSame(2, OrderItem::sum('quantity'));
        $this->assertSame([], json_decode(DB::table('buyer_carts')->where('user_id', $buyer->id)->value('items'), true));
    }

    public function test_percentage_bundle_counts_mixed_variants_and_exact_discount_then_removes_when_under_threshold(): void
    {
        $seller = $this->user();
        $product = $this->product($seller, ['price' => 199]);
        $variants = [];
        foreach ([0, 50, 50] as $i => $adjustment) {
            $variants[] = $product->variations()->create(['type' => 'Size', 'value' => 'Option '.$i, 'price_adjustment' => $adjustment, 'stock' => 5]);
        }
        $deal = $this->bundle($seller, [$product], ['threshold' => 3, 'percent' => 15]);
        $this->travelTo($deal->starts_at);
        $items = array_map(fn ($variant) => $this->line($product, 1, $variant->id), $variants);
        $full = $this->quote($items);
        $this->assertSame(59245, $full['subtotal_cents']);
        $this->assertSame(10455, 69700 - $full['subtotal_cents']);
        array_pop($items);
        $this->assertSame(44800, $this->quote($items)['subtotal_cents']);
        $this->assertSame(3, array_sum(array_column($full['lines'], 'quantity')));
    }

    public function test_bundle_rounding_keeps_each_unit_and_order_total_exact(): void
    {
        $seller = $this->user();
        $product = $this->product($seller, ['price' => 1.03]);
        $deal = $this->bundle($seller, [$product], ['percent' => 15]);
        $this->travelTo($deal->starts_at);
        $quote = $this->quote([$this->line($product, 2)]);
        $this->assertSame(175, $quote['subtotal_cents']);
        $this->assertSame([87, 88], array_column($quote['lines'], 'unit_cents'));
        $order = $this->place($this->user('buyer'), [$this->line($product, 2)], $quote);
        $this->assertSame(175, (int) round($order->items->sum('item_total') * 100));
        foreach ($order->items as $item) {
            $this->assertSame((float) $item->item_total, (float) $item->unit_price * $item->quantity);
        }
    }

    public function test_buy_x_get_y_is_reversible_uses_selected_gift_and_retains_weight_stock_and_caps(): void
    {
        $seller = $this->user();
        $qualifier = $this->product($seller);
        $gift = $this->product($seller, ['price' => 350, 'weight_grams' => 2000]);
        $deal = $this->bundle($seller, [$qualifier], ['kind' => 'gift', 'gift_product_id' => $gift->id]);
        $this->travelTo($deal->starts_at);
        $items = [$this->line($qualifier, 2), $this->line($gift)];
        $quote = $this->quote($items);
        $this->assertSame(90000, $quote['subtotal_cents']);
        $giftLines = array_values(array_filter($quote['lines'], fn ($line) => $line['product_id'] === $gift->id));
        $this->assertSame(0, $giftLines[0]['unit_cents']);
        $this->assertSame(80000, $this->quote([$this->line($qualifier), $this->line($gift)])['subtotal_cents']);
        $order = $this->place($this->user('buyer'), $items, $quote);
        $this->assertSame(4000, app(ParcelLoadService::class)->estimate($order));
        $this->assertSame(18, $qualifier->fresh()->stock);
        $this->assertSame(19, $gift->fresh()->stock);
        $gift->forceFill(['purchase_limit' => 1])->save();
        $this->as($order->buyer)->post(route('cart.add', $gift), ['quantity' => 1])->assertSessionHasErrors('quantity');
        $gift->update(['stock' => 0]);
        try {
            $this->quote($items);
            $this->fail('Gift stock bypassed.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('cart', $error->errors());
        }
    }

    public function test_bundle_selection_quote_recalculates_after_unchecking_a_qualifying_item(): void
    {
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $first = $this->product($seller, ['price' => 100]);
        $second = $this->product($seller, ['price' => 200]);
        $deal = $this->bundle($seller, [$first, $second], ['percent' => 10]);
        $this->travelTo($deal->starts_at);
        $this->as($buyer)->post(route('cart.add', $first), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->post(route('cart.add', $second), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->postJson(route('cart.selection-quote'), ['selected_keys' => [(string) $first->id, (string) $second->id]])->assertOk()->assertJsonPath('subtotal_cents', 27000);
        $this->postJson(route('cart.selection-quote'), ['selected_keys' => [(string) $first->id]])->assertOk()->assertJsonPath('subtotal_cents', 10000);
        $this->get(route('cart.index'))->assertOk()->assertSee('Bundle price');
        $this->postJson(route('cart.selection-quote'), ['selected_keys' => ['999999']])->assertUnprocessable();
    }

    public function test_bundle_validation_rejects_cross_shop_gifts_and_overlapping_flash_without_double_discount(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $foreign = $this->product($this->user());
        $data = $this->bundleData([$product]);
        $this->as($seller)->post(route('seller.flash-bundles.bundle'), [...$data, 'kind' => 'gift', 'gift_product_id' => $foreign->id])->assertSessionHasErrors('product_ids');
        $deal = $this->bundle($seller, [$product]);
        $this->post(route('seller.flash-bundles.flash'), $this->slotData($product))->assertSessionHasErrors('starts_at');
        $this->post(route('seller.flash-bundles.bundle'), [...$data, 'operation_key' => (string) Str::uuid()])->assertSessionHasErrors('product_ids');
        $this->as($this->user())->post(route('seller.flash-bundles.bundle.end', $deal))->assertForbidden();
        $this->as($seller)->post(route('seller.flash-bundles.bundle.end', $deal))->assertSessionHasNoErrors();
        $this->post(route('seller.flash-bundles.bundle.end', $deal))->assertSessionHasNoErrors();
    }

    public function test_split_prices_do_not_create_multiple_review_entitlements_for_one_product(): void
    {
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $product = $this->product($seller);
        $slot = $this->flash($seller, $product, ['quantity_limit' => 1]);
        $this->travelTo($slot->starts_at);
        $items = [$this->line($product, 2)];
        $order = $this->place($buyer, $items, $this->quote($items));
        $order->forceFill(['status' => 'COMPLETED'])->save();
        $data = ['product_id' => $product->id, 'rating' => 5];
        app(ReviewService::class)->save($buyer, $order, $data, []);
        try {
            app(ReviewService::class)->save($buyer, $order, $data, []);
            $this->fail('Second review allowed because of price segments.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('review', $error->errors());
        }
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_opted_in_reminder_is_delivered_once_before_start_and_can_be_cancelled(): void
    {
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $product = $this->product($seller);
        $slot = $this->flash($seller, $product, ['starts_at' => now()->addMinutes(5)->toIso8601String()]);
        $this->as($buyer)->post(route('buyer.flash-reminders.store', $slot), ['enabled' => 1])->assertSessionHasNoErrors();
        $this->post(route('buyer.flash-reminders.store', $slot), ['enabled' => 1])->assertSessionHasNoErrors();
        $this->assertSame(0, app(FlashReminderService::class)->deliver());
        $this->travelTo($slot->starts_at->copy()->subMinute());
        $this->assertSame(1, app(FlashReminderService::class)->deliver());
        $this->assertSame(0, app(FlashReminderService::class)->deliver());
        $this->assertDatabaseCount('notifications', 1);
        $notification = json_decode(DB::table('notifications')->sole()->data, true);
        $this->assertSame(route('product.show', $product), $notification['url']);
        $this->assertTrue($slot->starts_at->gt(DB::table('flash_reminders')->sole()->notified_at));
        $other = $this->user('buyer');
        $this->as($other)->post(route('buyer.flash-reminders.store', $slot), ['enabled' => 0])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('flash_reminders')->count());
    }

    public function test_withdrawn_unsubscribed_or_late_reminders_do_not_send_an_advance_alert(): void
    {
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $product = $this->product($seller);
        $slot = $this->flash($seller, $product, ['starts_at' => now()->addMinutes(5)->toIso8601String()]);
        $this->as($buyer)->post(route('buyer.flash-reminders.store', $slot), ['enabled' => 1])->assertSessionHasNoErrors();
        $this->post(route('buyer.flash-reminders.store', $slot), ['enabled' => 0])->assertSessionHasNoErrors();
        $this->travelTo($slot->starts_at->copy()->subMinute());
        $this->assertSame(0, app(FlashReminderService::class)->deliver());
        $this->post(route('buyer.flash-reminders.store', $slot), ['enabled' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('notifications', 1);
        app(FlashSaleService::class)->end($seller, $slot);
        $this->as($this->user('buyer'))->post(route('buyer.flash-reminders.store', $slot), ['enabled' => 1])->assertNotFound();
        $this->assertSame(0, app(FlashReminderService::class)->deliver());
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_flash_bundle_migration_down_and_up_preserves_existing_commerce_rows(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $migration = require database_path('migrations/2026_11_14_224226_add_flash_sales_bundles_and_reminders.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('flash_sales'));
        $this->assertNotNull($product->fresh());
        $migration->up();
        $this->assertTrue(Schema::hasTable('flash_reminders'));
        $this->assertSame(450.0, (float) $product->fresh()->price);
    }

    public function test_catalog_price_reduction_hides_incompatible_flash_price_and_reminder(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $slot = $this->flash($seller, $product);
        $this->travelTo($slot->starts_at);
        $product->update(['price' => 200]);
        $this->assertSame(20000, $this->quote([$this->line($product)])['lines'][0]['unit_cents']);
        $this->get(route('product.show', $product))->assertOk()->assertDontSee('flash units left');
        $this->as($this->user('buyer'))->post(route('buyer.flash-reminders.store', $slot), ['enabled' => 1])->assertNotFound();
    }

    public function test_late_subscription_reports_live_status_without_repeated_delivery(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $slot = $this->flash($seller, $product);
        $buyer = $this->user('buyer');
        $this->travelTo($slot->starts_at);
        $this->as($buyer)->post(route('buyer.flash-reminders.store', $slot), ['enabled' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Flash sale is live', json_decode(DB::table('notifications')->sole()->data, true)['title']);
        $this->post(route('buyer.flash-reminders.store', $slot), ['enabled' => 0])->assertSessionHasNoErrors();
        $this->post(route('buyer.flash-reminders.store', $slot), ['enabled' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertNull(DB::table('flash_reminders')->sole()->cancelled_at);
    }

    public function test_seller_cannot_lower_store_sale_below_an_overlapping_flash_price(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $slot = $this->flash($seller, $product);
        $this->as($seller)->post(route('seller.sales.store'), ['product_id' => $product->id, 'kind' => 'fixed', 'value' => 250, 'starts_at' => $slot->starts_at->toIso8601String(), 'ends_at' => $slot->ends_at->toIso8601String(), 'operation_key' => (string) Str::uuid()])->assertSessionHasErrors('value');
        $this->assertDatabaseCount('product_sales', 0);
    }

    private function user(string $role = 'seller'): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.com', 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'business_name' => 'Promotion shop', 'first_name' => 'Promo', 'last_name' => 'Tester', 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion']);
    }

    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function product(User $seller, array $data = []): Product
    {
        return Product::forceCreate(['user_id' => $seller->id, 'name' => 'Flash item '.Str::random(4), 'category' => 'Home and Garden', 'price' => 450, 'stock' => 20, 'weight_grams' => 1000, 'compliance_status' => 'approved', 'is_archived' => false, ...$data]);
    }

    private function slotData(Product $product): array
    {
        return ['product_id' => $product->id, 'price' => 299, 'quantity_limit' => 3, 'starts_at' => now()->addMinute()->toIso8601String(), 'ends_at' => now()->addHour()->toIso8601String(), 'operation_key' => (string) Str::uuid()];
    }

    private function flash(User $seller, Product $product, array $data = []): FlashSale
    {
        return app(FlashSaleService::class)->create($seller, [...$this->slotData($product), ...$data]);
    }

    private function bundleData(array $products): array
    {
        return ['name' => 'Qualifying deal', 'kind' => 'percent', 'threshold' => 2, 'percent' => 15, 'gift_quantity' => 1, 'product_ids' => array_map(fn ($product) => $product->id, $products), 'starts_at' => now()->addMinute()->toIso8601String(), 'ends_at' => now()->addHour()->toIso8601String(), 'operation_key' => (string) Str::uuid()];
    }

    private function bundle(User $seller, array $products, array $data = []): BundleDeal
    {
        return app(BundleDealService::class)->create($seller, [...$this->bundleData($products), ...$data]);
    }

    private function line(Product $product, int $quantity = 1, ?int $variation = null): array
    {
        return ['product_id' => $product->id, 'variation_id' => $variation, 'quantity' => $quantity];
    }

    private function quote(array $items): array
    {
        return app(CheckoutPricingService::class)->quote($items);
    }

    private function place(User $buyer, array $items, array $quote, bool $defer = false)
    {
        $token = (string) Str::uuid();

        return app(PlacePurchaseService::class)->place($buyer, $token, hash('sha256', $token), $items, $quote['hash'], null, $this->address(), $defer ? 'WALLET' : 'COD', $defer)->orders()->firstOrFail();
    }

    private function address(): array
    {
        return ['recipient_name' => 'Buyer Tester', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Fixture Lane'];
    }
}
