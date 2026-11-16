<?php

namespace Tests\Feature;

use App\Models\DeliveryRateVersion;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutPricingService;
use App\Services\DeliveryPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DeliveryPricingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([null, false])]
    #[TestWith([0, false])]
    #[TestWith([null, true])]
    #[TestWith([0, true])]
    public function test_web_and_api_refuse_mixed_missing_weights_without_creating_an_order(?int $weight, bool $missingFirst): void
    {
        $this->withoutVite();
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $known = $this->product($seller, 500);
        $missing = $this->product($seller, $weight);
        $this->enableTariff();
        $items = array_map(fn ($p) => ['product_id' => $p->id, 'quantity' => 1], $missingFirst ? [$missing, $known] : [$known, $missing]);
        $message = 'Every selected product needs a positive unit weight before delivery can be quoted. Ask the seller to update the product weight.';

        $this->actingAs($buyer)->postJson('/api/cart/quote', ['items' => $items])
            ->assertUnprocessable()->assertJsonPath('success', false)->assertJsonPath('errors.shipping.0', $message)
            ->assertJsonMissingPath('data.checkout_token');
        $this->withSession(['checkout_context' => ['mode' => 'cart', 'items' => $items]])->get(route('checkout.index'))
            ->assertRedirectToRoute('cart.index')->assertSessionHas('error', $message);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertSame(20, $known->fresh()->stock);
        $this->assertSame(20, $missing->fresh()->stock);
    }

    public function test_valid_weights_and_quantities_are_all_included_in_the_quote(): void
    {
        $seller = $this->user('seller');
        $first = $this->product($seller, 500);
        $second = $this->product($seller, 250);
        $this->enableTariff();

        $quote = app(CheckoutPricingService::class)->quote([
            ['product_id' => $first->id, 'quantity' => 2], ['product_id' => $second->id, 'quantity' => 3],
        ]);

        $this->assertSame(1750, $quote['groups'][$seller->id]['shipping_snapshot']['actual_grams']);
        $this->assertSame(4000, $quote['shipping_cents']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_legacy_flat_fee_keeps_its_compatibility_for_unweighed_products(): void
    {
        $seller = $this->user('seller');
        $product = $this->product($seller, null);
        DB::table('commerce_settings')->updateOrInsert(['key' => 'shipping_model'], ['value' => 0]);

        $quote = app(DeliveryPricingService::class)->quote($seller, [['product' => $product, 'quantity' => 1]]);

        $this->assertSame('legacy', $quote['model']);
        $this->assertSame(0, $quote['actual_grams']);
        $this->assertSame('UNCONFIGURED', $quote['allocation_status']);
    }

    public function test_seller_missing_weight_filter_is_scoped_and_retains_products(): void
    {
        $this->withoutVite();
        $seller = $this->user('seller');
        $missing = $this->product($seller, null);
        $this->product($seller, 500);
        $this->product($this->user('seller'), null);
        $this->product($seller, null)->update(['is_archived' => true]);

        $this->actingAs($seller)->get(route('seller.products.index', ['needs_weight' => 1]))
            ->assertOk()->assertViewHas('missingWeights', 1)
            ->assertViewHas('products', fn ($products) => $products->total() === 1 && $products->first()->id === $missing->id)
            ->assertSee('Product weights need attention')->assertSee('Unit weight required');
        $this->assertDatabaseCount('products', 4);
    }

    private function enableTariff(): void
    {
        config(['financial.sandbox_enabled' => true]);
        DB::table('commerce_settings')->updateOrInsert(['key' => 'shipping_model'], ['value' => 1]);
        $maker = $this->user('logistics_admin');
        $approver = $this->user('logistics_admin');
        DeliveryRateVersion::create(['name' => 'Synthetic integrity tariff', 'route_class' => 'same_barangay', 'status' => 'APPROVED',
            'created_by' => $maker->id, 'approved_by' => $approver->id, 'approved_at' => now(), 'effective_at' => now()->subMinute(),
            'sandbox' => true, 'volumetric_divisor' => 6000,
            'rules' => ['weight_brackets' => [['max_grams' => 1000, 'components' => ['last_mile' => 2000]], ['max_grams' => 5000, 'components' => ['last_mile' => 4000]]]],
        ]);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['role' => $role, 'status' => 'approved', 'email' => Str::uuid().'@example.test',
            'first_name' => 'Pricing', 'last_name' => 'Fixture', 'password' => 'Password123!', 'contact_no' => '09123456789',
            'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Roque', 'email_verified_at' => now(),
        ]);
    }

    private function product(User $seller, ?int $grams): Product
    {
        return Product::forceCreate(['user_id' => $seller->id, 'name' => 'Synthetic product '.Str::random(8), 'category' => 'Home and Garden',
            'price' => 100, 'stock' => 20, 'compliance_status' => 'approved', 'weight_grams' => $grams, 'variations_enabled' => false,
        ]);
    }
}
