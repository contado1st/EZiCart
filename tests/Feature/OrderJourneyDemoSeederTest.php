<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OrderJourneyDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class OrderJourneyDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_is_ready_for_checkout_and_reseeding_preserves_stock_and_existing_data(): void
    {
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $buyer = User::where('email', 'buyer@ezicart.com')->firstOrFail();
        $passwordHash = $buyer->password;
        $existing = $buyer->addresses()->create([
            'label' => 'Existing home', 'recipient_name' => 'John Buyer', 'recipient_contact' => '09123456781',
            'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Roque',
            'street_address' => 'Existing Street', 'is_default' => true,
        ]);

        $this->seed(OrderJourneyDemoSeeder::class);

        $product = Product::where('name', 'EZiCart Demo Everyday Tote Bag')->firstOrFail();
        $hub = User::where('email', 'sorting@ezicart.com')->firstOrFail();
        $rider = User::where('email', 'courier@ezicart.com')->firstOrFail();
        $area = Area::where('code', 'EZ-DEMO-MAJ')->firstOrFail();
        $address = $buyer->addresses()->where('label', 'Demo delivery — Majayjay')->firstOrFail();
        $this->assertSame('approved', $product->compliance_status);
        $this->assertSame($hub->id, $area->sorting_center_id);
        foreach ([$buyer, $hub, $rider, User::where('email', 'seller@ezicart.com')->firstOrFail()] as $account) {
            $this->assertSame(['Laguna', 'Majayjay', 'San Roque', '45', 'Market Road', '4005'],
                array_values($account->fresh()->only(['province', 'municipality', 'barangay', 'house_number', 'street_address', 'postal_code'])));
            $this->assertNotNull($account->fresh()->email_verified_at);
        }
        $this->assertSame($hub->id, $rider->fresh()->logistics_owner_id);
        $this->assertTrue($rider->serviceAreas()->whereKey($area->id)->exists());
        $this->assertNotEmpty($rider->badge_code);
        $this->assertDatabaseHas('storage_locations', ['hub_id' => $hub->id, 'code' => 'DEMO-STAGE-01', 'area_id' => $area->id]);
        $this->assertDatabaseCount('orders', 0);
        $this->actingAs($buyer)->get(route('product.show', $product))->assertOk()->assertSee($product->name);
        $this->post(route('cart.add', $product), ['quantity' => 1])->assertRedirectToRoute('cart.index');
        $this->get(route('checkout.index', ['address_id' => $address->id]))->assertOk()
            ->assertViewHas('selectedAddress', fn ($selected) => $selected->id === $address->id)
            ->assertViewHas('total', 150);

        $product->update(['stock' => 2]);
        $rider->serviceAreas()->updateExistingPivot($area->id, ['is_active' => false]);
        $this->seed(OrderJourneyDemoSeeder::class);

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame($passwordHash, $buyer->fresh()->password);
        $this->assertTrue($existing->fresh()->is_default);
        $this->assertTrue((bool) $rider->serviceAreas()->whereKey($area->id)->firstOrFail()->pivot->is_active);
        $this->assertSame(1, Product::where('name', $product->name)->count());
        $this->assertSame(1, DB::table('storage_locations')->where('code', 'DEMO-STAGE-01')->count());
        $this->assertSame(1, DB::table('barangay_rider_assignments')->count());
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_existing_hub_ownership_is_preserved_and_demo_writes_roll_back_on_conflict(): void
    {
        $this->seed(DatabaseSeeder::class);
        $otherHub = User::forceCreate([
            'email' => 'other-hub@example.test', 'password' => 'password', 'role' => 'sorting_center',
            'status' => 'approved', 'first_name' => 'Other', 'last_name' => 'Hub',
            'contact_no' => '09123456789',
        ]);
        $area = Area::create(['code' => 'OTHER-MAJ', 'name' => 'Existing area', 'sorting_center_id' => $otherHub->id, 'is_active' => true]);
        AreaMunicipality::create([
            'area_id' => $area->id, 'province' => 'Laguna', 'municipality' => 'Majayjay',
            'province_normalized' => 'laguna', 'municipality_normalized' => 'majayjay',
        ]);

        try {
            $this->seed(OrderJourneyDemoSeeder::class);
            $this->fail('Conflicting hub ownership must stop the demo seeder.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('without changing its ownership', $exception->getMessage());
        }

        $this->assertSame($otherHub->id, $area->fresh()->sorting_center_id);
        $this->assertSame('Origuel', User::where('email', 'seller@ezicart.com')->firstOrFail()->barangay);
        $this->assertSame('Santa Cruz', User::where('email', 'sorting@ezicart.com')->firstOrFail()->municipality);
        $this->assertNull(User::where('email', 'courier@ezicart.com')->firstOrFail()->logistics_owner_id);
        $this->assertDatabaseCount('storage_locations', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('buyer_addresses', 0);
    }
}
