<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\RiderActivationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RiderActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_approved_logistics_can_add_a_rider_with_private_documents_and_activation_link(): void
    {
        Storage::fake('private');
        Notification::fake();
        $company = $this->user('sorting_center', 'approved');

        $this->actingAs($company)->post(route('logistics.riders.store'), $this->riderDetails())
            ->assertRedirect();

        $rider = User::query()->where('email', 'rider@example.test')->firstOrFail();
        $this->assertSame('courier', $rider->role);
        $this->assertSame('pending_activation', $rider->status);
        $this->assertSame($company->id, $rider->logistics_owner_id);
        $this->assertNotNull($rider->license_path);
        $this->assertNotNull($rider->or_cr_path);
        Storage::disk('private')->assertExists($rider->license_path);
        Storage::disk('private')->assertExists($rider->or_cr_path);
        $this->assertDatabaseHas('rider_activation_tokens', ['user_id' => $rider->id]);
        Notification::assertSentTo($rider, RiderActivationNotification::class);
    }

    public function test_activation_link_is_single_use_and_sets_a_compliant_password(): void
    {
        Storage::fake('private');
        Notification::fake();
        $company = $this->user('sorting_center', 'approved');
        $this->actingAs($company)->post(route('logistics.riders.store'), $this->riderDetails())->assertRedirect();
        $rider = User::query()->where('role', 'courier')->firstOrFail();
        $token = str_repeat('a', 64);
        DB::table('rider_activation_tokens')->where('user_id', $rider->id)->update(['token_hash' => hash('sha256', $token)]);

        $this->get(route('rider.activation.show', ['token' => $token]))->assertOk()->assertSee('Activate your rider account');
        $this->post(route('rider.activation.store'), [
            'token' => $token,
            'password' => 'RiderPass@2026',
            'password_confirmation' => 'RiderPass@2026',
        ])->assertRedirect(route('login'));
        $this->assertSame('approved', $rider->fresh()->status);
        $this->assertTrue(Hash::check('RiderPass@2026', $rider->fresh()->password));
        $this->assertDatabaseMissing('rider_activation_tokens', ['user_id' => $rider->id]);

        $this->post(route('rider.activation.store'), [
            'token' => $token,
            'password' => 'RiderPass@2026',
            'password_confirmation' => 'RiderPass@2026',
        ])->assertSessionHasErrors('token');
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
            'plate_number' => '123ABC',
            'license_document' => UploadedFile::fake()->image('license.jpg'),
            'or_cr_document' => UploadedFile::fake()->create('vehicle.pdf', 120, 'application/pdf'),
        ];
    }
}
