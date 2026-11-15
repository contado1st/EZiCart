<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderCancellationRequest;
use App\Models\OrderFinance;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RefundExecution;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PartialBuyerDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_each_order_is_in_one_primary_group_with_matching_counts_including_legacy_history(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        foreach ([...array_column(OrderStatus::cases(), 'value'), 'RETURNED', 'LEGACY_UNKNOWN'] as $status) {
            $this->order($seller, $buyer, $status);
        }
        $other = $this->order($seller, $this->user('buyer'), 'COMPLETED');
        $response = $this->actingAs($buyer)->get(route('buyer.dashboard'))->assertOk()->assertDontSee($other->order_number);
        $counts = $response->viewData('counts');
        $this->assertSame(['all' => 17, 'to_pay' => 0, 'to_ship' => 3, 'to_receive' => 8, 'completed' => 1, 'cancelled' => 1, 'returns' => 3, 'other' => 1], $counts);
        $seen = [];
        foreach (['to_pay', 'to_ship', 'to_receive', 'completed', 'cancelled', 'returns', 'other'] as $group) {
            $orders = $this->get(route('buyer.dashboard', ['order_group' => $group]))->assertOk()->viewData('orders');
            $this->assertSame($counts[$group], $orders->total());
            foreach ($orders as $order) {
                $this->assertNotContains($order->id, $seen);
                $seen[] = $order->id;
            }
        }
        $this->assertCount(17, $seen);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_open_returns_and_refund_activity_take_precedence_but_pending_cancellation_stays_in_preparation(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $pending = $this->order($seller, $buyer, 'PLACED');
        OrderCancellationRequest::create(['order_id' => $pending->id, 'active_order_id' => $pending->id, 'requested_by' => $buyer->id, 'status' => 'PENDING', 'reason' => 'Please cancel this order', 'respond_by' => now()->addDay()]);
        $openDispute = $this->order($seller, $buyer, 'DELIVERED');
        $this->dispute($openDispute);
        $refunded = $this->order($seller, $buyer, 'COMPLETED');
        $this->refund($refunded);
        $cancelled = $this->order($seller, $buyer, 'CANCELLED');
        $this->dispute($cancelled);
        $this->actingAs($buyer)->get(route('buyer.dashboard', ['order_group' => 'returns']))->assertOk()
            ->assertSee($openDispute->order_number)->assertSee($refunded->order_number)->assertDontSee($cancelled->order_number)->assertDontSee($pending->order_number);
        $this->get(route('buyer.dashboard', ['order_group' => 'to_ship']))->assertOk()->assertSee($pending->order_number);
        $this->get(route('buyer.dashboard', ['order_group' => 'cancelled']))->assertOk()->assertSee($cancelled->order_number);
    }

    public function test_buyer_group_status_filter_and_pagination_retain_the_same_cohort(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        for ($i = 0; $i < 26; $i++) {
            $this->order($seller, $buyer, 'CANCELLED');
        }
        $this->order($seller, $buyer, 'COMPLETED');
        $response = $this->actingAs($buyer)->get(route('buyer.dashboard', ['order_group' => 'cancelled', 'status' => 'CANCELLED', 'page' => 2]))->assertOk();
        $this->assertSame(26, $response->viewData('counts')['all']);
        $this->assertSame(0, $response->viewData('counts')['completed']);
        $this->assertSame(26, $response->viewData('orders')->total());
        $this->assertCount(1, $response->viewData('orders'));
        $response->assertSee('status=CANCELLED', false)->assertSee('name="order_group" value="cancelled"', false);
        $this->getJson(route('buyer.dashboard', ['order_group' => 'unsupported_group']))->assertJsonValidationErrors('order_group');
        $this->getJson(route('buyer.dashboard', ['status' => 'UNSAFE_STATUS']))->assertJsonValidationErrors('status');
    }

    public function test_typo_suggestions_keep_literal_no_results_and_respect_visibility_and_filters(): void
    {
        $seller = $this->user('seller');
        $product = $this->product($seller, 'Rattan basket');
        $this->product($seller, 'Ratan basket', ['compliance_status' => 'pending']);
        $this->product($seller, 'Ratan baskit private', ['is_seller_active' => false]);
        $this->product($this->user('seller', ['status' => 'pending']), 'Ratan baskit');
        $response = $this->get(route('search.index', ['search' => 'Ratan baskit']))->assertOk()->assertSee('Did you mean:')->assertSee('No matching products');
        $this->assertSame(['Rattan basket'], $response->viewData('searchSuggestions'));
        $this->assertSame(0, $response->viewData('products')->total());
        $this->get(route('search.index', ['search' => 'zzqqlmptxyz']))->assertOk()->assertViewHas('searchSuggestions', []);
        $this->get(route('search.index', ['search' => 'Ratan baskit', 'min_price' => 999]))->assertOk()->assertViewHas('searchSuggestions', []);
        $this->get(route('search.index', ['search' => $product->name]))->assertOk()->assertViewHas('searchSuggestions', [])->assertSee($product->name);
    }

    public function test_best_selling_uses_completed_non_void_non_refunded_units_and_keeps_shop_scope(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $winner = $this->product($seller, 'Winning product');
        $other = $this->product($seller, 'Other product');
        $this->units($winner, $buyer, 'COMPLETED', 4);
        $this->units($other, $buyer, 'COMPLETED', 3);
        $this->units($other, $buyer, 'CANCELLED', 100);
        $this->units($other, $buyer, 'DELIVERED', 100);
        $refunded = $this->units($other, $buyer, 'COMPLETED', 100);
        $this->refund($refunded);
        $void = $this->units($other, $buyer, 'COMPLETED', 100);
        OrderFinance::create(['order_id' => $void->id, 'due_cents' => 10000, 'commission_cents' => 1000, 'seller_payable_cents' => 9000, 'remittance_status' => 'VOID']);
        $foreign = $this->product($this->user('seller'), 'Another shop');
        $this->units($foreign, $buyer, 'COMPLETED', 5);
        $response = $this->get(route('search.index', ['sort' => 'best_selling']))->assertOk();
        $this->assertSame([$foreign->id, $winner->id, $other->id], $response->viewData('products')->pluck('id')->all());
        $this->assertEquals([5, 4, 3], $response->viewData('products')->pluck('completed_sales_units')->all());
        $shop = $this->get(route('shops.show', [$seller, 'sort' => 'best_selling']))->assertOk();
        $this->assertSame([$winner->id, $other->id], $shop->viewData('products')->pluck('id')->all());
    }

    public function test_equal_sales_ranking_has_a_deterministic_page_boundary_and_preserves_filters(): void
    {
        $seller = $this->user('seller');
        $ids = [];
        for ($i = 0; $i < 25; $i++) {
            $ids[] = $this->product($seller, 'Ranking '.$i)->id;
        }
        $first = $this->get(route('search.index', ['sort' => 'best_selling']))->assertOk();
        $second = $this->get(route('search.index', ['sort' => 'best_selling', 'page' => 2]))->assertOk();
        $this->assertSame(array_reverse(array_slice($ids, 1)), $first->viewData('products')->pluck('id')->all());
        $this->assertSame([$ids[0]], $second->viewData('products')->pluck('id')->all());
        $second->assertSee('sort=best_selling', false);
    }

    public function test_suspended_seller_products_disappear_from_search_and_direct_pages(): void
    {
        $seller = $this->user('seller');
        $product = $this->product($seller, 'Account state product');
        $this->get(route('product.show', $product))->assertOk();
        $seller->forceFill(['status' => 'suspended'])->save();
        $this->get(route('search.index', ['search' => 'Account state product']))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 0)->assertViewHas('searchSuggestions', []);
        $this->get(route('product.show', $product))->assertNotFound();
    }

    public function test_suggested_titles_are_escaped_and_links_do_not_execute_product_markup(): void
    {
        $this->product($this->user('seller'), '<img src=x>');
        $this->get(route('search.index', ['search' => 'img srx x']))->assertOk()
            ->assertViewHas('searchSuggestions', ['<img src=x>'])->assertSee('<img src=x>')->assertDontSee('<img src=x>', false);
    }

    public function test_database_rejects_duplicate_wishlist_identity_even_outside_the_service(): void
    {
        $product = $this->product($this->user('seller'), 'Unique saved product');
        $buyer = $this->user('buyer');
        $buyer->wishlistedProducts()->attach($product->id);
        $this->expectException(UniqueConstraintViolationException::class);
        $buyer->wishlistedProducts()->attach($product->id);
    }

    public function test_guest_wishlist_and_follow_return_to_context_after_login_without_implicit_writes(): void
    {
        $seller = $this->user('seller');
        $product = $this->product($seller, 'Login return product');
        $buyer = $this->user('buyer');
        $this->get(route('product.show', $product))->assertOk()->assertSee(route('buyer.wishlist.product', $product), false);
        $this->get(route('buyer.wishlist.product', $product))->assertRedirectToRoute('login')
            ->assertSessionHas('url.intended', route('buyer.wishlist.product', $product));
        $this->post(route('login.post'), ['email' => $buyer->email, 'password' => 'Password123!'])->assertRedirectToRoute('buyer.wishlist.product', $product);
        $this->get(route('buyer.wishlist.product', $product))->assertRedirectToRoute('product.show', $product);
        $this->assertDatabaseCount('product_wishlists', 0);
        $this->assertDatabaseCount('shop_follows', 0);
        $this->post(route('logout'))->assertRedirect();
        $this->get(route('shops.follow-intent', $seller))->assertRedirectToRoute('login');
        $this->post(route('login.post'), ['email' => $buyer->email, 'password' => 'Password123!'])->assertRedirectToRoute('shops.follow-intent', $seller);
        $this->get(route('shops.follow-intent', $seller))->assertRedirectToRoute('shops.show', $seller);
        $this->assertDatabaseCount('shop_follows', 0);
    }

    public function test_wishlist_survives_logout_is_idempotent_and_isolated_from_another_buyer_and_cart(): void
    {
        $product = $this->product($this->user('seller'), 'Saved product');
        $buyer = $this->user('buyer');
        $other = $this->user('buyer');
        $this->actingAs($buyer)->post(route('buyer.wishlist.store', $product), ['saved' => true])->assertRedirect();
        $this->post(route('buyer.wishlist.store', $product), ['saved' => true])->assertRedirect();
        $this->assertDatabaseCount('product_wishlists', 1);
        $this->post(route('logout'))->assertRedirect();
        $this->post(route('login.post'), ['email' => $buyer->email, 'password' => 'Password123!'])->assertRedirect();
        $this->get(route('buyer.wishlist.index'))->assertOk()->assertSee($product->name)->assertHeader('Cache-Control', 'no-store, private');
        $this->flushSession();
        $this->actingAs($other)->get(route('buyer.wishlist.index'))->assertOk()->assertDontSee($product->name);
        $this->delete(route('buyer.wishlist.destroy', $product))->assertRedirect();
        $this->assertDatabaseHas('product_wishlists', ['buyer_id' => $buyer->id, 'product_id' => $product->id]);
        $this->assertDatabaseCount('buyer_carts', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_hidden_saved_product_remains_removable_without_disclosing_its_new_private_details(): void
    {
        $product = $this->product($this->user('seller'), 'Originally public');
        $buyer = $this->user('buyer');
        $this->actingAs($buyer)->post(route('buyer.wishlist.store', $product), ['saved' => true])->assertRedirect();
        $product->forceFill(['name' => 'Private changed title', 'compliance_status' => 'pending'])->save();
        $this->get(route('buyer.wishlist.index'))->assertOk()->assertSee('Not available for purchase')->assertDontSee('Private changed title');
        $this->post(route('buyer.wishlist.store', $product), ['saved' => true])->assertNotFound();
        $this->delete(route('buyer.wishlist.destroy', $product))->assertRedirect();
        $this->assertDatabaseCount('product_wishlists', 0);
    }

    #[TestWith(['seller'])]
    #[TestWith(['courier'])]
    #[TestWith(['ecommerce_admin'])]
    public function test_non_buyers_cannot_read_or_write_wishlists(string $role): void
    {
        $product = $this->product($this->user('seller'), 'Private wishlist item');
        $this->actingAs($this->user($role))->get(route('buyer.wishlist.index'))->assertForbidden();
        $this->post(route('buyer.wishlist.store', $product), ['saved' => true])->assertForbidden();
        $this->assertDatabaseCount('product_wishlists', 0);
    }

    public function test_wishlist_guards_authentication_validation_and_paused_sellers_and_paginates(): void
    {
        $seller = $this->user('seller');
        $product = $this->product($seller, 'Wishlist validation');
        $this->get(route('buyer.wishlist.index'))->assertRedirectToRoute('login');
        $this->post(route('buyer.wishlist.store', $product), ['saved' => true])->assertRedirectToRoute('login');
        $buyer = $this->user('buyer');
        $this->actingAs($buyer)->postJson(route('buyer.wishlist.store', $product), ['saved' => 'invalid'])->assertJsonValidationErrors('saved');
        $seller->forceFill(['status' => 'suspended'])->save();
        $this->post(route('buyer.wishlist.store', $product), ['saved' => true])->assertNotFound();
        $seller->forceFill(['status' => 'approved'])->save();
        $ids = [$product->id];
        for ($i = 0; $i < 25; $i++) {
            $ids[] = $this->product($seller, 'Wishlist '.$i)->id;
        }
        $buyer->wishlistedProducts()->attach($ids);
        $response = $this->get(route('buyer.wishlist.index', ['page' => 2]))->assertOk();
        $this->assertSame(26, $response->viewData('products')->total());
        $this->assertCount(1, $response->viewData('cards'));
    }

    private function user(string $role, array $data = []): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.com', 'password' => 'Password123!', 'role' => $role, 'status' => 'approved',
            'email_verified_at' => now(), 'first_name' => 'Discovery', 'last_name' => 'Tester', 'contact_no' => '09123456789', ...$data]);
    }

    private function product(User $seller, string $name, array $data = []): Product
    {
        return Product::forceCreate(['user_id' => $seller->id, 'name' => $name, 'category' => 'Home and Garden', 'price' => 100, 'stock' => 10,
            'weight_grams' => 1000, 'compliance_status' => 'approved', 'is_archived' => false, ...$data]);
    }

    private function order(User $seller, User $buyer, string $status): Order
    {
        return Order::forceCreate(['order_number' => 'DISC-'.Str::random(12), 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'recipient_name' => 'Discovery Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay',
            'barangay' => 'Poblacion', 'street_address' => 'Synthetic test address', 'subtotal' => 100, 'shipping_fee' => 50, 'total_amount' => 150,
            'payment_method' => 'COD', 'status' => $status]);
    }

    private function units(Product $product, User $buyer, string $status, int $quantity): Order
    {
        $order = $this->order($product->seller, $buyer, $status);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 100, 'quantity' => $quantity, 'item_total' => $quantity * 100]);

        return $order;
    }

    private function dispute(Order $order): Dispute
    {
        return Dispute::create(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id, 'reason' => 'Test return', 'description' => 'Synthetic test dispute', 'status' => 'PENDING']);
    }

    private function refund(Order $order): void
    {
        $dispute = $this->dispute($order);
        RefundExecution::create(['order_id' => $order->id, 'dispute_id' => $dispute->id, 'amount_cents' => 100, 'status' => 'EXECUTED', 'authorized_by' => $this->user('ecommerce_admin')->id]);
    }
}
