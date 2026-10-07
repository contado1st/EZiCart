<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;

    protected User $admin;

    protected User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seller = $this->createUser([
            'email' => 'test.seller@ezicart.com',
            'role' => 'seller',
            'status' => 'approved',
            'business_name' => 'Test Store',
            'line_of_business' => 'Electronics',
        ]);

        $this->admin = $this->createUser([
            'email' => 'test.admin@ezicart.com',
            'role' => 'admin',
            'status' => 'approved',
        ]);

        $this->buyer = $this->createUser([
            'email' => 'test.buyer@ezicart.com',
            'role' => 'buyer',
            'status' => 'approved',
        ]);
    }

    protected function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'buyer',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09123456789',
            'birthday' => '2000-01-01',
            'age' => 26,
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
            'street_address' => '123 Rizal St',
        ], $overrides));
    }

    public function test_seller_can_create_product_with_discount_voucher_and_variations(): void
    {
        $image1 = UploadedFile::fake()->create('photo1.jpg', 100, 'image/jpeg');
        $image2 = UploadedFile::fake()->create('photo2.jpg', 100, 'image/jpeg');
        $varImg = UploadedFile::fake()->create('red_var.jpg', 50, 'image/jpeg');

        $response = $this->actingAs($this->seller)->post(route('seller.products.store'), [
            'name' => 'Mechanical Keyboard Pro',
            'category' => 'Electronics',
            'description' => 'RGB backlit mechanical keyboard.',
            'price' => 1000.00,
            'stock' => 50,
            'images' => [$image1, $image2],
            'primary_image_index' => 0,
            'enable_discount' => 1,
            'discount_type' => 'percent',
            'discount_value' => 20.00,
            'enable_voucher' => 1,
            'voucher_code' => 'KEYBOARD20',
            'voucher_type' => 'fixed',
            'voucher_value' => 50.00,
            'voucher_min_spend' => 500.00,
            'voucher_max_discount' => 50.00,
            'voucher_start_date' => now()->subDay()->format('Y-m-d'),
            'voucher_expires_at' => now()->addDays(7)->format('Y-m-d'),
            'voucher_is_active' => 1,
            'variations' => [
                [
                    'type' => 'Color',
                    'value' => 'Red Switch',
                    'price_adjustment' => 50.00,
                    'stock' => 20,
                    'sku' => 'KB-RED',
                    'image' => $varImg,
                ],
                [
                    'type' => 'Color',
                    'value' => 'Blue Switch',
                    'price_adjustment' => 0.00,
                    'stock' => 30,
                    'sku' => 'KB-BLUE',
                ],
            ],
        ]);

        $response->assertRedirect(route('seller.products.index'));

        $product = Product::where('name', 'Mechanical Keyboard Pro')->first();
        $this->assertNotNull($product);
        $this->assertSame('pending', $product->status); // Must be pending!
        $this->assertTrue($product->has_discount);
        $this->assertEquals(800.00, $product->discounted_price); // 20% off 1000 = 800
        $this->assertEquals(2, $product->images()->count());
        $this->assertEquals(2, $product->variations()->count());

        $voucher = Voucher::where('code', 'KEYBOARD20')->first();
        $this->assertNotNull($voucher);
        $this->assertEquals($product->id, $voucher->product_id);

        $variationWithImg = $product->variations()->where('value', 'Red Switch')->first();
        $this->assertNotNull($variationWithImg->image_path);
    }

    public function test_admin_can_view_pending_products_and_approve_them(): void
    {
        $product = Product::create([
            'user_id' => $this->seller->id,
            'name' => 'Pending Widget',
            'category' => 'Electronics',
            'price' => 500.00,
            'stock' => 10,
            'status' => 'pending',
            'is_archived' => false,
        ]);

        // Admin views pending list
        $viewResponse = $this->actingAs($this->admin)->get(route('admin.products.index', ['status' => 'pending']));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Pending Widget');

        // Admin inspects product
        $showResponse = $this->actingAs($this->admin)->get(route('admin.products.show', $product->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Pending Widget');

        // Admin approves product
        $approveResponse = $this->actingAs($this->admin)->post(route('admin.products.approve', $product->id));
        $approveResponse->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertSame('approved', $product->status);
    }

    public function test_admin_can_reject_product_with_reason(): void
    {
        $product = Product::create([
            'user_id' => $this->seller->id,
            'name' => 'Questionable Gadget',
            'category' => 'Electronics',
            'price' => 150.00,
            'stock' => 5,
            'status' => 'pending',
            'is_archived' => false,
        ]);

        $rejectResponse = $this->actingAs($this->admin)->post(route('admin.products.reject', $product->id), [
            'rejection_reason' => 'Product images do not meet quality standards and description is missing details.',
        ]);

        $rejectResponse->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertSame('rejected', $product->status);
        $this->assertStringContainsString('quality standards', $product->rejection_reason);

        // Seller sees rejection reason in inventory list
        $sellerIndexResponse = $this->actingAs($this->seller)->get(route('seller.products.index'));
        $sellerIndexResponse->assertStatus(200);
        $sellerIndexResponse->assertSee('Rejected');
        $sellerIndexResponse->assertSee('quality standards');
    }

    public function test_editing_approved_product_resets_status_to_pending(): void
    {
        $product = Product::create([
            'user_id' => $this->seller->id,
            'name' => 'Approved Mouse',
            'category' => 'Electronics',
            'price' => 800.00,
            'stock' => 20,
            'status' => 'approved',
            'is_archived' => false,
        ]);

        $updateResponse = $this->actingAs($this->seller)->put(route('seller.products.update', $product->id), [
            'name' => 'Approved Mouse Ultra V2',
            'category' => 'Electronics',
            'price' => 850.00,
            'stock' => 25,
            'description' => 'Updated sensor specifications.',
        ]);

        $updateResponse->assertRedirect(route('seller.products.index'));

        $product->refresh();
        $this->assertSame('pending', $product->status); // Reverted to pending!
    }

    public function test_seller_can_view_edit_page_with_variations(): void
    {
        $product = Product::create([
            'user_id' => $this->seller->id,
            'name' => 'Test Keyboard',
            'category' => 'Electronics',
            'price' => 500.00,
            'stock' => 10,
            'status' => 'approved',
            'is_archived' => false,
        ]);

        $product->variations()->create([
            'type' => 'Color',
            'value' => 'Red',
            'price_adjustment' => 50.00,
            'stock' => 5,
        ]);

        $response = $this->actingAs($this->seller)->get(route('seller.products.edit', $product->id));
        $response->assertStatus(200);
        $response->assertSee('Edit Product');
        $response->assertSee('Test Keyboard');
    }

    public function test_buyer_cannot_view_or_cart_pending_or_rejected_products(): void
    {
        $pendingProduct = Product::create([
            'user_id' => $this->seller->id,
            'name' => 'Pending Laptop',
            'category' => 'Electronics',
            'price' => 35000.00,
            'stock' => 5,
            'status' => 'pending',
            'is_archived' => false,
        ]);

        // Buyer cannot view pending product (404)
        $buyerShowResponse = $this->actingAs($this->buyer)->get(route('product.show', $pendingProduct->id));
        $buyerShowResponse->assertStatus(404);

        // Buyer cannot add pending product to cart
        $cartResponse = $this->actingAs($this->buyer)->post(route('cart.add', $pendingProduct->id), [
            'quantity' => 1,
        ]);
        $this->assertEmpty(session()->get('cart', []));

        // Marketplace doesn't display pending product
        $homeResponse = $this->get(route('home'));
        $homeResponse->assertStatus(200);
        $homeResponse->assertDontSee('Pending Laptop');
    }

    public function test_buyer_can_view_and_cart_approved_product_with_discount_and_variation(): void
    {
        $approvedProduct = Product::create([
            'user_id' => $this->seller->id,
            'name' => 'Wireless Headphones',
            'category' => 'Electronics',
            'price' => 2000.00,
            'stock' => 15,
            'discount_type' => 'percent',
            'discount_value' => 25.00, // 25% off 2000 = 1500
            'status' => 'approved',
            'is_archived' => false,
        ]);

        $variation = $approvedProduct->variations()->create([
            'type' => 'Color',
            'value' => 'Matte Black',
            'price' => 1000.00,
            'stock' => 10,
        ]);

        // Buyer views approved product
        $showResponse = $this->actingAs($this->buyer)->get(route('product.show', $approvedProduct->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Wireless Headphones');
        $showResponse->assertSee('Matte Black');
        $showResponse->assertSee('25% OFF');

        // Buyer adds to cart with variation
        $cartResponse = $this->actingAs($this->buyer)->post(route('cart.add', $approvedProduct->id), [
            'quantity' => 1,
            'variation_id' => $variation->id,
        ]);

        $cartResponse->assertRedirect(route('cart.index'));
        $cart = session()->get('cart', []);
        $this->assertNotEmpty($cart);

        // Price in cart should be variation price (1000) minus 25% discount = 750.00
        $cartItem = reset($cart);
        $this->assertEquals(750.00, $cartItem['price']);
    }
}
