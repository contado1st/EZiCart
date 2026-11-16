<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\Order;
use App\Models\User;
use App\Services\ParcelTransferService;
use App\Services\UserDocumentService;
use Database\Seeders\LagunaMarketplaceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class LagunaMarketplaceDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_twenty_six_complete_private_demo_accounts_cover_fourteen_sellers_all_categories_and_three_towns(): void
    {
        $this->travelTo('2026-11-16 12:00:00');
        Storage::fake('private');
        Notification::fake();
        $this->administrators();

        $this->seed(LagunaMarketplaceDemoSeeder::class);

        $users = User::where('email', 'like', '%@'.LagunaMarketplaceDemoSeeder::DOMAIN)->get();
        $this->assertCount(26, $users);
        $sellers = $users->where('role', 'seller');
        $this->assertCount(14, $sellers);
        $this->assertSame(['Majayjay' => 5, 'Liliw' => 5, 'Magdalena' => 4], $sellers->countBy('municipality')->all());
        $this->assertEqualsCanonicalizing(config('registration.seller_categories'), $sellers->pluck('line_of_business')->unique()->all());
        $this->assertCount(3, $users->where('role', 'sorting_center'));
        $this->assertCount(9, $users->where('role', 'courier'));
        $this->assertCount(3, $users->where('role', 'courier')->where('hub_transfer_only', true));
        $documents = app(UserDocumentService::class);
        foreach ($users as $user) {
            $this->assertSame('approved', $user->status);
            $this->assertTrue(Hash::check(LagunaMarketplaceDemoSeeder::PASSWORD, $user->password));
            $this->assertNotNull($user->email_verified_at);
            $this->assertNotNull($user->terms_accepted_at);
            $this->assertNotEmpty($user->barangay);
            $this->assertMatchesRegularExpression('/^09\d{9}$/', $user->contact_no);
            $this->assertSame($user->contact_no, $user->canonical_mobile);
            $this->assertSame($user->email, $user->canonical_email);
            $this->assertSame(36, $user->age);
            $this->assertSame('Region IV-A (CALABARZON)', $user->region);
            $this->assertStringStartsWith('Fictional Laguna marketplace demo.', $user->profile_bio);
            Storage::disk('private')->assertExists($user->profile_avatar_path);
            foreach ($documents->submittedForReview($user) as $document) {
                $this->assertTrue($document['available'], $user->email.' '.$document['type']);
                $path = $documents->path($user, $document['type']);
                $size = getimagesizefromstring(Storage::disk('private')->get($path));
                $this->assertSame(IMAGETYPE_PNG, $size[2]);
                $this->assertLessThan(5 * 1024 * 1024, Storage::disk('private')->size($path));
            }
            if ($user->role === 'courier') {
                $this->assertNotNull($user->vehicle_capacity_verified_at);
                $this->assertGreaterThanOrEqual(30000, $user->vehicle_capacity_grams);
                $this->assertTrue($user->license_expires_at->isFuture());
                $this->assertMatchesRegularExpression('/^[A-Z]\d{2}-\d{2}-\d{6}$/', $user->license_number);
                $this->assertSame('available', $user->work_availability);
                $this->assertNotEmpty($user->badge_code);
            } else {
                $this->assertMatchesRegularExpression('/^\d{3}-\d{3}-\d{3}-\d{3}$/', $user->tin);
            }
        }
        $this->assertDatabaseCount('area_municipalities', 3);
        $this->assertDatabaseCount('barangay_rider_assignments', 97);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('administrative_audits', 26);
        Storage::disk('private')->assertExists(LagunaMarketplaceDemoSeeder::MANIFEST);
        Storage::disk('private')->assertExists(LagunaMarketplaceDemoSeeder::LOGIN_GUIDE);
        Notification::assertNothingSent();

        foreach (['seller.majayjay.01', 'hub.liliw', 'rider.magdalena.local1', 'rider.majayjay.transport'] as $login) {
            $user = $this->account($login);
            $this->flushSession();
            Auth::forgetGuards();
            $target = match ($user->role) {
                'seller' => 'seller.dashboard', 'sorting_center' => 'logistics.dashboard', default => 'courier.dashboard',
            };
            $this->post(route('login.post'), ['email' => $user->email, 'password' => LagunaMarketplaceDemoSeeder::PASSWORD])->assertRedirect(route($target));
            $this->assertAuthenticatedAs($user);
        }
    }

    public function test_reruns_preserve_existing_routes_primary_riders_passwords_and_work_choices_and_allow_majayjay_transport(): void
    {
        Storage::fake('private');
        Notification::fake();
        [, $administrator] = $this->administrators();
        $existingHub = $this->user('sorting_center', 'existing-hub@example.test');
        $existingRider = $this->user('courier', 'existing-rider@example.test');
        $area = Area::create(['name' => 'Original Majayjay', 'code' => 'ORIGINAL-MAJ', 'sorting_center_id' => $existingHub->id, 'is_active' => true]);
        $mapping = AreaMunicipality::create(['area_id' => $area->id, 'province' => 'Laguna', 'municipality' => 'Majayjay', 'province_normalized' => 'laguna', 'municipality_normalized' => 'majayjay']);
        $coverageId = DB::table('barangay_rider_assignments')->insertGetId(['area_municipality_id' => $mapping->id, 'barangay' => 'Amonoy', 'barangay_normalized' => 'amonoy', 'primary_rider_id' => $existingRider->id, 'created_at' => now(), 'updated_at' => now()]);
        $oldHub = $existingHub->fresh()->getRawOriginal();
        $oldCoverage = (array) DB::table('barangay_rider_assignments')->where('id', $coverageId)->first();
        $this->seed(LagunaMarketplaceDemoSeeder::class);
        $seller = $this->account('seller.majayjay.01');
        $seller->forceFill(['password' => 'ChangedDemo!2026'])->save();
        $local = $this->account('rider.majayjay.local1');
        $local->forceFill(['work_availability' => 'unavailable', 'status' => 'suspended'])->save();
        $this->seed(LagunaMarketplaceDemoSeeder::class);

        $this->assertSame(26, User::where('email', 'like', '%@'.LagunaMarketplaceDemoSeeder::DOMAIN)->count());
        $this->assertSame($oldHub, $existingHub->fresh()->getRawOriginal());
        $this->assertSame($oldCoverage, (array) DB::table('barangay_rider_assignments')->where('id', $coverageId)->first());
        $this->assertSame($area->id, $mapping->fresh()->area_id);
        $this->assertSame($existingHub->id, $local->fresh()->logistics_owner_id);
        $this->assertTrue(Hash::check('ChangedDemo!2026', $seller->fresh()->password));
        $this->assertSame('unavailable', $local->fresh()->work_availability);
        $this->assertSame('suspended', $local->fresh()->status);
        $manifest = json_decode(Storage::disk('private')->get(LagunaMarketplaceDemoSeeder::MANIFEST), true);
        $this->assertSame('Existing password retained', collect($manifest['accounts'])->firstWhere('id', $seller->id)['password']);
        $this->assertDatabaseCount('administrative_audits', 26);

        $driver = $this->account('rider.majayjay.transport');
        $this->assertSame($this->account('hub.majayjay')->id, $driver->logistics_owner_id);
        $this->assertTrue($driver->serviceAreas()->where('areas.id', $area->id)->exists());
        $buyer = $this->user('buyer', 'fixture-buyer@example.test');
        $destination = $this->account('hub.liliw');
        $destinationArea = AreaMunicipality::where('municipality_normalized', 'liliw')->firstOrFail()->area;
        $order = Order::forceCreate(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'order_number' => 'LAGUNA-TRANSFER-CHECK', 'status' => 'SORTED',
            'sorting_center_id' => $existingHub->id, 'destination_area_id' => $destinationArea->id, 'parcel_weight_grams' => 1000,
            'payment_method' => 'COD', 'subtotal' => 100, 'shipping_fee' => 0, 'total_amount' => 100,
            'recipient_name' => 'Demo Buyer', 'recipient_contact' => '09999999999', 'province' => 'Laguna', 'municipality' => 'Liliw', 'barangay' => 'Bagong Anyo', 'street_address' => 'Demo only']);
        $transfers = app(ParcelTransferService::class);
        $transfer = $transfers->plan($order, $administrator, $destination, 'Isolated fixture routing check.');
        $transfers->release($transfer, $existingHub, 'EZP:'.$order->parcel_code, 'DEMO-ONLY-MANIFEST', $driver);
        $this->assertSame('IN_TRANSIT', $transfer->fresh()->status);
        $this->assertSame($driver->id, $transfer->fresh()->carrier_rider_id);
    }

    public function test_identity_collision_rolls_back_the_dataset_and_cleans_only_new_sample_files(): void
    {
        Storage::fake('private');
        $this->administrators();
        $collision = $this->user('buyer', 'seller.liliw.14@'.LagunaMarketplaceDemoSeeder::DOMAIN);
        Storage::disk('private')->put('keep-existing.txt', 'Existing private file');
        try {
            $this->seed(LagunaMarketplaceDemoSeeder::class);
            $this->fail('Expected an identity collision to stop this fixture.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('demo identity was changed', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 3);
        $this->assertModelExists($collision);
        $this->assertDatabaseCount('areas', 0);
        $this->assertDatabaseCount('administrative_audits', 0);
        $this->assertSame(['keep-existing.txt'], Storage::disk('private')->allFiles());
    }

    public function test_demo_data_is_refused_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(LogicException::class);
        $this->seed(LagunaMarketplaceDemoSeeder::class);
    }

    private function administrators(): array
    {
        return [$this->user('ecommerce_admin', 'fixture-commerce@example.test'), $this->user('logistics_admin', 'fixture-logistics@example.test')];
    }

    private function user(string $role, string $email): User
    {
        return User::forceCreate(['role' => $role, 'status' => 'approved', 'email' => $email, 'password' => 'AdminDemo!2026',
            'first_name' => 'Existing', 'last_name' => 'Fixture', 'municipality' => 'Majayjay', 'province' => 'Laguna',
            'contact_no' => '0987'.str_pad((string) (User::count() + 1), 7, '0', STR_PAD_LEFT)]);
    }

    private function account(string $login): User
    {
        return User::where('email', $login.'@'.LagunaMarketplaceDemoSeeder::DOMAIN)->firstOrFail();
    }
}
