<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\RiderActivationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RiderActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_approved_logistics_can_add_a_rider_with_private_documents_and_activation_link(bool $hubTransport): void
    {
        Storage::fake('private');
        Notification::fake();
        $company = $this->user('sorting_center', 'approved');

        $details = [...$this->riderDetails(), 'hub_transfer_only' => $hubTransport ? '1' : '0', 'logistics_owner_id' => 999999];
        $this->actingAs($company)->post(route('logistics.riders.store'), $details)
            ->assertRedirect()->assertSessionHasNoErrors();

        $rider = User::query()->where('email', 'rider@example.test')->firstOrFail();
        $this->assertSame('courier', $rider->role);
        $this->assertSame('pending_activation', $rider->status);
        $this->assertSame($company->id, $rider->logistics_owner_id);
        $this->assertSame($hubTransport, $rider->hub_transfer_only);
        $label = $hubTransport ? 'Hub-to-hub transport courier' : 'Parcel pickup and delivery courier';
        $this->assertSame($label, $rider->riderDutyLabel());
        $this->assertNull($rider->vehicle_capacity_verified_at);
        $this->assertNotNull($rider->license_path);
        $this->assertNotNull($rider->or_cr_path);
        Storage::disk('private')->assertExists($rider->license_path);
        Storage::disk('private')->assertExists($rider->or_cr_path);
        $this->assertDatabaseHas('rider_activation_tokens', ['user_id' => $rider->id]);
        Notification::assertSentTo($rider, RiderActivationNotification::class, function ($notification) use ($rider, $label, $company): bool {
            $lines = $notification->toMail($rider)->introLines;

            return in_array('Assigned duty: '.$label.'.', $lines, true) && in_array('Home hub: '.$company->first_name.'.', $lines, true);
        });
        $audit = DB::table('administrative_audits')->where('actor_id', $company->id)->where('entity_id', $rider->id)->where('action', 'rider_account_created')->first();
        $this->assertNotNull($audit);
        $this->assertSame($hubTransport, json_decode($audit->new_values, true)['hub_transfer_only']);
        $this->get(route('logistics.riders'))->assertOk()->assertSee($label)->assertSee('Choose the rider’s duty');
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_activation_link_is_single_use_and_sets_a_compliant_password(bool $hubTransport): void
    {
        Storage::fake('private');
        Notification::fake();
        $company = $this->user('sorting_center', 'approved');
        $this->actingAs($company)->post(route('logistics.riders.store'), [...$this->riderDetails(), 'hub_transfer_only' => $hubTransport ? '1' : '0'])->assertRedirect()->assertSessionHasNoErrors();
        $rider = User::query()->where('role', 'courier')->firstOrFail();
        $token = str_repeat('a', 64);
        DB::table('rider_activation_tokens')->where('user_id', $rider->id)->update(['token_hash' => hash('sha256', $token)]);

        $label = $hubTransport ? 'Hub-to-hub transport courier' : 'Parcel pickup and delivery courier';
        $this->get(route('rider.activation.show', ['token' => $token]))->assertOk()->assertSee('Activate your rider account')->assertSee($label)->assertSee('Home hub:');
        $this->post(route('rider.activation.store'), [
            'token' => $token,
            'password' => 'RiderPass@2026',
            'password_confirmation' => 'RiderPass@2026',
            'hub_transfer_only' => ! $hubTransport,
            'logistics_owner_id' => 999999,
        ])->assertRedirect(route('login'));
        $this->assertSame('approved', $rider->fresh()->status);
        $this->assertSame($hubTransport, $rider->fresh()->hub_transfer_only);
        $this->assertSame($company->id, $rider->fresh()->logistics_owner_id);
        $this->assertTrue(Hash::check('RiderPass@2026', $rider->fresh()->password));
        $this->assertDatabaseMissing('rider_activation_tokens', ['user_id' => $rider->id]);

        $this->post(route('rider.activation.store'), [
            'token' => $token,
            'password' => 'RiderPass@2026',
            'password_confirmation' => 'RiderPass@2026',
        ])->assertSessionHasErrors('token');

        $this->flushSession();
        Auth::forgetGuards();
        $this->actingAs($rider->fresh());
        $dashboard = $this->get(route('courier.dashboard'));
        if ($hubTransport) {
            $dashboard->assertRedirect(route('parcel-transfers.index'));
            $this->get(route('parcel-transfers.index'))->assertOk()->assertSee($label);
        } else {
            $dashboard->assertOk()->assertSee($label);
            $this->get(route('parcel-transfers.index'))->assertForbidden();
        }
        $this->get(route('courier.profile'))->assertOk()->assertSee($label);
    }

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['both'])]
    #[TestWith(['2'])]
    public function test_creation_requires_one_valid_explicit_duty_before_saving_documents_or_sending_activation(?string $duty): void
    {
        Storage::fake('private');
        Notification::fake();
        $company = $this->user('sorting_center', 'approved');
        $details = $this->riderDetails();
        if ($duty === null) {
            unset($details['hub_transfer_only']);
        } else {
            $details['hub_transfer_only'] = $duty;
        }

        $this->actingAs($company)->post(route('logistics.riders.store'), $details)
            ->assertRedirect()->assertSessionHasErrors('hub_transfer_only');
        $this->assertDatabaseMissing('users', ['email' => 'rider@example.test']);
        $this->assertDatabaseCount('rider_activation_tokens', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
        Notification::assertNothingSent();
    }

    public function test_a_logistics_company_cannot_resend_another_companys_rider_activation(): void
    {
        Storage::fake('private');
        Notification::fake();
        $owner = $this->user('sorting_center', 'approved');
        $otherCompany = $this->user('sorting_center', 'approved');
        $this->actingAs($owner)->post(route('logistics.riders.store'), $this->riderDetails())->assertRedirect();
        $rider = User::query()->where('role', 'courier')->firstOrFail();

        $this->flushSession();
        $this->actingAs($otherCompany)
            ->post(route('logistics.riders.activation.resend', $rider))
            ->assertNotFound();
    }

    public function test_non_logistics_accounts_cannot_add_riders(): void
    {
        $buyer = $this->user('buyer', 'approved');
        $this->actingAs($buyer)->post(route('logistics.riders.store'), [])->assertForbidden();
    }

    public function test_invalid_or_expired_activation_links_do_not_expose_duty_or_hub_details(): void
    {
        $this->get(route('rider.activation.show', str_repeat('x', 64)))->assertOk()
            ->assertSee('This activation link is invalid')->assertDontSee('Assigned duty:')->assertDontSee('Home hub:');

        $rider = $this->user('courier', 'pending_activation');
        $token = str_repeat('y', 64);
        DB::table('rider_activation_tokens')->insert(['user_id' => $rider->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->subMinute(), 'created_at' => now(), 'updated_at' => now()]);
        $this->get(route('rider.activation.show', $token))->assertOk()
            ->assertSee('This activation link has expired')->assertDontSee('Assigned duty:')->assertDontSee('Home hub:');
    }

    private function user(string $role, string $status): User
    {
        static $number = 0;
        $number++;

        return User::query()->forceCreate([
            'first_name' => 'Test',
            'last_name' => 'Account',
            'email' => "{$role}{$number}@example.test",
            'password' => Hash::make('Password@2026'),
            'contact_no' => '091700000'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            'role' => $role,
            'status' => $status,
            'province' => 'Laguna',
            'municipality' => 'Santa Cruz',
            'barangay' => 'Poblacion',
            'street_address' => '1 Test Road',
        ]);
    }

    private function riderDetails(): array
    {
        return [
            'first_name' => 'Pedro José',
            'last_name' => 'García',
            'birthday' => now()->subYears(30)->toDateString(),
            'email' => 'rider@example.test',
            'contact_no' => '+639451239876',
            'license_number' => 'N01-23-456789',
            'license_expires_at' => now()->addYears(4)->toDateString(),
            'vehicle_type' => 'Motorcycle',
            'hub_transfer_only' => '0',
            'plate_number' => '123ABC',
            'license_document' => UploadedFile::fake()->image('license.jpg'),
            'or_cr_document' => UploadedFile::fake()->create('vehicle.pdf', 120, 'application/pdf'),
        ];
    }
}
