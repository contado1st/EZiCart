<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\DeliveryRateVersion;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutPricingService;
use App\Services\DeliveryRateManagementService;
use App\Services\ZoneReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ZoneReadinessTest extends TestCase
{
    use RefreshDatabase;

    private User $hub;

    private User $otherHub;

    private User $maker;

    private User $checker;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->freezeTime();
        config(['financial.sandbox_enabled' => true]);
        $this->hub = $this->user('sorting_center');
        $this->otherHub = $this->user('sorting_center');
        $this->maker = $this->user('logistics_admin');
        $this->checker = $this->user('logistics_admin');
        $this->area = Area::create(['name' => 'Planning zone', 'code' => 'planning-zone', 'sorting_center_id' => $this->hub->id, 'is_active' => true]);
        AreaMunicipality::create(['area_id' => $this->area->id, 'province' => 'Laguna', 'municipality' => 'Majayjay', 'province_normalized' => 'laguna', 'municipality_normalized' => 'majayjay']);
    }

    public function test_mapping_and_approval_do_not_enable_tariffs_without_the_existing_explicit_activation(): void
    {
        $this->rate('same_barangay');
        $service = app(ZoneReadinessService::class);
        $preview = $service->preview($this->hub, $this->input());
        $this->assertFalse($preview['routing_and_tariff_configured']);
        $this->assertSame('legacy', $preview['quote']['model']);
        $this->assertFalse($service->activation()['ready']);
        $this->assertFalse($service->activation()['enabled']);
        foreach (['same_municipality', 'inter_municipality', 'inter_province'] as $class) {
            $this->rate($class);
        }
        $this->assertTrue($service->activation()['ready']);
        $this->assertFalse($service->activation()['enabled']);
        $this->signIn($this->maker)->post(route('finance.rates.activate'), ['reason' => 'Activate independently reviewed synthetic schedules.'])->assertRedirect();
        $this->assertTrue($service->activation()['enabled']);
        $this->assertTrue($service->preview($this->hub, $this->input())['routing_and_tariff_configured']);
    }

    public function test_preview_uses_the_same_weight_route_and_money_as_actual_checkout_quote_and_is_read_only(): void
    {
        $this->rate('same_barangay');
        $this->enable();
        $seller = $this->user('seller');
        $product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Planning comparison', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 10, 'compliance_status' => 'approved', 'weight_grams' => 1000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100]);
        $before = $this->counts();
        $preview = app(ZoneReadinessService::class)->preview($this->hub, $this->input());
        $quote = app(CheckoutPricingService::class)->quote([['product_id' => $product->id, 'quantity' => 1]], null, false, ['address' => ['province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro'], 'payment_method' => 'COD']);
        $this->assertSame(5000, $preview['quote']['total_cents']);
        $this->assertSame(5000, $quote['shipping_cents']);
        $this->assertSame(1000, $preview['quote']['chargeable_grams']);
        $response = $this->signIn($this->hub)->get(route('logistics-zone-readiness', ['preview' => 1, ...$this->input()]))->assertOk()->assertSee('PHP 50.00')->assertDontSee('kg kg');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($before, $this->counts());
        $this->assertSame(10, $product->fresh()->stock);
    }

    #[TestWith(['future_rate'])]
    #[TestWith(['draft_rate'])]
    #[TestWith(['over_weight'])]
    #[TestWith(['volumetric'])]
    #[TestWith(['inactive_area'])]
    #[TestWith(['unassigned_area'])]
    #[TestWith(['missing_mapping'])]
    #[TestWith(['suspended_hub'])]
    public function test_incomplete_route_or_weight_configuration_has_actionable_hold_messages(string $scenario): void
    {
        $this->enable();
        $this->rate('same_barangay', $scenario === 'future_rate' ? now()->addDay()->toDateTimeString() : null, $scenario !== 'draft_rate');
        $data = $this->input();
        if ($scenario === 'over_weight') {
            $data['weight_kg'] = 2;
        }
        if ($scenario === 'volumetric') {
            $data['height_mm'] = 1000;
        }
        if ($scenario === 'inactive_area') {
            $this->area->update(['is_active' => false]);
        }
        if ($scenario === 'unassigned_area') {
            $this->area->update(['sorting_center_id' => null]);
        }
        if ($scenario === 'missing_mapping') {
            $data['municipality'] = 'Unmapped municipality';
        }
        if ($scenario === 'suspended_hub') {
            $this->hub->forceFill(['status' => 'suspended'])->save();
        }
        // An administrator can inspect a suspended hub's configuration without using its account.
        $preview = app(ZoneReadinessService::class)->preview($this->maker, $data);
        $this->assertFalse($preview['routing_and_tariff_configured']);
        $this->assertNotEmpty($preview['issues']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_capacity_preview_distinguishes_verified_local_coverage_and_availability_without_reserving_jobs(): void
    {
        config(['rider-work.enforce_availability' => true]);
        $rider = $this->user('courier');
        $rider->forceFill(['work_availability' => 'available', 'vehicle_capacity_grams' => 10000, 'vehicle_capacity_verified_at' => now(), 'license_expires_at' => today()->addYear()])->save();
        $rider->serviceAreas()->attach($this->area, ['is_active' => true]);
        $this->assertSame(1, app(ZoneReadinessService::class)->preview($this->hub, $this->input())['capacity_candidates']);
        $rider->forceFill(['work_availability' => 'unavailable'])->save();
        $this->assertSame(0, app(ZoneReadinessService::class)->preview($this->hub, $this->input())['capacity_candidates']);
        $rider->forceFill(['work_availability' => 'available'])->save();
        $rider->forceFill(['hub_transfer_only' => true])->save();
        $this->assertSame(0, app(ZoneReadinessService::class)->preview($this->hub, $this->input())['capacity_candidates']);
        $rider->forceFill(['hub_transfer_only' => false, 'license_expires_at' => today()->subDay()])->save();
        $this->assertSame(0, app(ZoneReadinessService::class)->preview($this->hub, $this->input())['capacity_candidates']);
        $rider->forceFill(['license_expires_at' => today()->addYear(), 'vehicle_capacity_verified_at' => null])->save();
        $this->assertSame(0, app(ZoneReadinessService::class)->preview($this->hub, $this->input())['capacity_candidates']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_area_scope_and_role_checks_hide_other_hub_configuration(): void
    {
        $foreign = Area::create(['name' => 'Other private area', 'code' => 'foreign-zone', 'sorting_center_id' => $this->otherHub->id, 'is_active' => true]);
        $this->signIn($this->hub)->get(route('logistics-zone-readiness'))->assertOk()->assertDontSee('Other private area');
        $this->get(route('logistics-zone-readiness', ['preview' => 1, ...$this->input(), 'area_id' => $foreign->id]))->assertNotFound();
        $this->signIn($this->maker)->get(route('logistics-zone-readiness'))->assertOk()->assertSee('Other private area');
        $this->signIn($this->user('buyer'))->get(route('logistics-zone-readiness'))->assertForbidden();
    }

    public function test_invalid_measurements_and_missing_input_are_rejected_without_writes(): void
    {
        $this->signIn($this->hub)->getJson(route('logistics-zone-readiness', ['preview' => 1]))->assertUnprocessable()->assertJsonValidationErrors(['area_id', 'weight_kg']);
        $this->getJson(route('logistics-zone-readiness', ['preview' => 1, ...$this->input(), 'weight_kg' => -1]))->assertUnprocessable()->assertJsonValidationErrors('weight_kg');
        $this->getJson(route('logistics-zone-readiness', ['preview' => 1, ...$this->input(), 'length_mm' => 0]))->assertUnprocessable()->assertJsonValidationErrors('length_mm');
        $this->assertDatabaseCount('delivery_rate_versions', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    private function rate(string $class, ?string $effective = null, bool $approve = true): DeliveryRateVersion
    {
        $service = app(DeliveryRateManagementService::class);
        $rate = $service->create($this->maker, ['name' => 'Synthetic planning tariff', 'route_class' => $class, 'sandbox' => true, 'volumetric_divisor' => 6000, 'effective_at' => $effective ?? now()->subMinute()->toDateTimeString(), 'rules' => ['weight_brackets' => [['max_grams' => 1000, 'components' => ['last_mile' => 5000]]]]]);
        if ($approve) {
            $service->approve($rate, $this->checker, 'Independent synthetic schedule approval.');
        }

        return $rate->refresh();
    }

    private function input(): array
    {
        return ['area_id' => $this->area->id, 'origin_province' => 'Laguna', 'origin_municipality' => 'Majayjay', 'origin_barangay' => 'San Isidro', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'weight_kg' => 1, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100, 'payment_method' => 'COD'];
    }

    private function enable(): void
    {
        DB::table('commerce_settings')->updateOrInsert(['key' => 'shipping_model'], ['value' => 1]);
    }

    private function counts(): array
    {
        return collect(['users', 'orders', 'products', 'financial_accounts', 'ledger_transactions', 'administrative_audits'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    }

    private function signIn(User $user): static
    {
        $this->flushSession();
        Auth::forgetGuards();

        return $this->actingAs($user);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.test', 'password' => 'Password123!', 'first_name' => 'Zone', 'last_name' => 'Tester', 'role' => $role, 'status' => 'approved', 'email_verified_at' => now(), 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro']);
    }
}
