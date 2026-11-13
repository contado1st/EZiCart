<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BuyerEmailChangeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartialAccountIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_profile_rejects_invalid_identity_without_saving_and_does_not_require_an_unrelated_address_edit(): void
    {
        $buyer = $this->buyer();
        $this->actingAs($buyer)->patch(route('account.profile.update'), ['first_name' => 'A', 'last_name' => 'B', 'contact_no' => 'NOT-A-MOBILE'])
            ->assertSessionHasErrors(['first_name', 'last_name', 'contact_no']);
        $this->assertSame('Original', $buyer->fresh()->first_name);
        $this->patch(route('account.profile.update'), ['first_name' => 'Updated', 'last_name' => 'Buyer', 'contact_no' => '+639123456789'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Updated', $buyer->fresh()->first_name);
    }

    public function test_clearing_a_profile_location_is_blocked_but_unchanged_legacy_location_can_be_kept(): void
    {
        $buyer = $this->buyer(['province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Roque', 'street_address' => '10 Road']);
        $profile = ['first_name' => 'Original', 'last_name' => 'Buyer', 'contact_no' => '09123456789'];
        $this->actingAs($buyer)->patch(route('account.profile.update'), [...$profile, 'street_address' => ''])->assertSessionHasErrors('street_address');
        $this->assertSame('10 Road', $buyer->fresh()->street_address);
        $this->patch(route('account.profile.update'), [...$profile, 'street_address' => '10 Road'])->assertSessionHasNoErrors();
    }

    public function test_saved_address_requires_street_without_requiring_a_house_number(): void
    {
        $buyer = $this->buyer();
        $address = ['label' => 'Home', 'recipient_name' => 'Original Buyer', 'recipient_contact' => '09123456789',
            'address_mode' => 'manual', 'manual_region' => 'CALABARZON', 'manual_province' => 'Laguna',
            'manual_municipality' => 'Majayjay', 'manual_barangay' => 'San Roque'];
        $this->actingAs($buyer)->post(route('buyer.addresses.store'), $address)->assertSessionHasErrors('street_address');
        $this->assertDatabaseCount('buyer_addresses', 0);
        $this->post(route('buyer.addresses.store'), [...$address, 'street_address' => 'Farm beside the barangay hall'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('buyer_addresses', ['user_id' => $buyer->id, 'house_number' => null, 'street_address' => 'Farm beside the barangay hall']);
    }

    public function test_password_reuse_is_rejected_before_session_or_password_changes(): void
    {
        $buyer = $this->buyer();
        $hash = $buyer->password;
        $this->actingAs($buyer)->patch(route('account.password.update'), ['current_password' => 'Password123!', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])
            ->assertSessionHasErrors(['password' => 'Choose a password different from your current password.']);
        $this->assertSame($hash, $buyer->fresh()->password);
        $this->assertAuthenticatedAs($buyer);
    }

    public function test_profile_photo_accepts_three_megabytes_and_keeps_private_reencoded_storage(): void
    {
        Storage::fake('private');
        $buyer = $this->buyer();
        $this->actingAs($buyer)->patch(route('account.appearance.update'), ['profile_theme' => 'rose', 'avatar' => UploadedFile::fake()->image('photo.png', 200, 200)->size(3000)])
            ->assertSessionHasNoErrors();
        Storage::disk('private')->assertExists($buyer->fresh()->profile_avatar_path);
        $this->assertSame('image/jpeg', getimagesizefromstring(Storage::disk('private')->get($buyer->fresh()->profile_avatar_path))['mime']);
    }

    public function test_new_email_remains_pending_until_verified_and_then_changes_once(): void
    {
        $buyer = $this->buyer();
        $oldEmail = $buyer->email;
        $code = $this->requestCode($buyer);
        $this->assertSame($oldEmail, $buyer->fresh()->email);
        $this->assertTrue(Hash::check($code, DB::table('buyer_email_changes')->value('code_hash')));
        $this->patch(route('account.email-change.update'), ['email_code' => $code])->assertSessionHasNoErrors()->assertSessionHas('web_auth_version', 1);
        $this->assertSame('new-buyer@example.com', $buyer->fresh()->email);
        $this->assertSame(1, (int) $buyer->fresh()->web_auth_version);
        $this->assertDatabaseCount('buyer_email_changes', 0);
        Notification::assertSentOnDemand(BuyerEmailChangeNotification::class, fn ($notice, $channels, $notifiable) => $notice->code === null && $notifiable->routes['mail'] === $oldEmail);
        $this->patch(route('account.email-change.update'), ['email_code' => $code])->assertSessionHasErrors('email_code');
    }

    public function test_case_insensitive_existing_email_is_rejected_without_sending_or_flashing_password(): void
    {
        Notification::fake();
        $buyer = $this->buyer();
        $this->buyer(['email' => 'Taken@Example.com', 'role' => 'seller']);
        $this->actingAs($buyer)->post(route('account.email-change.store'), ['new_email' => ' TAKEN@example.com ', 'email_password' => 'Password123!'])
            ->assertSessionHasErrors('new_email')->assertSessionMissing('_old_input.email_password');
        $this->assertDatabaseCount('buyer_email_changes', 0);
        Notification::assertNothingSent();
    }

    public function test_wrong_password_cannot_start_email_change_and_roles_cannot_bypass_the_route(): void
    {
        Notification::fake();
        $buyer = $this->buyer();
        $this->post(route('account.email-change.store'), [])->assertRedirectToRoute('login');
        $this->actingAs($buyer)->post(route('account.email-change.store'), ['new_email' => 'new@example.com', 'email_password' => 'Wrong123!'])
            ->assertSessionHasErrors('email_password')->assertSessionMissing('_old_input.email_password');
        $this->flushSession();
        $this->actingAs($this->buyer(['role' => 'seller']))->post(route('account.email-change.store'), ['new_email' => 'new@example.com', 'email_password' => 'Password123!'])->assertForbidden();
        $this->assertDatabaseCount('buyer_email_changes', 0);
        Notification::assertNothingSent();
    }

    public function test_wrong_codes_persist_attempts_and_lock_after_five(): void
    {
        $buyer = $this->buyer();
        $code = $this->requestCode($buyer);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->patch(route('account.email-change.update'), ['email_code' => '000000'])->assertSessionHasErrors('email_code');
        }
        $this->assertSame(5, (int) DB::table('buyer_email_changes')->value('attempts'));
        $this->patch(route('account.email-change.update'), ['email_code' => $code])->assertSessionHasErrors('email_code');
        $this->assertSame($buyer->email, $buyer->fresh()->email);
    }

    public function test_resend_cooldown_and_expiry_do_not_change_identity(): void
    {
        $this->freezeTime();
        $buyer = $this->buyer();
        $code = $this->requestCode($buyer);
        $this->post(route('account.email-change.store'), ['new_email' => 'another@example.com', 'email_password' => 'Password123!'])->assertSessionHasErrors('new_email');
        $this->travel(10)->minutes();
        $this->patch(route('account.email-change.update'), ['email_code' => $code])->assertSessionHasErrors('email_code');
        $this->assertSame($buyer->email, $buyer->fresh()->email);
    }

    public function test_password_change_invalidates_an_existing_email_challenge(): void
    {
        $buyer = $this->buyer();
        $code = $this->requestCode($buyer);
        $buyer->forceFill(['password' => 'Changed123!'])->save();
        $this->patch(route('account.email-change.update'), ['email_code' => $code])->assertRedirectToRoute('login');
        $this->post(route('login.post'), ['email' => $buyer->email, 'password' => 'Changed123!'])->assertSessionHasNoErrors();
        $this->patch(route('account.email-change.update'), ['email_code' => $code])->assertSessionHasErrors('email_code');
        $this->assertDatabaseCount('buyer_email_changes', 0);
    }

    public function test_address_claimed_after_sending_code_is_rechecked_at_confirmation(): void
    {
        $buyer = $this->buyer();
        $code = $this->requestCode($buyer);
        $this->buyer(['email' => 'new-buyer@example.com']);
        $this->patch(route('account.email-change.update'), ['email_code' => $code])->assertSessionHasErrors('email_code');
        $this->assertSame($buyer->email, $buyer->fresh()->email);
    }

    public function test_resending_replaces_the_challenge_and_another_buyer_cannot_use_it(): void
    {
        $this->freezeTime();
        $buyer = $this->buyer();
        $oldCode = $this->requestCode($buyer);
        $reference = DB::table('buyer_email_changes')->value('reference');
        $this->travel(61)->seconds();
        $newCode = $this->requestCode($buyer);
        $newReference = DB::table('buyer_email_changes')->value('reference');
        $this->assertNotSame($reference, $newReference);
        $this->assertDatabaseCount('buyer_email_changes', 1);
        // Codes can randomly coincide; replacement is proved by the new reference.
        if ($oldCode !== $newCode) {
            $this->patch(route('account.email-change.update'), ['email_code' => $oldCode])->assertSessionHasErrors('email_code');
        }
        $this->flushSession();
        $other = $this->buyer();
        $this->actingAs($other)->patch(route('account.email-change.update'), ['email_code' => $newCode])->assertSessionHasErrors('email_code');
        $this->assertSame($other->email, $other->fresh()->email);
        $this->assertDatabaseHas('buyer_email_changes', ['user_id' => $buyer->id, 'reference' => $newReference]);
    }

    public function test_security_version_rejects_old_buyer_sessions_but_does_not_affect_other_accounts_or_new_logins(): void
    {
        $buyer = $this->buyer(['web_auth_version' => 1]);
        $this->actingAs($buyer)->withSession(['web_auth_version' => 0])->get(route('account.profile.edit'))->assertRedirectToRoute('login');
        $other = $this->buyer();
        $this->actingAs($other)->get(route('account.profile.edit'))->assertOk();
        $this->post(route('logout'))->assertRedirect();
        $this->post(route('login.post'), ['email' => $buyer->email, 'password' => 'Password123!'])->assertSessionHas('web_auth_version', 1);
        $this->get(route('account.profile.edit'))->assertOk();
    }

    private function requestCode(User $buyer): string
    {
        Notification::fake();
        $this->actingAs($buyer)->post(route('account.email-change.store'), ['new_email' => 'NEW-BUYER@example.com', 'email_password' => 'Password123!'])->assertSessionHasNoErrors();
        $code = '';
        Notification::assertSentOnDemand(BuyerEmailChangeNotification::class, function ($notice, $channels, $notifiable) use (&$code): bool {
            $code = $notice->code;

            return $notifiable->routes['mail'] === 'new-buyer@example.com';
        });

        return $code;
    }

    private function buyer(array $extra = []): User
    {
        return User::forceCreate([...['first_name' => 'Original', 'last_name' => 'Buyer', 'email' => fake()->unique()->safeEmail(),
            'password' => 'Password123!', 'contact_no' => '09123456789', 'role' => 'buyer', 'status' => 'approved', 'email_verified_at' => now()], ...$extra]);
    }
}
