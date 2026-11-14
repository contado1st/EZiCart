<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDraft;
use App\Models\Review;
use App\Models\StoreCategory;
use App\Models\User;
use App\Services\StoreCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MissingSellerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_store_categories_have_independent_names_and_platform_classification_and_public_filters(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $other = $this->product($seller, ['name' => 'Other item']);
        $category = $this->category($seller, 'Bags');
        $this->category($this->user(), 'Bags');
        $this->asUser($seller)->patch(route('seller.categories.assign', $product), ['categories' => [$category->id]])->assertSessionHasNoErrors();
        $this->get(route('seller.categories.index', ['product_id' => $product->id]))->assertOk()->assertSee('Bags')->assertSee('Save product categories');
        $this->get(route('seller.products.index'))->assertOk()->assertSee('Duplicate to draft');
        $this->get(route('shops.show', [$seller, 'store_category' => $category->id]))->assertOk()
            ->assertViewHas('products', fn ($rows) => $rows->modelKeys() === [$product->id]);
        $this->get(route('shops.show', [$seller, 'store_category' => 'uncategorized']))->assertOk()
            ->assertViewHas('products', fn ($rows) => $rows->modelKeys() === [$other->id]);
        $this->assertSame('Home and Garden', $product->fresh()->category);
    }

    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(["\u{00a0}\u{00a0}"])]
    #[TestWith(['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'])]
    public function test_invalid_category_names_do_not_persist(string $name): void
    {
        $this->asUser($this->user())->post(route('seller.categories.store'), $this->categoryData($name))->assertSessionHasErrors('name');
        $this->assertDatabaseCount('store_categories', 0);
    }

    public function test_category_name_boundaries_normalization_and_duplicate_rename(): void
    {
        $seller = $this->user();
        $category = $this->category($seller, 'Bags');
        foreach ([' bags ', 'BAGS'] as $name) {
            $this->post(route('seller.categories.store'), $this->categoryData($name))->assertSessionHasErrors('name');
        }
        $other = $this->category($seller, 'Kitchen');
        $this->patch(route('seller.categories.update', $other), $this->categoryData('Bags'))->assertSessionHasErrors('name');
        $this->assertSame('Kitchen', $other->fresh()->name);
        $this->category($seller, 'X');
        $this->category($seller, str_repeat('z', 50));
        $this->assertDatabaseCount('store_categories', 4);
    }

    public function test_hierarchy_depth_membership_limits_and_cross_store_mutations_are_rejected(): void
    {
        $seller = $this->user();
        $root = $this->category($seller, 'Home');
        $child = $this->category($seller, 'Lamps', $root->id);
        $this->post(route('seller.categories.store'), $this->categoryData('Too deep', $child->id))->assertSessionHasErrors('parent_id');
        $this->patch(route('seller.categories.update', $root), $this->categoryData('Home', $child->id))->assertSessionHasErrors('parent_id');
        $product = $this->product($seller);
        $third = $this->category($seller, 'Living');
        $fourth = $this->category($seller, 'Decor');
        $this->patch(route('seller.categories.assign', $product), ['categories' => [$root->id, $child->id, $third->id]])->assertSessionHasNoErrors();
        $this->assertSame(3, $product->storeCategories()->count());
        $this->patch(route('seller.categories.assign', $product), ['categories' => [$root->id, $child->id, $third->id, $fourth->id]])->assertSessionHasErrors('categories');
        $outsider = $this->user();
        $foreign = $this->category($outsider, 'Private navigation');
        $this->asUser($seller)->patch(route('seller.categories.assign', $product), ['categories' => [$foreign->id]])->assertSessionHasErrors('categories');
        $this->patch(route('seller.categories.update', $foreign), $this->categoryData('Hijacked'))->assertNotFound();
        $this->get(route('seller.categories.preview', $foreign))->assertNotFound();
        $this->patch(route('seller.categories.assign', $this->product($outsider)), ['categories' => [$root->id]])->assertNotFound();
        $this->get(route('shops.show', [$seller, 'store_category' => $foreign->id]))->assertNotFound();
    }

    public function test_rename_reorder_hide_and_restore_preserve_membership_and_searchability(): void
    {
        $seller = $this->user();
        $first = $this->category($seller, 'Bags');
        $second = $this->category($seller, 'Kitchen');
        $child = $this->category($seller, 'Small', $second->id);
        $product = $this->product($seller);
        $this->patch(route('seller.categories.assign', $product), ['categories' => [$second->id, $child->id]])->assertSessionHasNoErrors();
        $this->patch(route('seller.categories.update', $second), [...$this->categoryData('Dining'), 'position' => 1, 'is_visible' => 0])->assertSessionHasNoErrors();
        $this->assertSame([$second->id, $first->id], StoreCategory::whereNull('parent_id')->orderBy('sort_order')->pluck('id')->all());
        $this->get(route('shops.show', [$seller, 'store_category' => $second->id]))->assertNotFound();
        $this->get(route('shops.show', [$seller, 'store_category' => $child->id]))->assertNotFound();
        $this->get(route('search.index', ['search' => $product->name]))->assertOk()->assertSee($product->name);
        $this->get(route('product.show', $product))->assertOk();
        $this->patch(route('seller.categories.update', $second), $this->categoryData('Dining'))->assertSessionHasNoErrors();
        $this->get(route('shops.show', [$seller, 'store_category' => $child->id]))->assertOk()->assertViewHas('products', fn ($rows) => $rows->total() === 1);
        $this->assertSame(2, $product->storeCategories()->count());
    }

    public function test_deletion_preview_cancel_and_stale_confirmation_then_parent_delete_preserve_products(): void
    {
        $seller = $this->user();
        $root = $this->category($seller, 'Home');
        $child = $this->category($seller, 'Lamps', $root->id);
        $product = $this->product($seller);
        $this->patch(route('seller.categories.assign', $product), ['categories' => [$child->id]])->assertSessionHasNoErrors();
        $this->get(route('seller.categories.preview', $root))->assertOk()->assertSee('Lamps')->assertViewHas('product_count', 1);
        $stale = app(StoreCategoryService::class)->preview($seller, $root)['token'];
        $this->get(route('seller.categories.index'))->assertOk();
        $this->assertDatabaseCount('store_categories', 2);
        $second = $this->product($seller);
        $this->patch(route('seller.categories.assign', $second), ['categories' => [$child->id]])->assertSessionHasNoErrors();
        $this->delete(route('seller.categories.destroy', $root), ['preview_token' => $stale])->assertSessionHasErrors('category');
        $token = app(StoreCategoryService::class)->preview($seller, $root)['token'];
        $this->delete(route('seller.categories.destroy', $root), ['preview_token' => $token])->assertRedirectToRoute('seller.categories.index');
        $this->assertDatabaseCount('store_categories', 0);
        $this->assertDatabaseCount('product_store_category', 0);
        $this->assertTrue($product->fresh()->isListingVisible());
        $this->get(route('shops.show', [$seller, 'store_category' => 'uncategorized']))->assertOk()->assertViewHas('products', fn ($rows) => $rows->total() === 2);
    }

    public function test_deletion_retains_other_memberships_and_pagination(): void
    {
        $seller = $this->user();
        $first = $this->category($seller, 'Remove');
        $remaining = $this->category($seller, 'Keep');
        $product = $this->product($seller);
        $this->patch(route('seller.categories.assign', $product), ['categories' => [$first->id, $remaining->id]])->assertSessionHasNoErrors();
        $this->delete(route('seller.categories.destroy', $first), ['preview_token' => app(StoreCategoryService::class)->preview($seller, $first)['token']])->assertSessionHasNoErrors();
        $this->assertSame([$remaining->id], $product->storeCategories()->pluck('store_categories.id')->all());
        for ($i = 0; $i < 26; $i++) {
            app(StoreCategoryService::class)->save($seller, $this->categoryData('Page '.$i));
        }
        $this->get(route('seller.categories.index'))->assertOk()->assertViewHas('categories', fn ($rows) => $rows->count() === 25 && $rows->total() === 27);
    }

    public function test_duplication_is_private_repeat_safe_and_copies_media_with_fresh_variant_identity(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $seller = $this->user();
        $product = $this->product($seller, ['image_path' => 'products/cover.png']);
        $image = UploadedFile::fake()->image('cover.png');
        Storage::disk('public')->put($product->image_path, file_get_contents($image->getRealPath()));
        $variant = $product->variations()->create(['type' => 'Color', 'value' => 'Blue', 'sku' => 'EXISTING', 'stock' => 2, 'price_adjustment' => 5, 'image_path' => $product->image_path]);
        $product->media()->create(['kind' => 'image', 'mime_type' => 'image/png', 'size' => 100, 'path' => $product->image_path, 'sort_order' => 1]);
        $key = (string) Str::uuid();
        $this->asUser($seller)->post(route('seller.products.duplicate', $product), ['operation_key' => $key])->assertSessionHasNoErrors();
        $draft = ProductDraft::sole();
        $this->assertSame($product->name, $draft->payload['name']);
        $this->assertNull($draft->submitted_at);
        $this->assertNull($draft->payload['variations'][0]['sku']);
        $this->assertArrayNotHasKey('id', $draft->payload['variations'][0]);
        $this->assertCount(3, $draft->uploads);
        foreach ($draft->uploads as $upload) {
            Storage::disk('private')->assertExists($upload['path']);
        }
        $this->post(route('seller.products.duplicate', $product), ['operation_key' => $key])->assertRedirectToRoute('seller.product-drafts.edit', $draft);
        $this->assertDatabaseCount('product_drafts', 1);
        $this->assertCount(3, Storage::disk('private')->allFiles());
        $this->assertDatabaseCount('products', 1);
        $this->assertSame('EXISTING', $variant->fresh()->sku);
        $this->delete(route('seller.products.destroy', $product))->assertSessionHasNoErrors();
        foreach ($draft->uploads as $upload) {
            Storage::disk('private')->assertExists($upload['path']);
        }
        $this->get(route('seller.product-drafts.edit', $draft))->assertOk()->assertSee($product->name);
    }

    public function test_duplication_failure_cleans_private_files_and_foreign_requests_have_no_effect(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $seller = $this->user();
        $product = $this->product($seller, ['image_path' => 'products/good.png']);
        Storage::disk('public')->put($product->image_path, 'cover');
        $product->media()->create(['kind' => 'image', 'mime_type' => 'image/png', 'size' => 100, 'path' => 'products/missing.png', 'sort_order' => 1]);
        $this->asUser($seller)->post(route('seller.products.duplicate', $product), ['operation_key' => (string) Str::uuid()])->assertStatus(422);
        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->assertDatabaseCount('product_drafts', 0);
        $this->asUser($this->user())->post(route('seller.products.duplicate', $product), ['operation_key' => (string) Str::uuid()])->assertNotFound();
        $this->assertDatabaseCount('seller_catalog_operations', 0);
    }

    public function test_bulk_mixed_deletion_keeps_unfinished_orders_and_is_replay_safe(): void
    {
        $seller = $this->user();
        $pending = $this->product($seller);
        $finished = $this->product($seller);
        $unused = $this->product($seller);
        $this->order($seller, $pending, 'PREPARING');
        $this->order($seller, $finished, 'COMPLETED');
        $data = ['products' => [$pending->id, $finished->id, $unused->id], 'action' => 'deactivate', 'operation_key' => (string) Str::uuid()];
        $this->asUser($seller)->post(route('seller.products.bulk'), $data)->assertSessionHasNoErrors();
        $this->assertFalse((bool) $pending->fresh()->is_seller_active);
        $this->assertFalse((bool) $finished->fresh()->is_seller_active);
        $data = [...$data, 'action' => 'delete', 'operation_key' => (string) Str::uuid()];
        $this->post(route('seller.products.bulk'), $data)->assertSessionHasNoErrors()->assertSessionHas('catalog_results', fn ($rows) => $rows[0]['outcome'] === 'Skipped: unfinished orders');
        $this->assertFalse((bool) $pending->fresh()->is_archived);
        $this->assertTrue((bool) $finished->fresh()->is_archived);
        $this->assertNull($unused->fresh());
        $this->post(route('seller.products.bulk'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('order_items', 2);
        $this->post(route('seller.products.bulk'), [...$data, 'action' => 'deactivate'])->assertConflict();
    }

    public function test_bulk_rejects_foreign_or_invalid_selections_before_changing_own_products(): void
    {
        $seller = $this->user();
        $own = $this->product($seller);
        $foreign = $this->product($this->user());
        $data = ['products' => [$own->id, $foreign->id], 'action' => 'deactivate', 'operation_key' => (string) Str::uuid()];
        $this->asUser($seller)->post(route('seller.products.bulk'), $data)->assertNotFound();
        $this->assertTrue((bool) $own->fresh()->is_seller_active);
        $this->post(route('seller.products.bulk'), [...$data, 'products' => []])->assertSessionHasErrors('products');
        $this->asUser($this->user('buyer'))->post(route('seller.products.bulk'), $data)->assertForbidden();
    }

    public function test_seller_reply_is_unique_editable_escaped_and_never_changes_the_buyer_review(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $order = $this->order($seller, $product, 'COMPLETED');
        $review = Review::create(['order_id' => $order->id, 'product_id' => $product->id, 'buyer_id' => $order->buyer_id, 'rating' => 5, 'comment' => 'Original feedback']);
        $body = '<script>Seller response</script>';
        $this->asUser($seller)->post(route('seller.reviews.reply', $review), ['body' => $body])->assertSessionHasNoErrors();
        $this->post(route('seller.reviews.reply', $review), ['body' => $body])->assertSessionHasNoErrors();
        $this->post(route('seller.reviews.reply', $review), ['body' => 'Another reply'])->assertConflict();
        $this->get(route('product.show', $product))->assertOk()->assertSee($body)->assertDontSee($body, false);
        $this->patch(route('seller.reviews.reply.update', $review), ['body' => 'Updated response', 'version' => 1])->assertSessionHasNoErrors();
        $this->patch(route('seller.reviews.reply.update', $review), ['body' => 'Stale update', 'version' => 1])->assertConflict();
        $this->assertDatabaseCount('review_replies', 1);
        $this->assertSame('Updated response', $review->fresh()->reply->body);
        $this->assertSame('Original feedback', $review->fresh()->comment);
        $this->get(route('seller.reviews.index'))->assertOk()->assertSee('Edit seller response');
        $this->assertSame(2, DB::table('administrative_audits')->where('action', 'seller_review_reply_saved')->count());
        $this->asUser($this->user())->patch(route('seller.reviews.reply.update', $review), ['body' => 'Foreign', 'version' => 2])->assertForbidden();
        $this->asUser($seller)->post(route('seller.reviews.reply', $review), ['body' => ' '])->assertSessionHasErrors('body');
    }

    private function asUser(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    public function test_workspace_migration_can_be_reversed_and_reapplied_without_removing_existing_commerce_records(): void
    {
        $seller = $this->user();
        $product = $this->product($seller);
        $order = $this->order($seller, $product, 'COMPLETED');
        $migration = require database_path('migrations/2026_11_14_212513_create_seller_catalog_workspaces_and_review_replies.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('store_categories'));
        $this->assertFalse(Schema::hasTable('review_replies'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('seller_catalog_operations'));
        $this->assertTrue(Schema::hasTable('product_store_category'));
        $this->assertNotNull($product->fresh());
        $this->assertNotNull($order->fresh());
        $this->assertDatabaseCount('order_items', 1);
        $this->category($seller, 'After reapply');
    }

    private function user(string $role = 'seller'): User
    {
        return User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'first_name' => 'Workspace', 'last_name' => 'User', 'role' => $role, 'status' => 'approved', 'contact_no' => '09123456789']);
    }

    private function product(User $seller, array $data = []): Product
    {
        return Product::forceCreate(['user_id' => $seller->id, 'name' => 'Workspace product '.Str::random(4), 'category' => 'Home and Garden', 'description' => 'Store navigation fixture', 'price' => 100, 'stock' => 10, 'weight_grams' => 1000, 'compliance_status' => 'approved', 'is_archived' => false, ...$data]);
    }

    private function categoryData(string $name, ?int $parent = null): array
    {
        return ['name' => $name, 'parent_id' => $parent, 'position' => 1, 'is_visible' => 1];
    }

    private function category(User $seller, string $name, ?int $parent = null): StoreCategory
    {
        $this->asUser($seller)->post(route('seller.categories.store'), $this->categoryData($name, $parent))->assertSessionHasNoErrors();

        return StoreCategory::where('seller_id', $seller->id)->where('name', $name)->firstOrFail();
    }

    private function order(User $seller, Product $product, string $status): Order
    {
        $buyer = $this->user('buyer');
        $order = Order::forceCreate(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'order_number' => 'WS-'.Str::random(10), 'recipient_name' => 'Buyer User', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Demo Lane', 'status' => $status, 'payment_method' => 'COD', 'subtotal' => 100, 'total_amount' => 150, 'shipping_fee' => 50, 'commission_fee' => 10]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 100, 'quantity' => 1, 'item_total' => 100]);

        return $order;
    }
}
