<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\Product;
use App\Models\ProductComplianceEvent;
use App\Models\User;
use App\Services\RiderBadgeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

class OrderJourneyDemoSeeder extends Seeder
{
    public function run(RiderBadgeService $badges): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('The order journey demo is restricted to local/testing environments.');
        }

        $result = DB::transaction(function () use ($badges): array {
            $buyer = $this->account('buyer@ezicart.com', 'buyer');
            $seller = $this->account('seller@ezicart.com', 'seller');
            $rider = $this->account('courier@ezicart.com', 'courier');
            $hub = $this->account('sorting@ezicart.com', 'sorting_center');
            $admin = $this->account('admin@ezicart.com', 'admin');

            if ($rider->logistics_owner_id !== null && (int) $rider->logistics_owner_id !== $hub->id) {
                throw new LogicException('The demo rider belongs to another hub. No existing ownership was changed.');
            }
            // These explicitly selected demo accounts share one pickup/drop-off
            // location so the walkthrough stays within a single hub and area.
            foreach ([$buyer, $seller, $rider, $hub] as $account) {
                $account->forceFill([
                    'region' => 'Region IV-A (CALABARZON)',
                    'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Roque',
                    'house_number' => '45', 'street_address' => 'Market Road', 'postal_code' => '4005',
                    'email_verified_at' => $account->email_verified_at ?? now(),
                ])->save();
            }
            $rider->forceFill(['logistics_owner_id' => $hub->id])->save();

            $mapping = AreaMunicipality::query()->where('province_normalized', 'laguna')
                ->where('municipality_normalized', 'majayjay')->first();
            $area = $mapping?->area ?? Area::query()->firstOrCreate(['code' => 'EZ-DEMO-MAJ'], [
                'name' => 'Majayjay Demo Delivery Area', 'is_active' => true, 'sorting_center_id' => $hub->id,
            ]);
            if (! $area->is_active || (int) $area->sorting_center_id !== $hub->id) {
                throw new LogicException('Majayjay routing belongs to another or inactive hub. Demo setup stopped without changing its ownership.');
            }
            if ($rider->serviceAreas()->whereNotNull('sorting_center_id')->where('sorting_center_id', '!=', $hub->id)->exists()) {
                throw new LogicException('The seeded rider already serves another hub. Demo setup stopped without changing rider coverage.');
            }
            $mapping ??= AreaMunicipality::query()->create([
                'area_id' => $area->id, 'province' => 'Laguna', 'municipality' => 'Majayjay',
                'province_normalized' => 'laguna', 'municipality_normalized' => 'majayjay',
            ]);
            $coverage = DB::table('barangay_rider_assignments')->where('area_municipality_id', $mapping->id)
                ->where('barangay_normalized', 'san roque')->first();
            if ($coverage && (int) $coverage->primary_rider_id !== $rider->id) {
                throw new LogicException('San Roque already has another primary rider. Demo setup stopped without replacing it.');
            }
            $membership = DB::table('area_user')->where('user_id', $rider->id)->where('area_id', $area->id)->first();
            if (! $membership) {
                $rider->serviceAreas()->attach($area->id, [
                    'is_primary' => ! $rider->serviceAreas()->wherePivot('is_primary', true)->exists(), 'is_active' => true,
                ]);
            } else {
                // serviceAreas intentionally hides inactive pivots; use the
                // stored membership here to reactivate rather than insert it twice.
                DB::table('area_user')->where('user_id', $rider->id)->where('area_id', $area->id)
                    ->update(['is_active' => true, 'updated_at' => now()]);
            }
            if (! $coverage) {
                DB::table('barangay_rider_assignments')->insert([
                    'area_municipality_id' => $mapping->id, 'barangay' => 'San Roque',
                    'barangay_normalized' => 'san roque', 'primary_rider_id' => $rider->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $badges->ensure($rider);

            $address = $buyer->addresses()->firstOrCreate(['label' => 'Demo delivery — Majayjay'], [
                'recipient_name' => trim($buyer->first_name.' '.$buyer->last_name),
                'recipient_contact' => $buyer->contact_no, 'region' => 'Region IV-A (CALABARZON)',
                'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Roque',
                'house_number' => '45', 'street_address' => 'Market Road', 'postal_code' => '4005',
                'is_default' => ! $buyer->addresses()->where('is_default', true)->exists(),
            ]);
            $address->forceFill([
                'recipient_name' => trim($buyer->first_name.' '.$buyer->last_name),
                'recipient_contact' => $buyer->contact_no, 'region' => $buyer->region,
                'province' => $buyer->province, 'municipality' => $buyer->municipality,
                'barangay' => $buyer->barangay, 'house_number' => $buyer->house_number,
                'street_address' => $buyer->street_address, 'postal_code' => $buyer->postal_code,
            ])->save();

            $product = $seller->products()->where('name', 'EZiCart Demo Everyday Tote Bag')->first();
            if (! $product) {
                $product = Product::query()->forceCreate([
                    'user_id' => $seller->id, 'name' => 'EZiCart Demo Everyday Tote Bag',
                    'description' => 'Reusable everyday tote bag. Local demonstration listing for the complete COD parcel journey.',
                    'category' => 'Fashion', 'price' => 100, 'stock' => 3, 'is_archived' => false,
                    'compliance_status' => 'approved', 'compliance_reviewed_by' => $admin->id,
                    'compliance_reviewed_at' => now(), 'compliance_note' => 'Approved local demo fixture; no real document approval is represented.',
                ]);
                ProductComplianceEvent::query()->create([
                    'product_id' => $product->id, 'actor_id' => $admin->id, 'action' => 'approved',
                    'previous_status' => null, 'new_status' => 'approved', 'note' => 'Created by OrderJourneyDemoSeeder as an approved local demonstration fixture.',
                ]);
            }

            $location = DB::table('storage_locations')->where('hub_id', $hub->id)->where('code', 'DEMO-STAGE-01')->first();
            if (! $location) {
                DB::table('storage_locations')->insert([
                    'hub_id' => $hub->id, 'code' => 'DEMO-STAGE-01', 'label' => 'Demo Majayjay staging bin',
                    'type' => 'STAGING', 'area_id' => $area->id, 'capacity' => 10, 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } elseif (! $location->is_active || $location->type !== 'STAGING' || (int) $location->area_id !== $area->id) {
                throw new LogicException('The demo staging code already has different settings. No existing location was changed.');
            }

            return ['product' => $product, 'address' => $address, 'accounts' => [$buyer, $seller, $rider, $hub, $admin]];
        });

        $this->command?->info('Demo ready: '.$result['product']->name.' (product #'.$result['product']->id.').');
        $this->command?->line('Buyer: buyer@ezicart.com | Seller: seller@ezicart.com | Rider: courier@ezicart.com | Hub: sorting@ezicart.com');
        $this->command?->line('All four demo profiles: 45 Market Road, San Roque, Majayjay, Laguna 4005. Rider linked to the demo hub.');
        $passwordMatches = collect($result['accounts'])->every(fn (User $user): bool => Hash::check('Password123!', $user->password));
        $this->command?->line($passwordMatches ? 'All five demo accounts use Password123!.' : 'Existing account passwords were preserved; use their current credentials.');
        $this->command?->line('Saved address: Demo delivery — Majayjay (#'.$result['address']->id.'). Buy quantity 1 using COD; use the checkout total.');
        $this->command?->line('Hub storage scan: EZL:DEMO-STAGE-01. No order, delivery proof or review was pre-created.');
        $this->command?->line('For a walkthrough starting at product creation, follow docs/full-order-flow-demo-audit.md.');
    }

    private function account(string $email, string $role): User
    {
        $user = User::query()->where('email', $email)->lockForUpdate()->first();
        if (! $user || $user->role !== $role || $user->status !== 'approved') {
            throw new LogicException('An approved seeded '.$role.' account is required at '.$email.'. No existing account was changed.');
        }

        return $user;
    }
}
