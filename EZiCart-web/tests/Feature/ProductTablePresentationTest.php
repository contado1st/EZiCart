<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductTablePresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_catalog_tables_show_cover_images_and_keep_products_without_photos_readable(): void
    {
        $seller = $this->user('seller');
        $coverPath = UploadedFile::fake()->image('table-cover.jpg')->storeAs('products', 'table-cover.jpg', 'public');
        $product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Photographed item', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 2, 'image_path' => $coverPath]);
        Product::forceCreate(['user_id' => $seller->id, 'name' => 'No photo item', 'category' => 'Home and Garden', 'price' => 50, 'stock' => 2]);
        $this->actingAs($seller)->get(route('seller.sales.index'))->assertOk()
            ->assertSee(asset('storage/'.$product->image_path), false)->assertSee('Photographed item')
            ->assertSee('No photo item')->assertSee('Image unavailable: No photo item')
            ->assertSee('loading="lazy"', false);
    }

    public function test_rider_and_hub_queues_use_purchase_images_and_batch_load_items(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $rider = $this->user('courier');
        $hub = $this->user('sorting_center');
        $currentImagePath = UploadedFile::fake()->image('replacement-cover.jpg')->storeAs('products', 'replacement-cover.jpg', 'public');
        $purchaseImagePath = UploadedFile::fake()->image('purchased-cover.jpg')->storeAs('products', 'purchased-cover.jpg', 'public');
        $purchaseImageUrl = asset('storage/'.$purchaseImagePath);
        $product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Updated catalog name', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 2, 'image_path' => $currentImagePath]);
        $order = Order::forceCreate(['order_number' => 'TABLE-'.Str::random(10), 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'pickup_courier_id' => $rider->id, 'sorting_center_id' => $hub->id, 'status' => 'READY_FOR_PICKUP', 'parcel_weight_grams' => 1000,
            'recipient_name' => 'Test buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Synthetic test address',
            'subtotal' => 100, 'shipping_fee' => 50, 'total_amount' => 150, 'payment_method' => 'COD']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Purchased name', 'quantity' => 1, 'unit_price' => 100, 'item_total' => 100, 'purchased_image_path' => $purchaseImagePath]);
        $response = $this->actingAs($rider)->get(route('courier.dashboard'))->assertOk()
            ->assertSee('Purchased name')->assertSee($purchaseImageUrl, false)
            ->assertDontSee(asset('storage/'.$currentImagePath), false);
        $this->assertTrue($response->viewData('availablePickups')->first()->relationLoaded('items'));
        $order->forceFill(['status' => 'PICKED_UP', 'picked_up_at' => now()])->save();
        $this->flushSession();
        Auth::forgetGuards();
        $response = $this->actingAs($hub)->get(route('logistics.intake'))->assertOk()
            ->assertSee($purchaseImageUrl, false)->assertSee('Purchased name');
        $this->assertTrue($response->viewData('parcels')->first()->relationLoaded('items'));
    }

    public function test_draft_thumbnail_uses_owner_authorized_media_instead_of_a_public_private_path(): void
    {
        Storage::fake('private');
        $seller = $this->user('seller');
        Storage::disk('private')->put('product-drafts/private-preview.jpg', 'synthetic image');
        $draft = ProductDraft::create(['user_id' => $seller->id, 'payload' => ['name' => 'Private draft'], 'uploads' => ['image' => ['path' => 'product-drafts/private-preview.jpg', 'name' => 'image.jpg']]]);
        $url = route('seller.product-drafts.media', [$draft, 'image']);
        $this->actingAs($seller)->get(route('seller.product-drafts.index'))->assertOk()->assertSee($url, false)
            ->assertDontSee('storage/product-drafts')->assertDontSee('private-preview.jpg');
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->flushSession();
        Auth::forgetGuards();
        $this->actingAs($this->user('seller'))->get($url)->assertNotFound();
    }

    public function test_buyer_and_seller_order_history_cards_preview_each_item_and_label_legacy_photos(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $currentPath = UploadedFile::fake()->image('current.jpg')->storeAs('products', 'current.jpg', 'public');
        $purchasePath = UploadedFile::fake()->image('purchased.jpg')->storeAs('products', 'purchased.jpg', 'public');
        $product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Current catalog name', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 5, 'image_path' => $currentPath]);
        $order = Order::forceCreate(['order_number' => 'HISTORY-'.Str::random(10), 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'status' => 'CANCELLED', 'recipient_name' => 'History buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Synthetic test address',
            'subtotal' => 300, 'shipping_fee' => 50, 'total_amount' => 350, 'payment_method' => 'COD']);
        foreach ([['Purchased item', $purchasePath, $product->id], ['Legacy item', null, $product->id], ['Retired item', null, null]] as [$name, $image, $productId]) {
            OrderItem::create(['order_id' => $order->id, 'product_id' => $productId, 'product_name' => $name, 'variation_info' => 'Blue / Large', 'quantity' => 1, 'unit_price' => 100, 'item_total' => 100, 'purchased_image_path' => $image]);
        }
        foreach ([[$buyer, 'buyer.dashboard'], [$seller, 'seller.orders.index']] as [$actor, $route]) {
            $this->flushSession();
            Auth::forgetGuards();
            $response = $this->actingAs($actor)->get(route($route))->assertOk()
                ->assertSee(asset('storage/'.$purchasePath), false)->assertSee(asset('storage/'.$currentPath), false)
                ->assertSee('Current catalog photo')->assertSee('Purchase image not recorded: Retired item')
                ->assertSee('Blue / Large')->assertSee('Qty 1');
            $dom = new \DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertCount(3, $xpath->query('//div[contains(@class,"order-item-row")]//span[contains(@class,"product-table-thumbnail")]'));
            $this->assertCount(2, $xpath->query('//div[contains(@class,"order-item-row")]//img[@data-product-table-image]'));
            $items = $response->viewData('orders')->first()->items;
            $this->assertTrue($items->every(fn (OrderItem $item) => $item->relationLoaded('product')));
        }
        $this->assertNull($order->items()->where('product_name', 'Legacy item')->first()->purchased_image_path);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['first_name' => 'Example', 'last_name' => ucfirst($role), 'role' => $role, 'status' => 'approved',
            'email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'contact_no' => '09'.fake()->unique()->numerify('#########')]);
    }
}
