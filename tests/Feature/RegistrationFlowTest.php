<?php

namespace Tests\Feature;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_buyer_registration_creates_unverified_account_and_sends_verification_notification(): void
    {
        Notification::fake();

        $this->post(route('register.post'), $this->buyerDetails([
            'first_name' => 'José Miguel',
            'email' => '  BUYER@example.com  ',
            'contact_no' => '+639171234567',
        ]))->assertRedirect(route('verification.notice', ['email' => 'buyer@example.com']));

        $user = User::query()->where('email', 'buyer@example.com')->firstOrFail();
        $this->assertSame('buyer', $user->role);
        $this->assertSame('unverified', $user->status);
        $this->assertSame('09171234567', $user->contact_no);
        $this->assertSame($user->contact_no, $user->canonical_mobile);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertNotNull($user->privacy_accepted_at);
        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id]);
        $this->assertDatabaseCount('users', 1);
        Notification::assertSentTo($user, EmailVerificationCodeNotification::class);
    }

    public function test_a_valid_otp_activates_buyer_and_can_only_be_used_once(): void
    {
        Notification::fake();
        $this->post(route('register.post'), $this->buyerDetails())->assertRedirect();
        $user = User::query()->firstOrFail();
        EmailVerificationCode::query()->where('user_id', $user->id)->update([
            'code_hash' => Hash::make('482916'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $payload = ['email' => $user->email, 'code' => '482916'];
        $this->post(route('verification.verify'), $payload)
            ->assertRedirect(route('login'));
        $this->assertSame('approved', $user->fresh()->status);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);

        $this->post(route('verification.verify'), $payload)->assertSessionHasErrors('code');
    }

    public function test_expired_or_incorrect_otp_does_not_activate_account(): void
    {
        Notification::fake();
        $this->post(route('register.post'), $this->buyerDetails())->assertRedirect();
        $user = User::query()->firstOrFail();
        EmailVerificationCode::query()->where('user_id', $user->id)->update([
            'code_hash' => Hash::make('482916'),
            'expires_at' => now()->subSecond(),
        ]);

        $this->post(route('verification.verify'), ['email' => $user->email, 'code' => '482916'])
            ->assertSessionHasErrors('code');
        $this->assertSame('unverified', $user->fresh()->status);
    }

    public function test_login_redirects_unverified_accounts_to_email_verification_and_denies_session(): void
    {
        $user = User::query()->forceCreate([
            'first_name' => 'Pending',
            'last_name' => 'Buyer',
            'contact_no' => '09281234567',
            'email' => 'pending@example.com',
            'password' => Hash::make('Password@2026'),
            'role' => 'buyer',
            'status' => 'unverified',
        ]);

        $this->post(route('login.post'), ['email' => ' PENDING@EXAMPLE.COM ', 'password' => 'Password@2026'])
            ->assertRedirect(route('verification.notice', ['email' => $user->email]));
        $this->assertGuest();
    }

    public function test_email_and_mobile_are_unique_after_normalization(): void
    {
        Notification::fake();
        $this->post(route('register.post'), $this->buyerDetails())->assertRedirect();

        $this->post(route('register.post'), $this->buyerDetails([
            'first_name' => 'Ana Marie',
            'email' => ' BUYER@example.com ',
            'contact_no' => '+639171234567',
        ]))->assertSessionHasErrors(['email', 'contact_no']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_buyer_registration_enforces_age_password_and_consent(): void
    {
        $this->from(route('register'))
            ->post(route('register.post'), $this->buyerDetails([
                'birthday' => now()->subYears(17)->toDateString(),
                'password' => 'short',
                'password_confirmation' => 'different',
                'terms' => '0',
            ]))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['birthday', 'password', 'terms']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_public_rider_registration_is_not_available(): void
    {
        $this->get('/register/courier')->assertNotFound();
        $this->post('/register/courier')->assertNotFound();
    }

    public function test_seller_registration_stores_required_private_documents_and_waits_for_verification(): void
    {
        Notification::fake();
        Storage::fake('private');

        $this->post(route('register.seller.post'), [
            'first_name' => 'Maria Clara',
            'last_name' => 'Santos',
            'email' => 'seller@example.com',
            'contact_no' => '09189876543',
            'shop_name' => 'Maria Clara Crafts',
            'line_of_business' => 'Home & Living',
            'seller_type' => 'Corporation',
            'tin' => '123-456-789-000',
            'region' => 'National Capital Region (NCR)',
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Commonwealth',
            'street_address' => 'Blk 12 Lot 8',
            'postal_code' => '1121',
            'id_document' => UploadedFile::fake()->image('id.jpg'),
            'bir_form_2303' => UploadedFile::fake()->create('bir.pdf', 500, 'application/pdf'),
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 500, 'application/pdf'),
            'password' => 'Seller-Pass@2026',
            'password_confirmation' => 'Seller-Pass@2026',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice', ['email' => 'seller@example.com']));

        $seller = User::query()->where('email', 'seller@example.com')->firstOrFail();
        $this->assertSame('unverified', $seller->status);
        Storage::disk('private')->assertExists($seller->id_path);
        Storage::disk('private')->assertExists($seller->sec_certificate_path);
        Storage::disk('private')->assertExists($seller->bir_certificate_path);
        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $seller->id]);
        Notification::assertSentTo($seller, EmailVerificationCodeNotification::class);
    }

    public function test_corporate_seller_cannot_register_without_sec_certificate(): void
    {
        Storage::fake('private');
        $this->from(route('register.seller'))
            ->post(route('register.seller.post'), [
                'first_name' => 'Maria Clara', 'last_name' => 'Santos',
                'email' => 'seller@example.com', 'contact_no' => '09189876543',
                'shop_name' => 'Maria Clara Crafts', 'line_of_business' => 'Home & Living',
                'seller_type' => 'Corporation', 'tin' => '123-456-789-000',
                'region' => 'National Capital Region (NCR)', 'province' => 'Metro Manila',
                'municipality' => 'Quezon City', 'barangay' => 'Commonwealth',
                'street_address' => 'Blk 12 Lot 8', 'postal_code' => '1121',
                'id_document' => UploadedFile::fake()->image('id.jpg'),
                'bir_form_2303' => UploadedFile::fake()->create('bir.pdf', 500, 'application/pdf'),
                'password' => 'Seller-Pass@2026', 'password_confirmation' => 'Seller-Pass@2026', 'terms' => '1',
            ])
            ->assertRedirect(route('register.seller'))
            ->assertSessionHasErrors('sec_certificate');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_logistics_registration_accepts_multiple_regions_and_keeps_documents_private(): void
    {
        Notification::fake();
        Storage::fake('private');

        $this->post(route('register.sorting.post'), [
            'first_name' => 'Carlos Antonio', 'last_name' => 'Mendoza',
            'email' => 'operations@example.com', 'contact_no' => '09285551234',
            'business_name' => 'Bilis Hatid Express Inc.', 'sec_registration_no' => 'CS202012345',
            'tin' => '234-567-890-000', 'region' => 'National Capital Region (NCR)',
            'province' => 'Metro Manila', 'municipality' => 'Pasig City', 'barangay' => 'Kapitolyo',
            'street_address' => 'Unit 5B, 88 West Capitol Drive', 'postal_code' => '1603',
            'contact_position' => 'Operations Manager',
            'service_areas' => ['National Capital Region (NCR)', 'Region IV-A (CALABARZON)'],
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 500, 'application/pdf'),
            'bir_form_2303' => UploadedFile::fake()->create('bir.pdf', 500, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 500, 'application/pdf'),
            'representative_id' => UploadedFile::fake()->image('rep.jpg'),
            'password' => 'Logistics-Pass@2026', 'password_confirmation' => 'Logistics-Pass@2026',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice', ['email' => 'operations@example.com']));

        $company = User::query()->where('email', 'operations@example.com')->firstOrFail();
        $this->assertSame('sorting_center', $company->role);
        $this->assertSame('unverified', $company->status);
        $this->assertSame(['National Capital Region (NCR)', 'Region IV-A (CALABARZON)'], $company->service_areas);
        Storage::disk('private')->assertExists($company->sec_certificate_path);
        Storage::disk('private')->assertExists($company->bir_certificate_path);
        Storage::disk('private')->assertExists($company->permit_path);
        Storage::disk('private')->assertExists($company->representative_id_path);
        Notification::assertSentTo($company, EmailVerificationCodeNotification::class);
    }

    public function test_rejected_seller_can_correct_and_resubmit_the_application(): void
    {
        Storage::fake('private');
        $seller = User::query()->forceCreate([
            'first_name' => 'Maria Clara', 'last_name' => 'Santos',
            'email' => 'seller@example.com', 'canonical_email' => 'seller@example.com',
            'contact_no' => '09189876543', 'canonical_mobile' => '09189876543',
            'password' => Hash::make('Seller-Pass@2026'), 'role' => 'seller', 'status' => 'rejected',
            'shop_name' => 'Maria Clara Crafts', 'business_name' => 'Maria Clara Crafts',
            'line_of_business' => 'Home & Living', 'seller_type' => 'Individual', 'tin' => '123-456-789-000',
            'region' => 'National Capital Region (NCR)', 'province' => 'Metro Manila',
            'municipality' => 'Quezon City', 'barangay' => 'Commonwealth',
            'street_address' => 'Blk 12 Lot 8', 'postal_code' => '1121',
            'id_path' => 'documents/old-id.pdf', 'rejection_reason' => 'Upload a clearer government ID.',
        ]);

        $this->actingAs($seller)->get(route('application.resubmit'))
            ->assertOk()
            ->assertSee('Upload a clearer government ID.')
            ->assertSee('Maria Clara Crafts');

        $this->post(route('logout'));
        $this->post(route('login.post'), ['email' => $seller->email, 'password' => 'Seller-Pass@2026'])
            ->assertRedirect(route('application.resubmit'));

        $this->post(route('application.resubmit.post'), [
            'first_name' => 'Maria Clara', 'last_name' => 'Santos',
            'email' => 'seller@example.com', 'contact_no' => '09189876543',
            'shop_name' => 'Maria Clara Crafts Updated', 'line_of_business' => 'Home & Living',
            'seller_type' => 'Individual', 'tin' => '123-456-789-000',
            'region' => 'National Capital Region (NCR)', 'province' => 'Metro Manila',
            'municipality' => 'Quezon City', 'barangay' => 'Commonwealth',
            'street_address' => 'Blk 12 Lot 8', 'postal_code' => '1121',
            'id_document' => UploadedFile::fake()->image('updated-id.jpg'),
            'password' => 'Seller-Pass@2026', 'password_confirmation' => 'Seller-Pass@2026', 'terms' => '1',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'id' => $seller->id,
            'shop_name' => 'Maria Clara Crafts Updated',
            'status' => 'pending',
            'rejection_reason' => null,
        ]);
        Storage::disk('private')->assertExists($seller->fresh()->id_path);
    }

    public function test_rejected_logistics_company_can_correct_and_resubmit_the_application(): void
    {
        Storage::fake('private');
        $company = User::query()->forceCreate([
            'first_name' => 'Carlos Antonio', 'last_name' => 'Mendoza',
            'email' => 'operations@example.com', 'canonical_email' => 'operations@example.com',
            'contact_no' => '09285551234', 'canonical_mobile' => '09285551234',
            'password' => Hash::make('Logistics-Pass@2026'), 'role' => 'sorting_center', 'status' => 'rejected',
            'business_name' => 'Bilis Hatid Express Inc.', 'canonical_company_name' => 'bilis hatid express inc.',
            'sec_registration_no' => 'CS202012345', 'tin' => '234-567-890-000',
            'region' => 'National Capital Region (NCR)', 'province' => 'Metro Manila',
            'municipality' => 'Pasig City', 'barangay' => 'Kapitolyo',
            'street_address' => 'Unit 5B, 88 West Capitol Drive', 'postal_code' => '1603',
            'contact_position' => 'Operations Manager',
            'service_areas' => ['National Capital Region (NCR)'],
            'rejection_reason' => 'Upload a current business permit.',
        ]);

        $this->actingAs($company)->post(route('application.resubmit.post'), [
            'first_name' => 'Carlos Antonio', 'last_name' => 'Mendoza',
            'email' => 'operations@example.com', 'contact_no' => '09285551234',
            'business_name' => 'Bilis Hatid Express Inc.', 'sec_registration_no' => 'CS202012345',
            'tin' => '234-567-890-000', 'region' => 'National Capital Region (NCR)',
            'province' => 'Metro Manila', 'municipality' => 'Pasig City', 'barangay' => 'Kapitolyo',
            'street_address' => 'Unit 5B, 88 West Capitol Drive', 'postal_code' => '1603',
            'contact_position' => 'Operations Manager',
            'service_areas' => ['National Capital Region (NCR)', 'Region IV-A (CALABARZON)'],
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 500, 'application/pdf'),
            'bir_form_2303' => UploadedFile::fake()->create('bir.pdf', 500, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 500, 'application/pdf'),
            'representative_id' => UploadedFile::fake()->image('rep.jpg'),
            'terms' => '1',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $company->id,
            'status' => 'pending',
            'rejection_reason' => null,
        ]);
        $this->assertSame(['National Capital Region (NCR)', 'Region IV-A (CALABARZON)'], $company->fresh()->service_areas);
    }

    private function buyerDetails(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz',
            'email' => 'buyer@example.com',
            'contact_no' => '09171234567',
            'birthday' => now()->subYears(28)->toDateString(),
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => '1',
        ], $overrides);
    }
}
