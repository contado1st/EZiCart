<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDraft;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use App\Services\BuyerCartService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartialCatalogIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_incomplete_draft_and_media_are_private_and_never_catalog_records(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        $seller = $this->user();
        $this->actingAs($seller)->post(route('seller.product-drafts.store'), ['name' => 'Private draft bag', 'image' => UploadedFile::fake()->image('cover.jpg')])->assertSessionHasNoErrors();
        $draft = ProductDraft::sole();
        $this->assertDatabaseCount('products', 0);
        Storage::disk('private')->assertExists($draft->uploads['image']['path']);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->get(route('seller.product-drafts.index'))->assertOk()->assertSee('Private draft bag');
        $this->get(route('seller.product-drafts.edit', $draft))->assertOk()->assertSee('Private draft bag')->assertSee('Save private draft');
        $this->get(route('seller.product-drafts.media', [$draft, 'image']))->assertOk();
        $this->flushSession();
        $this->actingAs($this->user())->get(route('seller.product-drafts.media', [$draft, 'image']))->assertNotFound();
        $this->post(route('seller.product-drafts.update', $draft), ['name' => 'Hijacked'])->assertNotFound();
        $this->assertSame('Private draft bag', $draft->fresh()->payload['name']);
        $this->flushSession();
        $this->actingAs($this->user('buyer'))->get(route('search.index', ['search' => 'Private draft bag']))->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 0);
        $this->post(route('seller.product-drafts.store'), ['name' => 'Buyer draft'])->assertForbidden();
    }

    public function test_empty_draft_can_be_listed_but_cannot_be_submitted_without_required_details(): void
    {
        Storage::fake('private');
        $this->actingAs($this->user())->post(route('seller.product-drafts.store'), [])->assertSessionHasNoErrors();
        $draft = ProductDraft::sole();
        $this->get(route('seller.product-drafts.index'))->assertOk()->assertSee('Untitled product');
        $this->post(route('seller.products.store'), ['draft_id' => $draft->id])->assertSessionHasErrors(['name', 'category', 'price', 'stock', 'weight_kg']);
        $this->assertDatabaseCount('products', 0);
        $this->assertNull($draft->fresh()->submitted_at);
    }

    public function test_complete_draft_submission_preserves_media_variants_and_is_idempotent(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        $seller = $this->user();
        $this->actingAs($seller)->post(route('seller.product-drafts.store'), [...$this->payload(),
            'image' => UploadedFile::fake()->image('cover.jpg'), 'gallery_images' => [UploadedFile::fake()->image('side.jpg')],
            'product_video' => UploadedFile::fake()->createWithContent('clip.mp4', file_get_contents(base_path('tests/Fixtures/media/product-short.mp4')))->mimeType('video/mp4'),
            'variations_present' => 1, 'variations' => [['type' => 'Color', 'value' => 'Blue', 'sku' => 'BAG-BLUE', 'stock' => 3, 'price_adjustment' => 20, 'image' => UploadedFile::fake()->image('blue.jpg')]],
        ])->assertSessionHasNoErrors();
        $draft = ProductDraft::sole();
        $this->assertCount(4, $draft->uploads);
        $this->post(route('seller.products.store'), ['draft_id' => $draft->id])->assertSessionHasNoErrors()->assertRedirectToRoute('seller.products.index');
        $product = Product::sole();
        $this->assertSame('pending_review', $product->compliance_status);
        $this->assertSame('BAG-BLUE', $product->variations()->sole()->sku);
        $this->assertSame(3, (int) $product->stock);
        $this->assertCount(2, $product->media);
        Storage::disk('public')->assertExists($product->image_path);
        $this->assertSame([], $draft->fresh()->uploads);
        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->post(route('seller.products.store'), ['draft_id' => $draft->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 1);
        $this->get(route('product.show', $product))->assertNotFound();
    }

    public function test_draft_media_can_be_removed_and_invalid_submission_keeps_the_remaining_files(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        $this->actingAs($this->user())->post(route('seller.product-drafts.store'), ['name' => 'Draft', 'image' => UploadedFile::fake()->image('cover.jpg')])->assertSessionHasNoErrors();
        $draft = ProductDraft::sole();
        $path = $draft->uploads['image']['path'];
        $this->post(route('seller.products.store'), ['draft_id' => $draft->id])->assertSessionHasErrors();
        Storage::disk('private')->assertExists($path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->post(route('seller.product-drafts.update', $draft), ['name' => 'Draft', 'clear_draft_media' => ['image']])->assertSessionHasNoErrors();
        Storage::disk('private')->assertMissing($path);
        $this->assertSame([], $draft->fresh()->uploads);
    }

    public function test_unapproved_accounts_keep_the_existing_operational_access_restriction(): void
    {
        $seller = $this->user();
        $seller->forceFill(['status' => 'pending'])->save();
        $this->actingAs($seller)->post(route('seller.product-drafts.store'), ['name' => 'Pending'])->assertRedirectToRoute('login');
        $this->assertDatabaseCount('product_drafts', 0);
    }

    public function test_draft_deletion_removes_private_files_and_changing_variant_attributes_does_not_reuse_the_old_image(): void
    {
        Storage::fake('private');
        $this->actingAs($this->user())->post(route('seller.product-drafts.store'), ['name' => 'Draft', 'variations' => [
            ['type' => 'Color', 'value' => 'Blue', 'image' => UploadedFile::fake()->image('blue.png')],
        ]])->assertSessionHasNoErrors();
        $draft = ProductDraft::sole();
        $path = $draft->uploads['variations.0.image']['path'];
        $this->post(route('seller.product-drafts.update', $draft), ['name' => 'Draft', 'variations' => [['type' => 'Color', 'value' => 'Red']]])->assertSessionHasNoErrors();
        Storage::disk('private')->assertMissing($path);
        $this->assertSame([], $draft->fresh()->uploads);
        $this->post(route('seller.product-drafts.update', $draft), ['name' => 'Draft', 'image' => UploadedFile::fake()->image('cover.png')])->assertSessionHasNoErrors();
        $path = $draft->fresh()->uploads['image']['path'];
        $this->delete(route('seller.product-drafts.destroy', $draft))->assertRedirectToRoute('seller.product-drafts.index');
        $this->assertDatabaseCount('product_drafts', 0);
        Storage::disk('private')->assertMissing($path);
    }

    public function test_declared_weight_and_supplied_dimensions_reject_invalid_values_and_keep_ten_grams_exactly(): void
    {
        Storage::fake('public');
        $this->actingAs($this->user());
        foreach ([0, -1, 'not-weight'] as $weight) {
            $this->post(route('seller.products.store'), [...$this->payload(), 'weight_kg' => $weight])->assertSessionHasErrors('weight_kg');
        }
        foreach (['length_mm', 'width_mm', 'height_mm'] as $field) {
            $this->post(route('seller.products.store'), [...$this->payload(), $field => 0])->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('products', 0);
        $this->post(route('seller.products.store'), [...$this->payload(), 'weight_kg' => 0.01])->assertSessionHasNoErrors();
        $this->assertSame(10, (int) Product::sole()->weight_grams);
    }

    public function test_pausing_sales_blocks_catalog_cart_and_checkout_without_erasing_cart_lines(): void
    {
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $product = $this->product($seller);
        app(BuyerCartService::class)->add($buyer, $product->id, null, 1);
        $this->actingAs($seller)->patch(route('seller.products.availability', $product), ['is_seller_active' => 0])->assertSessionHasNoErrors();
        $this->get(route('product.show', $product))->assertNotFound();
        $this->get(route('home'))->assertDontSee($product->name);
        $this->flushSession();
        $this->actingAs($buyer)->post(route('cart.add', $product), ['quantity' => 1])->assertNotFound();
        $this->get(route('cart.index'))->assertOk()->assertSee('Product is currently unavailable.');
        $this->assertCount(1, app(BuyerCartService::class)->read($buyer));
        $this->get(route('checkout.index'))->assertRedirectToRoute('cart.index')->assertSessionHas('error');
        $this->flushSession();
        $this->actingAs($seller)->patch(route('seller.products.availability', $product), ['is_seller_active' => 1])->assertSessionHasNoErrors();
        $this->get(route('product.show', $product))->assertOk();
    }

    public function test_resuming_sales_does_not_override_compliance_and_other_sellers_cannot_toggle(): void
    {
        $seller = $this->user();
        $product = $this->product($seller, ['compliance_status' => 'flagged', 'is_seller_active' => false]);
        $this->actingAs($seller)->patch(route('seller.products.availability', $product), ['is_seller_active' => 1, 'compliance_status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('flagged', $product->fresh()->compliance_status);
        $this->get(route('product.show', $product))->assertNotFound();
        $this->flushSession();
        $this->actingAs($this->user())->patch(route('seller.products.availability', $product), ['is_seller_active' => 0])->assertForbidden();
    }

    public function test_store_sku_validation_covers_other_products_and_retired_variations_but_not_other_stores(): void
    {
        Storage::fake('public');
        $seller = $this->user();
        $product = $this->product($seller);
        $variant = $product->variations()->forceCreate(['type' => 'Size', 'value' => 'Small', 'stock' => 1, 'sku' => '  BAG-S  ', 'is_active' => false]);
        $data = [...$this->payload(), 'variations' => [['type' => 'Size', 'value' => 'Medium', 'stock' => 1, 'sku' => 'bag-s']]];
        $this->actingAs($seller)->post(route('seller.products.store'), $data)->assertSessionHasErrors('variations.0.sku');
        $this->assertDatabaseCount('products', 1);
        $this->assertSame('BAG-S', $variant->fresh()->sku);
        $this->flushSession();
        $this->actingAs($this->user())->post(route('seller.products.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 2);
    }

    public function test_database_rejects_duplicate_store_skus_even_when_the_editor_is_bypassed(): void
    {
        $seller = $this->user();
        $a = $this->product($seller);
        $b = $this->product($seller);
        $a->variations()->create(['type' => 'Size', 'value' => 'S', 'sku' => 'SHOP-ONE', 'stock' => 1]);
        $this->expectException(UniqueConstraintViolationException::class);
        $b->variations()->create(['type' => 'Size', 'value' => 'M', 'sku' => ' shop-one ', 'stock' => 1]);
    }

    public function test_existing_variant_sku_can_be_kept_while_updating_stock(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $variant = $product->variations()->create(['type' => 'Size', 'value' => 'S', 'sku' => 'UNIQUE-S', 'stock' => 1]);
        $this->actingAs($seller)->put(route('seller.products.update', $product), [...$this->payload(), 'variations_present' => 1,
            'variations' => [['id' => $variant->id, 'type' => 'Size', 'value' => 'S', 'sku' => 'UNIQUE-S', 'stock' => 4]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(4, (int) $variant->fresh()->stock);
    }

    public function test_available_variant_range_appears_on_search_and_product_page_and_excludes_sold_out_options(): void
    {
        $product = $this->product($this->user(), ['price' => 199]);
        foreach ([0, 50, 100] as $adjustment) {
            $product->variations()->create(['type' => 'Size', 'value' => (string) $adjustment, 'stock' => 2, 'price_adjustment' => $adjustment]);
        }
        $product->variations()->create(['type' => 'Size', 'value' => 'Sold out', 'stock' => 0, 'price_adjustment' => 1000]);
        $this->get(route('search.index', ['search' => $product->name]))->assertOk()->assertSee('₱199.00 – ₱299.00');
        $this->get(route('product.show', $product))->assertOk()->assertSee('₱199.00 – ₱299.00');
    }

    public function test_order_purchase_image_is_recorded_and_survives_cover_replacement_and_archiving(): void
    {
        Notification::fake();
        Storage::fake('public');
        $seller = $this->user();
        $buyer = $this->user('buyer');
        $product = $this->product($seller, ['image_path' => 'products/old-cover.jpg']);
        Storage::disk('public')->put($product->image_path, 'purchased image');
        $this->actingAs($buyer)->post(route('cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->get(route('checkout.index'))->assertOk();
        $this->post(route('checkout.process'), ['recipient_name' => 'Buyer User', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Buyer Road', 'payment_method' => 'COD', 'checkout_token' => session('checkout_token'), 'quote_hash' => session('checkout_quote_hash')])->assertSessionHasNoErrors();
        $item = OrderItem::sole();
        $this->assertSame('products/old-cover.jpg', $item->purchased_image_path);
        $this->flushSession();
        $this->actingAs($seller)->put(route('seller.products.update', $product), [...$this->payload(), 'image' => UploadedFile::fake()->image('replacement.jpg')])->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists('products/old-cover.jpg');
        $this->delete(route('seller.products.destroy', $product))->assertSessionHasNoErrors();
        $this->assertTrue((bool) $product->fresh()->is_archived);
        $this->assertDatabaseCount('order_items', 1);
        $this->get(route('seller.orders.show', $item->order_id))->assertOk()->assertSee('products/old-cover.jpg');
        $this->flushSession();
        $this->actingAs($buyer)->get(route('buyer.orders.show', $item->order_id))->assertOk()->assertSee('products/old-cover.jpg');
        Notification::assertSentTo($seller, OrderWorkflowNotification::class);
    }

    public function test_search_price_filter_and_sort_use_the_lowest_available_variant_price(): void
    {
        $seller = $this->user();
        $varied = $this->product($seller, ['name' => 'Varied bag', 'price' => 100]);
        $varied->variations()->create(['type' => 'Size', 'value' => 'S', 'stock' => 2, 'price_adjustment' => 150]);
        $plain = $this->product($seller, ['name' => 'Plain bag', 'price' => 200]);
        $this->get(route('search.index', ['sort' => 'price_asc']))->assertOk()->assertViewHas('products', fn ($rows) => $rows->pluck('id')->all() === [$plain->id, $varied->id]);
        $this->get(route('search.index', ['min_price' => 240, 'max_price' => 260]))->assertOk()->assertViewHas('products', fn ($rows) => $rows->pluck('id')->all() === [$varied->id]);
    }

    private function user(string $role = 'seller'): User
    {
        return User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Catalog', 'last_name' => 'User', 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion']);
    }

    private function product(User $seller, array $overrides = []): Product
    {
        return Product::forceCreate([...array_diff_key($this->payload(), ['weight_kg' => true]), 'weight_grams' => 1000, 'user_id' => $seller->id, 'compliance_status' => 'approved', 'is_archived' => false, ...$overrides]);
    }

    private function payload(): array
    {
        return ['name' => 'Catalog journey bag', 'category' => 'Pet Supplies', 'price' => 100, 'stock' => 5, 'weight_kg' => 1, 'description' => 'A durable bag for the complete product journey.'];
    }
}
