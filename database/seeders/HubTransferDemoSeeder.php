<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ParcelTrackingEvent;
use App\Models\Product;
use App\Models\User;
use App\Services\RiderBadgeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class HubTransferDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Hub transfer demo data is restricted to local/testing environments.');
        }
        DB::transaction(function (): void {
            $admin = $this->account('hub.admin@ezicart.com', 'logistics_admin', 'Demo', 'Administrator', 'Santa Cruz');
            $buyer = $this->account('hub.buyer@ezicart.com', 'buyer', 'Demo', 'Buyer', 'Santa Cruz');
            $seller = $this->account('hub.seller@ezicart.com', 'seller', 'Demo', 'Seller', 'Majayjay');
            $hubs = [];
            $areas = [];
            foreach (['majayjay' => 'Majayjay', 'santacruz' => 'Santa Cruz', 'calamba' => 'Calamba'] as $key => $town) {
                $hub = $this->account('hub.'.$key.'@ezicart.com', 'sorting_center', $town, 'Demo Hub', $town);
                $hubs[$key] = $hub;
                $area = Area::firstOrCreate(['code' => 'HUB-DEMO-'.strtoupper($key)], ['name' => $town.' Hub Transfer Demo Area', 'sorting_center_id' => $hub->id, 'is_active' => true]);
                if ((int) $area->sorting_center_id !== $hub->id || ! $area->is_active) {
                    throw new LogicException('A demo area was reassigned or disabled. No routing ownership was overwritten.');
                }
                $areas[$key] = $area;
                if ($key !== 'majayjay') {
                    $mapping = AreaMunicipality::firstOrCreate(['province_normalized' => 'laguna', 'municipality_normalized' => strtolower($town)], ['province' => 'Laguna', 'municipality' => $town, 'area_id' => $area->id]);
                    if ((int) $mapping->area_id !== $area->id) {
                        throw new LogicException('A destination municipality already has different routing. No routing map was replaced.');
                    }
                }
                foreach (['transport' => true, 'local' => false] as $duty => $transferOnly) {
                    $driver = $this->account($duty.'.'.$key.'@ezicart.com', 'courier', ucfirst($duty), $town.' Driver', $town, [
                        'logistics_owner_id' => $hub->id, 'hub_transfer_only' => $transferOnly,
                        'vehicle_type' => $transferOnly ? 'Light truck' : 'Motorcycle',
                        'vehicle_category' => $transferOnly ? 'four_wheel' : 'two_wheel',
                        'vehicle_capacity_grams' => $transferOnly ? 500000 : 40000,
                        'vehicle_capacity_verified_by' => $admin->id, 'vehicle_capacity_verified_at' => now(),
                        'plate_number' => 'HD'.($transferOnly ? 'T' : 'L').strtoupper(substr($key, 0, 3)),
                    ]);
                    if ((int) $driver->logistics_owner_id !== $hub->id || $driver->hub_transfer_only !== $transferOnly) {
                        throw new LogicException('An existing demo driver has changed hub or duty. No active allocation was reset.');
                    }
                    $driver->serviceAreas()->syncWithoutDetaching([$area->id => ['is_primary' => true, 'is_active' => true]]);
                    app(RiderBadgeService::class)->ensure($driver);
                }
            }
            $product = Product::unguarded(fn () => Product::firstOrCreate(['user_id' => $seller->id, 'name' => 'Hub Transfer Demo Cargo Box'], [
                'description' => 'Local demo cargo for hub transfer testing.', 'category' => 'Home and Garden',
                'price' => 100, 'stock' => 20, 'weight_grams' => 10000, 'compliance_status' => 'approved',
            ]));
            foreach ([['HUB-DEMO-001', 'majayjay', 'santacruz', 21000], ['HUB-DEMO-002', 'santacruz', 'calamba', 22000]] as [$number, $from, $to, $weight]) {
                // A rerun adds missing fixtures; it never rewinds an existing parcel.
                if (Order::where('order_number', $number)->exists()) {
                    continue;
                }
                $order = Order::forceCreate([
                    'order_number' => $number, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
                    'recipient_name' => 'Demo Buyer', 'recipient_contact' => $buyer->contact_no,
                    'province' => 'Laguna', 'municipality' => $hubs[$to]->municipality, 'barangay' => 'Poblacion',
                    'street_address' => '100 Demo Delivery Road', 'subtotal' => 200, 'shipping_fee' => 50,
                    'total_amount' => 250, 'payment_method' => 'COD', 'status' => 'SORTED',
                    'sorting_center_id' => $hubs[$from]->id, 'destination_area_id' => $areas[$to]->id,
                    'delivery_area' => $areas[$to]->name, 'received_at' => now(), 'sorted_at' => now(),
                    'parcel_weight_grams' => $weight, 'weight_recorded_by' => $hubs[$from]->id, 'weight_recorded_at' => now(),
                    'notes' => 'DEMO FIXTURE: begins at source hub before transfer planning. No real pickup scans are implied.',
                ]);
                OrderItem::forceCreate(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 2, 'unit_price' => 100, 'item_total' => 200, 'unit_weight_grams' => 10000]);
                ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $admin->id, 'event_type' => 'hub_demo_initialized', 'status' => 'SORTED', 'notes' => 'Demo fixture initialized at source hub; ready for application transfer actions.']);
            }
        });
        $this->command?->info('Hub demo ready: 3 hubs, 3 transport drivers, 3 local riders, demo admin/buyer/seller and 2 source-hub parcels. Existing demo progress preserved.');
    }

    private function account(string $email, string $role, string $first, string $last, string $town, array $extra = []): User
    {
        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing) {
            if ($existing->role !== $role || $existing->status !== 'approved') {
                throw new LogicException('A demo email belongs to a different role or inactive account: '.$email);
            }

            return $existing;
        }

        return User::forceCreate(array_merge([
            'email' => $email, 'canonical_email' => $email, 'first_name' => $first, 'last_name' => $last,
            'role' => $role, 'status' => 'approved', 'password' => 'Password123!', 'email_verified_at' => now(),
            'province' => 'Laguna', 'municipality' => $town, 'barangay' => 'Poblacion',
            'street_address' => '100 Demo Hub Road', 'business_name' => $first.' '.$last,
            'contact_no' => '09'.substr((string) hexdec(substr(hash('sha256', $email), 0, 10)), -9),
        ], $extra));
    }
}
