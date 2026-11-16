<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\User;
use App\Services\AdministrativeAuditService;
use App\Services\RiderBadgeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Throwable;

class LagunaMarketplaceDemoSeeder extends Seeder
{
    public const DOMAIN = 'laguna-demo.test';

    public const PASSWORD = 'LagunaDemo!2026';

    public const MANIFEST = 'demo/laguna-marketplace/accounts.json';

    public const LOGIN_GUIDE = 'demo/laguna-marketplace/accounts.md';

    private array $newFiles = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Laguna fictional demo accounts are restricted to local/testing.');
        }
        if (! function_exists('imagecreatetruecolor')) {
            throw new LogicException('The existing GD extension is required for sample document images.');
        }
        $source = json_decode(file_get_contents(database_path('seeders/fixtures/laguna-demo-addresses.json')), true, 512, JSON_THROW_ON_ERROR);
        $categories = array_values(config('registration.seller_categories'));
        if (! $categories || count($categories) > 14) {
            throw new LogicException('The fourteen-seller fixture cannot cover this category configuration.');
        }
        $commerce = User::whereIn('role', ['admin', 'ecommerce_admin'])->where('status', 'approved')->orderBy('id')->first();
        $logistics = User::where('role', 'logistics_admin')->where('status', 'approved')->orderBy('id')->first();
        if (! $commerce || ! $logistics) {
            throw new LogicException('Existing approved commerce and logistics administrator records are required for fixture references.');
        }
        $this->newFiles = [];
        try {
            $report = DB::transaction(function () use ($source, $categories, $commerce, $logistics): array {
                $accounts = [];
                $network = [];
                foreach (array_values(array_keys($source['municipalities'])) as $townIndex => $slug) {
                    $town = $source['municipalities'][$slug];
                    $town['slug'] = $slug;
                    $hub = $this->account('hub.'.$slug, 'sorting_center', $town, 101 + $townIndex, $logistics, [
                        'first_name' => $town['name'], 'last_name' => 'Demo Hub',
                        'business_name' => 'DEMO '.$town['name'].' Sorting Hub',
                        'canonical_company_name' => 'demo '.strtolower($town['name']).' sorting hub',
                        'contact_position' => 'Demo operations representative',
                        'service_areas' => [$source['region']],
                        'sec_registration_no' => 'DX'.str_pad((string) (101 + $townIndex), 9, '0', STR_PAD_LEFT),
                        'canonical_sec_registration_no' => 'DX'.str_pad((string) (101 + $townIndex), 9, '0', STR_PAD_LEFT),
                    ]);
                    $accounts[] = $hub;
                    $demoArea = Area::firstOrCreate(['code' => 'LAGUNA-DEMO-'.strtoupper($slug)], [
                        'name' => 'DEMO '.$town['name'].' hub area', 'sorting_center_id' => $hub->id, 'is_active' => true,
                    ]);
                    if ((int) $demoArea->sorting_center_id !== $hub->id || ! $demoArea->is_active) {
                        throw new LogicException('The demo area was reassigned or disabled; it was not overwritten.');
                    }
                    $mapping = AreaMunicipality::firstOrCreate(['province_normalized' => 'laguna', 'municipality_normalized' => $slug], [
                        'province' => 'Laguna', 'municipality' => $town['name'], 'area_id' => $demoArea->id,
                    ]);
                    $primaryArea = $mapping->area;
                    $primaryHub = $primaryArea?->sortingCenter;
                    if (! $primaryArea?->is_active || $primaryHub?->role !== 'sorting_center' || $primaryHub->status !== 'approved') {
                        throw new LogicException('The existing municipality route has no active approved hub; no routing was replaced.');
                    }
                    $locals = [];
                    foreach ([1, 2, 3] as $number) {
                        $transport = $number === 3;
                        $owner = $transport ? $hub : $primaryHub;
                        $serial = 200 + $townIndex * 10 + $number;
                        $vehicle = $transport ? 'Van' : ($number === 1 ? 'Motorcycle' : 'Tricycle');
                        $rider = $this->account('rider.'.$slug.'.'.($transport ? 'transport' : 'local'.$number), 'courier', $town, $serial, $logistics, [
                            'first_name' => ['Lara', 'Marco', 'Tomas'][$number - 1], 'last_name' => $town['name'].' Demo',
                            'logistics_owner_id' => $owner->id, 'hub_transfer_only' => $transport,
                            'vehicle_type' => $vehicle, 'vehicle_category' => config('rider-vehicles.types')[$vehicle],
                            'vehicle_description' => 'Fictional school-demo '.$vehicle.'; no real vehicle inspection claimed.',
                            'plate_number' => 'DEMO '.$serial, 'canonical_plate' => 'DEMO'.$serial,
                            'license_number' => sprintf('D%02d-26-%06d', $townIndex + 1, $serial),
                            'canonical_license_number' => sprintf('D%02d-26-%06d', $townIndex + 1, $serial),
                            'license_expires_at' => now()->addYears(4)->toDateString(),
                            'vehicle_capacity_grams' => $transport ? 500000 : ($number === 1 ? 30000 : 75000),
                            'vehicle_capacity_verified_at' => now(), 'vehicle_capacity_verified_by' => $logistics->id,
                            'work_availability' => 'available', 'work_availability_changed_at' => now(), 'work_availability_version' => 1,
                        ]);
                        if ((int) $rider->logistics_owner_id !== $owner->id || $rider->hub_transfer_only !== $transport) {
                            throw new LogicException('An existing demo rider changed duty or home hub; no assignment was reset.');
                        }
                        $this->membership($rider, $transport ? $demoArea : $primaryArea, true);
                        if ($transport && $primaryArea->id !== $demoArea->id) {
                            // Existing transfer rules explicitly permit carriers with source-hub area membership.
                            $this->membership($rider, $primaryArea, false);
                        }
                        app(RiderBadgeService::class)->ensure($rider);
                        $accounts[] = $rider;
                        if (! $transport) {
                            $locals[] = $rider;
                        }
                    }
                    foreach ($town['barangays'] as $index => $barangay) {
                        $name = trim($barangay['name']);
                        $where = ['area_municipality_id' => $mapping->id, 'barangay_normalized' => mb_strtolower($name)];
                        if (! DB::table('barangay_rider_assignments')->where($where)->exists()) {
                            DB::table('barangay_rider_assignments')->insert($where + [
                                'barangay' => $name, 'primary_rider_id' => $locals[$index % 2]->id,
                                'backup_rider_id' => $locals[($index + 1) % 2]->id, 'created_at' => now(), 'updated_at' => now(),
                            ]);
                        }
                    }
                    $network[$slug] = ['town' => $town['name'], 'new_demo_hub_id' => $hub->id, 'primary_hub_id' => $primaryHub->id,
                        'primary_area_id' => $primaryArea->id, 'mapping_id' => $mapping->id, 'existing_routing_preserved' => $primaryHub->id !== $hub->id];
                }
                $slots = $categories;
                while (count($slots) < 14) {
                    $slots[] = $categories[(count($slots) - count($categories)) % count($categories)];
                }
                $townSlugs = array_keys($source['municipalities']);
                $names = ['Alma', 'Benito', 'Cara', 'Dario', 'Elena', 'Fabian', 'Gina', 'Hugo', 'Iris', 'Joel', 'Kara', 'Lino', 'Mina', 'Nico'];
                foreach ($slots as $index => $category) {
                    $slug = $townSlugs[$index % 3];
                    $town = $source['municipalities'][$slug] + ['slug' => $slug];
                    $shop = sprintf('DEMO %s Shop %02d', strtoupper(substr($slug, 0, 3)), $index + 1);
                    $accounts[] = $this->account(sprintf('seller.%s.%02d', $slug, $index + 1), 'seller', $town, 301 + $index, $commerce, [
                        'first_name' => $names[$index], 'last_name' => 'Demo',
                        'business_name' => $shop, 'shop_name' => $shop, 'canonical_shop_name' => strtolower($shop),
                        'line_of_business' => $category, 'seller_type' => 'Sole Proprietorship', 'shop_availability' => 'open',
                    ]);
                }

                return ['accounts' => $accounts, 'network' => $network, 'address_source' => $source['source']];
            });
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($this->newFiles);
            throw $exception;
        }
        $this->export($report);
        $this->command?->info('Laguna demo ready: 14 sellers (5 Majayjay, 5 Liliw, 4 Magdalena), 3 new hubs and 9 riders. Existing routing and account changes preserved.');
        $this->command?->line('Private login guide: storage/app/private/'.self::LOGIN_GUIDE);
    }

    private function account(string $login, string $role, array $town, int $serial, User $administrator, array $extra): User
    {
        $email = $login.'@'.self::DOMAIN;
        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing) {
            if ($existing->role !== $role || $existing->municipality !== $town['name'] || ! str_starts_with((string) $existing->profile_bio, 'Fictional Laguna marketplace demo.')) {
                throw new LogicException('A demo identity was changed or is owned by another account: '.$email);
            }

            return $existing;
        }
        $phone = '0999'.str_pad((string) $serial, 7, '0', STR_PAD_LEFT);
        if (User::where('canonical_mobile', $phone)->orWhere('contact_no', $phone)->exists()) {
            throw new LogicException('A demo mobile identifier is already used; no existing identity was changed.');
        }
        $birthday = '1990-01-'.str_pad((string) ($serial % 27 + 1), 2, '0', STR_PAD_LEFT);
        $digits = '980'.str_pad((string) $serial, 9, '0', STR_PAD_LEFT);
        $barangay = trim($town['barangays'][$serial % count($town['barangays'])]['name']);
        $data = array_merge([
            'email' => $email, 'canonical_email' => $email, 'password' => self::PASSWORD,
            'role' => $role, 'status' => 'approved', 'email_verified_at' => now(), 'phone_verified_at' => now(),
            'first_name' => 'Demo', 'middle_name' => 'Sample', 'middle_initial' => 'S', 'last_name' => 'Account', 'sex' => 'Other',
            'birthday' => $birthday, 'age' => (int) now()->diffInYears($birthday, true), 'contact_no' => $phone, 'canonical_mobile' => $phone,
            'region' => 'Region IV-A (CALABARZON)', 'province' => 'Laguna', 'municipality' => $town['name'],
            'barangay' => $barangay, 'house_number' => 'DEMO-'.$serial, 'street_address' => 'Fictional Demo Lane '.$serial, 'postal_code' => $town['postal_code'],
            'profile_bio' => 'Fictional Laguna marketplace demo. School testing only; documents, approvals, identifiers and verification are simulated.',
            'terms_accepted_at' => now(), 'terms_version' => config('registration.policy_version'),
            'privacy_accepted_at' => now(), 'privacy_version' => config('registration.policy_version'),
            'approved_at' => now(), 'approved_by' => $administrator->id,
        ], $role !== 'courier' ? ['tin' => implode('-', str_split($digits, 3)), 'canonical_tin' => $digits] : [], $extra);
        $data['profile_display_name'] = $data['business_name'] ?? $data['first_name'].' '.$data['last_name'];
        $documents = match ($role) {
            'seller' => ['id_path' => 'Sample identity document', 'permit_path' => 'Sample business permit'],
            'sorting_center' => ['id_path' => 'Sample representative identity', 'permit_path' => 'Sample business permit', 'sec_certificate_path' => 'Sample SEC certificate', 'bir_certificate_path' => 'Sample BIR Form 2303'],
            'courier' => ['id_path' => 'Sample identity document', 'license_path' => 'Sample driver license', 'or_cr_path' => 'Sample vehicle OR CR'],
        };
        foreach ($documents as $field => $title) {
            $data[$field] = $this->document($email, $field, $title, $data);
        }
        $data['id_upload_path'] = $data['id_path'];
        if (isset($data['permit_path'])) {
            $data['business_permit_path'] = $data['permit_path'];
        }
        if ($role === 'sorting_center') {
            $data['representative_id_path'] = $data['id_path'];
        }
        if ($role === 'courier') {
            $data['or_cr_upload_path'] = $data['or_cr_path'];
        }
        $data['profile_avatar_path'] = $this->document($email, 'avatar', 'DEMO PROFILE', $data);
        $user = User::forceCreate($data);
        app(AdministrativeAuditService::class)->record(null, $user, 'laguna_demo_account_seeded', [], [
            'role' => $role, 'municipality' => $town['name'], 'category' => $data['line_of_business'] ?? null,
            'hub_transfer_only' => $data['hub_transfer_only'] ?? null, 'logistics_owner_id' => $data['logistics_owner_id'] ?? null,
            'fixture_administrator_reference' => $administrator->id,
        ], 'Owner-requested fictional school fixture. Approval, availability, identity documents and vehicle verification are simulated; no real person, legal document, inspection or consent is asserted.');

        return $user;
    }

    private function membership(User $rider, Area $area, bool $primary): void
    {
        if (! DB::table('area_user')->where('user_id', $rider->id)->where('area_id', $area->id)->exists()) {
            $rider->serviceAreas()->attach($area->id, ['is_primary' => $primary, 'is_active' => true]);
        }
    }

    private function document(string $email, string $field, string $title, array $data): string
    {
        $path = 'demo/laguna-marketplace/documents/'.sha1($email).'/'.$field.'.png';
        if (Storage::disk('private')->exists($path)) {
            return $path;
        }
        $image = imagecreatetruecolor(720, 480);
        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 30, 40, 55);
        $red = imagecolorallocate($image, 170, 20, 50);
        imagefill($image, 0, 0, $white);
        imagestring($image, 5, 24, 20, 'DEMO ONLY - NOT A REAL OR VALID DOCUMENT', $red);
        imagestring($image, 5, 24, 58, strtoupper($title), $ink);
        $lines = [
            $data['first_name'].' '.$data['last_name'].' | '.$data['role'],
            $data['business_name'] ?? ($data['hub_transfer_only'] ?? false ? 'Hub-to-hub transport courier' : 'Parcel pickup and delivery courier'),
            $data['municipality'].', Laguna | '.$data['barangay'],
            'Sample date of birth: '.$data['birthday'].' | '.$data['sex'],
            $data['email'], 'Synthetic phone: '.$data['contact_no'],
            isset($data['tin']) ? 'Sample TIN: '.$data['tin'] : 'Sample license: '.($data['license_number'] ?? 'Not applicable'),
            isset($data['sec_registration_no']) ? 'Sample SEC: '.$data['sec_registration_no'] : 'Sample plate: '.($data['plate_number'] ?? 'Not applicable'),
            'School test fixture. No government seal or real verification.',
        ];
        if (isset($data['license_expires_at'])) {
            array_splice($lines, -1, 0, ['Demo license expires: '.$data['license_expires_at'], 'Demo cargo capacity: '.($data['vehicle_capacity_grams'] / 1000).' kg (not a real inspection)']);
        }
        $y = 100;
        foreach ($lines as $line) {
            foreach (explode("\n", wordwrap($line, 72, "\n", true)) as $part) {
                imagestring($image, 4, 24, $y, $part, $ink);
                $y += 24;
            }
        }
        imagestring($image, 5, 24, 435, 'FICTIONAL SAMPLE - NO LEGAL VALIDITY', $red);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        if (! Storage::disk('private')->put($path, $bytes)) {
            throw new RuntimeException('Could not save the private sample document.');
        }
        $this->newFiles[] = $path;

        return $path;
    }

    private function export(array $report): void
    {
        $rows = [];
        $markdown = "# Fictional Laguna demo accounts\n\nSchool testing only. All identities, addresses within the named barangays, phone numbers, TINs, licenses, plates and documents are synthetic. Do not contact these numbers or treat the images as legal documents. No external email was sent.\n\nDefault password for newly created accounts: `".self::PASSWORD."`. Existing passwords are never reset.\n\n| Municipality | Role / category / duty | Email | Password | Home hub |\n| --- | --- | --- | --- | --- |\n";
        foreach ($report['accounts'] as $user) {
            $user->loadMissing('logisticsOwner');
            $password = Hash::check(self::PASSWORD, $user->password) ? self::PASSWORD : 'Existing password retained';
            $description = $user->role === 'courier' ? $user->riderDutyLabel() : ($user->line_of_business ?: 'Sorting center');
            $hub = $user->logisticsOwner?->business_name ?? '';
            $rows[] = ['id' => $user->id, 'role' => $user->role, 'municipality' => $user->municipality, 'category_or_duty' => $description,
                'email' => $user->email, 'password' => $password, 'home_hub_id' => $user->logistics_owner_id,
                'home_hub' => $hub, 'status' => $user->status];
            $markdown .= '| '.$user->municipality.' | '.$description.' | '.$user->email.' | '.$password.' | '.$hub." |\n";
        }
        $markdown .= "\nMajayjay's existing primary route and existing barangay assignments are preserved. Its parcel riders serve the existing primary hub; its new demo hub has a dedicated transport driver with source-hub coverage at both Majayjay hubs. Liliw and Magdalena use the new demo hubs only where no earlier municipality route exists.\n\nAll required sample files are in private storage and can be viewed through the normal authorized staff/application document pages. Vehicle capacities are demo verification fixtures, not manufacturer specifications. No orders, payments or balances were created.\n";
        if (! Storage::disk('private')->put(self::MANIFEST, json_encode(['accounts' => $rows, 'network' => $report['network'], 'address_source' => $report['address_source']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))
            || ! Storage::disk('private')->put(self::LOGIN_GUIDE, $markdown)) {
            throw new RuntimeException('The accounts exist, but their private login manifest could not be written. Rerun without resetting passwords.');
        }
    }
}
