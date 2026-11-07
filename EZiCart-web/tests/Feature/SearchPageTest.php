<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    private ?User $seller = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_navbar_search_opens_a_dedicated_public_results_page(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('action="'.route('search.index').'"', false);
        $this->get(route('search.index'))->assertOk()->assertViewIs('search.index')->assertSee('Discover products')->assertSee('No matching products');
        $this->actingAs($this->user('buyer'))->get(route('search.index'))->assertOk()->assertViewIs('search.index');
    }

    public function test_search_matches_product_name_description_category_and_shop_without_case_sensitivity(): void
    {
        $match = $this->product(['name' => 'Canvas Tote', 'description' => 'Everyday carry', 'category' => 'Fashion']);
        $this->product(['name' => 'Cookbook', 'description' => 'Recipes', 'category' => 'Books', 'user_id' => $this->user('seller', 'Blue Books')->id]);
        foreach (['CANVAS', 'everyday', 'FASHION', 'rose studio'] as $term) {
            $this->get(route('search.index', ['search' => $term]))->assertOk()->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$match->id]);
        }
    }

    public function test_search_keeps_unavailable_unapproved_archived_and_unsellable_variants_hidden(): void
    {
        $visible = $this->product();
        $this->product(['compliance_status' => 'pending_review', 'category' => 'Hidden category']);
        $this->product(['is_archived' => true]);
        $this->product(['stock' => 0]);
        $inactive = $this->product();
        $inactive->variations()->forceCreate(['type' => 'Size', 'value' => 'M', 'stock' => 5, 'is_active' => false]);
        $soldOut = $this->product();
        $soldOut->variations()->forceCreate(['type' => 'Size', 'value' => 'L', 'stock' => 0, 'is_active' => true]);
        $variant = $this->product();
        $variant->variations()->forceCreate(['type' => 'Size', 'value' => 'S', 'stock' => 2, 'is_active' => true]);
        $this->get(route('search.index'))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->sort()->values()->all() === [$visible->id, $variant->id])
            ->assertViewHas('categories', fn ($categories) => ! $categories->contains('Hidden category'));
    }

    public function test_category_price_and_seller_location_filters_work_together_and_zero_price_is_supported(): void
    {
        $match = $this->product(['price' => 150]);
        $this->product(['price' => 90]);
        $this->product(['price' => 180, 'category' => 'Books']);
        $otherSeller = $this->user('seller', 'Cavite Store');
        $otherSeller->update(['province' => 'Cavite']);
        $this->product(['price' => 160, 'user_id' => $otherSeller->id]);
        $this->get(route('search.index', ['category' => 'Fashion', 'min_price' => 100, 'max_price' => 200, 'province' => 'Laguna']))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$match->id]);
        $free = $this->product(['price' => 0]);
        $this->get(route('search.index', ['max_price' => 0]))->assertOk()->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$free->id]);
    }

    public function test_price_and_relevance_sorting_use_the_requested_order(): void
    {
        $cheap = $this->product(['name' => 'Bag', 'price' => 100]);
        $expensive = $this->product(['name' => 'Bag organizer', 'price' => 900]);
        $this->get(route('search.index', ['sort' => 'price_asc']))->assertOk()->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$cheap->id, $expensive->id]);
        $this->get(route('search.index', ['sort' => 'price_desc']))->assertOk()->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$expensive->id, $cheap->id]);
        $this->get(route('search.index', ['search' => 'Bag', 'sort' => 'relevance']))->assertOk()->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$cheap->id, $expensive->id]);
        $this->get(route('search.index', ['sort' => 'latest']))->assertOk()->assertViewHas('products', fn ($products) => $products->first()->id === $expensive->id);
    }

    public function test_rating_filter_and_sort_use_real_review_averages(): void
    {
        $low = $this->product();
        $high = $this->product();
        $this->review($low, 3);
        $this->review($high, 5);
        $this->product(['name' => 'Not reviewed']);
        $this->get(route('search.index', ['rating' => 4]))->assertOk()->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$high->id]);
        $this->get(route('search.index', ['sort' => 'rating']))->assertOk()->assertViewHas('products', fn ($products) => $products->first()->id === $high->id);
    }

    public function test_search_treats_sql_wildcards_as_literal_characters_and_escapes_html(): void
    {
        $literal = $this->product(['name' => '100% Canvas_Studio']);
        $this->product(['name' => '100x Canvas Studio']);
        foreach (['100%', '_'] as $term) {
            $this->get(route('search.index', ['search' => $term]))->assertOk()->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$literal->id]);
        }
        $script = '<script>alert(1)</script>';
        $this->get(route('search.index', ['search' => $script]))->assertOk()->assertSee(e($script), false)->assertDontSee($script, false);
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        foreach ([
            [['search' => ['array']], 'search'],
            [['sort' => 'price; DROP TABLE products'], 'sort'],
            [['min_price' => -1], 'min_price'],
            [['min_price' => 200, 'max_price' => 100], 'max_price'],
            [['rating' => 6], 'rating'],
        ] as [$query, $field]) {
            $this->getJson(route('search.index', $query))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_pagination_preserves_search_and_filters(): void
    {
        for ($index = 0; $index < 25; $index++) {
            $this->product(['name' => 'Canvas bag '.$index]);
        }
        $this->get(route('search.index', ['search' => 'Canvas', 'category' => 'Fashion', 'sort' => 'price_asc', 'page' => 2]))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 25 && $products->count() === 1 && str_contains($products->url(1), 'category=Fashion') && str_contains($products->url(1), 'search=Canvas') && str_contains($products->url(1), 'sort=price_asc'));
    }

    private function user(string $role, string $businessName = 'Rose Studio'): User
    {
        return User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => $role, 'status' => 'approved', 'first_name' => 'Test', 'last_name' => 'User', 'business_name' => $businessName, 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay']);
    }

    private function product(array $attributes = []): Product
    {
        $this->seller ??= $this->user('seller');

        return Product::forceCreate(array_replace(['user_id' => $this->seller->id, 'name' => 'Canvas bag', 'description' => 'Daily essentials', 'category' => 'Fashion', 'price' => 100, 'stock' => 10, 'is_archived' => false, 'compliance_status' => 'approved'], $attributes));
    }

    private function review(Product $product, int $rating): void
    {
        $buyer = $this->user('buyer');
        $order = Order::forceCreate(['order_number' => 'SEARCH-'.Str::uuid(), 'buyer_id' => $buyer->id, 'seller_id' => $product->user_id, 'recipient_name' => 'Test Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Test Street', 'subtotal' => 100, 'total_amount' => 150, 'payment_method' => 'COD', 'status' => 'COMPLETED']);
        Review::create(['order_id' => $order->id, 'buyer_id' => $buyer->id, 'product_id' => $product->id, 'rating' => $rating, 'comment' => 'Test feedback']);
    }
}
