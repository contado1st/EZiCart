<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_shows_real_shop_information_vouchers_and_available_recommendations(): void
    {
        $this->withoutVite();
        $seller = $this->user('seller');
        $product = $this->product($seller, 'Featured bag');
        $this->product($seller, 'Same shop bag');
        $this->product($seller, 'Unapproved bag', ['compliance_status' => 'pending_review']);
        $this->product($this->user('seller'), 'Suggested bag');
        $this->product($this->user('seller', ['status' => 'suspended']), 'Suspended seller bag');
        Voucher::create(['seller_id' => $seller->id, 'code' => 'BAG10', 'type' => 'fixed', 'value' => 10, 'min_spend' => 100, 'is_active' => true]);
        Voucher::create(['seller_id' => $seller->id, 'code' => 'EXPIRED10', 'type' => 'fixed', 'value' => 10, 'is_active' => true, 'expires_at' => now()->subDay()]);
        $this->get(route('product.show', $product))->assertOk()->assertSee('Product specifications')->assertSee('Product description')
            ->assertSee('Product ratings')->assertSee('Same shop bag')->assertSee('Suggested bag')->assertSee('BAG10')
            ->assertDontSee('EXPIRED10')->assertDontSee('Unapproved bag')->assertDontSee('Suspended seller bag')
            ->assertSee('Cash on delivery')->assertSee('No reviews yet.');
    }

    public function test_review_filters_and_pagination_preserve_the_overall_rating(): void
    {
        $this->withoutVite();
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $product = $this->product($seller, 'Reviewed bag');
        foreach ([5, 4, 5, 4, 5, 4] as $index => $rating) {
            $order = Order::forceCreate(['order_number' => 'PDP-'.$index, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'recipient_name' => 'Test Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Test Street', 'subtotal' => 200, 'total_amount' => 250, 'payment_method' => 'COD', 'status' => 'COMPLETED']);
            Review::create(['order_id' => $order->id, 'product_id' => $product->id, 'buyer_id' => $buyer->id, 'rating' => $rating, 'comment' => 'Review number '.$index]);
        }
        $this->get(route('product.show', $product))->assertOk()->assertViewHas('averageRating', 4.5)->assertViewHas('reviews', fn ($reviews) => $reviews->count() === 5 && $reviews->total() === 6);
        $this->get(route('product.show', [$product, 'rating' => 5]))->assertOk()->assertViewHas('reviewCount', 6)
            ->assertViewHas('averageRating', 4.5)->assertViewHas('reviews', fn ($reviews) => $reviews->total() === 3 && $reviews->every(fn ($review) => $review->rating === 5));
    }

    public function test_other_shop_recommendations_have_their_own_bottom_grid_and_more_products_link(): void
    {
        $this->withoutVite();
        $seller = $this->user('seller');
        $other = $this->user('seller', ['business_name' => 'Another Shop', 'municipality' => 'Liliw']);
        $product = $this->product($seller, 'Current shop product');
        $this->product($seller, 'Same shop only');
        foreach (range(1, 25) as $index) {
            $this->product($other, 'Other shop item '.$index);
        }
        $this->product($other, 'Archived recommendation', ['is_archived' => true]);
        $this->product($other, 'Sold-out recommendation', ['stock' => 0]);

        $response = $this->get(route('product.show', $product));
        $response->assertOk()->assertSee('id="you-may-also-like"', false)->assertSee('See more')
            ->assertSee('Another Shop')->assertSee('Liliw')
            ->assertDontSee('Archived recommendation')->assertDontSee('Sold-out recommendation')
            ->assertViewHas('suggestions', fn ($items) => $items->total() === 25 && $items->count() === 24 && $items->getCollection()->every(fn ($item) => $item->user_id === $other->id));
        $this->get(route('product.show', [$product, 'recommendations_page' => 2]))->assertOk()
            ->assertSee('Previous products')->assertDontSee('See more')
            ->assertViewHas('suggestions', fn ($items) => $items->count() === 1 && $items->currentPage() === 2);
    }

    public function test_other_shop_section_stays_visible_when_only_the_current_shop_has_products(): void
    {
        $this->withoutVite();
        $product = $this->product($this->user('seller'), 'Only shop product');
        $this->get(route('product.show', $product))->assertOk()->assertSee('You may also like')
            ->assertSee('Products from other shops will appear here')
            ->assertViewHas('suggestions', fn ($items) => $items->isEmpty());
    }

    public function test_buy_now_uses_existing_cart_and_checkout_while_add_to_cart_keeps_its_destination(): void
    {
        $product = $this->product($this->user('seller'), 'Buy now bag');
        $this->actingAs($this->user('buyer'))->post(route('cart.add', $product), ['quantity' => 1, 'intent' => 'buy_now'])
            ->assertRedirect(route('checkout.index'))->assertSessionHas('cart.'.$product->id.'.quantity', 1);
        $this->post(route('cart.add', $product), ['quantity' => 1])->assertRedirect(route('cart.index'))
            ->assertSessionHas('cart.'.$product->id.'.quantity', 2);
    }

    private function user(string $role, array $attributes = []): User
    {
        return User::forceCreate(array_merge(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'first_name' => 'Test', 'last_name' => 'Shopper', 'contact_no' => '09123456789', 'business_name' => 'Test Shop', 'role' => $role, 'status' => 'approved', 'municipality' => 'Majayjay', 'province' => 'Laguna'], $attributes));
    }

    private function product(User $seller, string $name, array $attributes = []): Product
    {
        return Product::forceCreate(array_merge(['user_id' => $seller->id, 'name' => $name, 'price' => 200, 'stock' => 10, 'category' => "Men's Apparel", 'compliance_status' => 'approved'], $attributes));
    }
}
