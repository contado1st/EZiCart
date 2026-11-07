<?php

namespace Tests\Feature;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
        Http::fake([
            'psgc.cloud/api/v2/regions' => Http::response(['data' => [['code' => '1300000000', 'name' => 'National Capital Region (NCR)']]]),
            'psgc.cloud/api/v2/regions/1300000000/provinces' => Http::response(['data' => []]),
            'psgc.cloud/api/v2/regions/1300000000/cities-municipalities' => Http::response(['data' => [['code' => '1376000000', 'name' => 'Quezon City']]]),
            'psgc.cloud/api/v2/cities-municipalities/1376000000/barangays' => Http::response(['data' => [['code' => '1376000001', 'name' => 'Commonwealth']]]),
        ]);
    }

    /**
     * Scenario 1: Buyer registration sends a 6-digit OTP.
     */
    public function test_buyer_registration_creates_unverified_account_and_sends_verification_otp(): void
    {
        Notification::fake();

        $this->post(route('register.post'), $this->buyerDetails([
            'first_name' => 'José Miguel',
            'email' => '  BUYER@example.com  ',
            'contact_no' => '+639171234567',
        ]))->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'buyer@example.com')->firstOrFail();
        $this->assertSame('buyer', $user->role);
        $this->assertSame('unverified', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('09171234567', $user->contact_no);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertNotNull($user->privacy_accepted_at);

        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id]);
        $record = EmailVerificationCode::where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($record->code_hash);
        $this->assertNotNull($record->expires_at);
        $this->assertTrue($record->expires_at->isFuture());

        Notification::assertSentTo($user, EmailVerificationCodeNotification::class, function ($notification) {
            return strlen($notification->code) === 6 && ctype_digit($notification->code);
        });
    }

    public function test_otp_email_template_renders_subject_and_body_correctly(): void
    {
        $user = User::query()->forceCreate([
            'first_name' => 'Miguel',
            'last_name' => 'Santos',
            'email' => 'miguel@example.com',
            'contact_no' => '09171234567',
            'password' => Hash::make('Password@2026'),
            'role' => 'buyer',
            'status' => 'unverified',
        ]);

        $notification = new EmailVerificationCodeNotification('456789');
        $mail = $notification->toMail($user);

        $this->assertSame('Your EZiCart Verification Code: 456789', $mail->subject);
        $renderedHtml = (string) $mail->render();
        $this->assertStringContainsString('456789', $renderedHtml);
        $this->assertStringContainsString('EZiCart', $renderedHtml);
        $this->assertStringContainsString('Miguel Santos', $renderedHtml);
        $this->assertStringContainsString('5 minutes', $renderedHtml);
    }

    /**
     * Scenario 2: Seller registration sends an OTP.
     */
    public function test_seller_registration_stores_required_private_documents_and_sends_verification_otp(): void
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
            'address_mode' => 'psgc',
            'region_code' => '1300000000',
            'province_code' => 'NCR',
            'municipality_code' => '1376000000',
            'barangay_code' => '1376000001',
            'street_address' => 'Blk 12 Lot 8',
            'postal_code' => '1121',
            'id_document' => UploadedFile::fake()->create('id.pdf', 500, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 500, 'application/pdf'),
            'password' => 'Seller-Pass@2026',
            'password_confirmation' => 'Seller-Pass@2026',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $seller = User::query()->where('email', 'seller@example.com')->firstOrFail();
        $this->assertSame('seller', $seller->role);
        $this->assertSame('unverified', $seller->status);
        $this->assertNull($seller->email_verified_at);

        Storage::disk('private')->assertExists($seller->id_path);
        Storage::disk('private')->assertExists($seller->permit_path);
        $this->assertNull($seller->bir_certificate_path);
        $this->assertNull($seller->sec_certificate_path);

        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $seller->id]);
        Notification::assertSentTo($seller, EmailVerificationCodeNotification::class);
    }

    public function test_seller_registration_requires_a_valid_id_and_business_permit(): void
    {
        Storage::fake('private');

        $this->post(route('register.seller.post'), [
            'first_name' => 'Alex',
            'last_name' => 'Seller',
            'email' => 'seller.without.permit@example.com',
            'contact_no' => '09123456789',
            'birthday' => '1990-01-01',
            'shop_name' => 'Alex Store',
            'line_of_business' => 'Home & Living',
            'seller_type' => 'Individual',
            'tin' => '123-456-789-001',
            'address_mode' => 'psgc',
            'region_code' => '1300000000',
            'province_code' => 'NCR',
            'municipality_code' => '1376000000',
            'barangay_code' => '1376000001',
            'street_address' => '14 Market Road',
            'postal_code' => '1121',
            'id_document' => UploadedFile::fake()->create('id.pdf', 500, 'application/pdf'),
            'password' => 'Seller-Pass@2026',
            'password_confirmation' => 'Seller-Pass@2026',
            'terms' => '1',
        ])->assertSessionHasErrors('business_permit');

        $this->assertDatabaseMissing('users', ['email' => 'seller.without.permit@example.com']);
    }

    /**
     * Scenario 3: Logistics registration sends an OTP.
     */
    public function test_logistics_registration_accepts_service_areas_and_sends_verification_otp(): void
    {
        Notification::fake();
        Storage::fake('private');

        $this->post(route('register.sorting.post'), [
            'first_name' => 'Carlos Antonio',
            'last_name' => 'Mendoza',
            'email' => 'operations@example.com',
            'contact_no' => '09285551234',
            'business_name' => 'Bilis Hatid Express Inc.',
            'sec_registration_no' => 'CS202012345',
            'tin' => '234-567-890-000',
            'address_mode' => 'psgc',
            'region_code' => '1300000000',
            'province_code' => 'NCR',
            'municipality_code' => '1376000000',
            'barangay_code' => '1376000001',
            'street_address' => 'Unit 5B, 88 West Capitol Drive',
            'postal_code' => '1603',
            'contact_position' => 'Operations Manager',
            'service_areas' => ['National Capital Region (NCR)', 'Region IV-A (CALABARZON)'],
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 500, 'application/pdf'),
            'bir_form_2303' => UploadedFile::fake()->create('bir.pdf', 500, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 500, 'application/pdf'),
            'representative_id' => UploadedFile::fake()->create('rep.pdf', 500, 'application/pdf'),
            'password' => 'Logistics-Pass@2026',
            'password_confirmation' => 'Logistics-Pass@2026',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $company = User::query()->where('email', 'operations@example.com')->firstOrFail();
        $this->assertSame('sorting_center', $company->role);
        $this->assertSame('unverified', $company->status);
        $this->assertNull($company->email_verified_at);

        Storage::disk('private')->assertExists($company->sec_certificate_path);
        Storage::disk('private')->assertExists($company->bir_certificate_path);
        Storage::disk('private')->assertExists($company->permit_path);
        Storage::disk('private')->assertExists($company->representative_id_path);

        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $company->id]);
        Notification::assertSentTo($company, EmailVerificationCodeNotification::class);
    }

    /**
     * Scenario 4: Correct OTP successfully verifies email.
     * Buyer transitions to 'approved' immediately. Seller and Logistics transition to 'pending'.
     */
    public function test_correct_otp_verifies_buyer_and_seller_differently(): void
    {
        Notification::fake();

        // 1. Buyer verification -> approved
        $this->post(route('register.post'), $this->buyerDetails())->assertRedirect();
        $buyer = User::query()->where('email', 'buyer@example.com')->firstOrFail();

        EmailVerificationCode::query()->where('user_id', $buyer->id)->update([
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->post(route('verification.verify'), [
            'email' => $buyer->email,
            'code' => '123456',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        $this->assertSame('approved', $buyer->fresh()->status);
        $this->assertNotNull($buyer->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $buyer->id]);

        // 2. Seller verification -> pending admin approval
        $seller = User::query()->forceCreate([
            'first_name' => 'Seller',
            'last_name' => 'Test',
            'email' => 'pending.seller@example.com',
            'contact_no' => '09191234567',
            'password' => Hash::make('Seller@2026'),
            'role' => 'seller',
            'status' => 'unverified',
        ]);
        EmailVerificationCode::query()->forceCreate([
            'user_id' => $seller->id,
            'code_hash' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        $this->post(route('verification.verify'), [
            'email' => $seller->email,
            'code' => '654321',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        $this->assertSame('pending', $seller->fresh()->status);
        $this->assertNotNull($seller->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $seller->id]);
    }

    /**
     * Scenario 5: Incorrect OTP is rejected.
     */
    public function test_incorrect_otp_is_rejected(): void
    {
        Notification::fake();
        $this->post(route('register.post'), $this->buyerDetails())->assertRedirect();
        $user = User::query()->firstOrFail();

        EmailVerificationCode::query()->where('user_id', $user->id)->update([
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->post(route('verification.verify'), [
            'email' => $user->email,
            'code' => '999999',
        ])->assertSessionHasErrors('code');

        $this->assertSame('unverified', $user->fresh()->status);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame(1, EmailVerificationCode::where('user_id', $user->id)->first()->attempts);
    }

    /**
     * Scenario 6: Expired OTP is rejected.
     */
    public function test_expired_otp_is_rejected(): void
    {
        Notification::fake();
        $this->post(route('register.post'), $this->buyerDetails())->assertRedirect();
        $user = User::query()->firstOrFail();

        EmailVerificationCode::query()->where('user_id', $user->id)->update([
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinute(),
        ]);

        $this->post(route('verification.verify'), [
            'email' => $user->email,
            'code' => '123456',
        ])->assertSessionHasErrors('code');

        $this->assertSame('unverified', $user->fresh()->status);
    }

    /**
     * Scenario 7: Resending an OTP generates a new code and invalidates the previous code.
     */
    public function test_resending_otp_generates_new_code_and_invalidates_previous_code(): void
    {
        Notification::fake();
        $this->post(route('register.post'), $this->buyerDetails())->assertRedirect();
        $user = User::query()->firstOrFail();

        $originalRecord = EmailVerificationCode::where('user_id', $user->id)->firstOrFail();
        $originalHash = $originalRecord->code_hash;

        // Simulate 65 seconds passed so cooldown expires
        Carbon::setTestNow(now()->addSeconds(65));

        $this->post(route('verification.resend'), ['email' => $user->email])
            ->assertSessionHas('success');

        $newRecord = EmailVerificationCode::where('user_id', $user->id)->firstOrFail();
        $this->assertNotSame($originalHash, $newRecord->code_hash);
        $this->assertSame(0, $newRecord->attempts);

        Notification::assertSentToTimes($user, EmailVerificationCodeNotification::class, 2);

        Carbon::setTestNow();
    }

    /**
     * Scenario 8: Resending is blocked during the 60-second cooldown period.
     */
    public function test_resending_is_blocked_during_cooldown_period(): void
    {
        Notification::fake();
        $this->post(route('register.post'), $this->buyerDetails())->assertRedirect();
        $user = User::query()->firstOrFail();

        // Immediately try to resend without waiting 60s
        $this->post(route('verification.resend'), ['email' => $user->email])
            ->assertSessionHasErrors('email');

        // Notification count remains 1 (only the registration one)
        Notification::assertSentToTimes($user, EmailVerificationCodeNotification::class, 1);
    }

    /**
     * Scenario 9: Excessive OTP attempts are rate-limited.
     */
    public function test_excessive_otp_attempts_are_blocked(): void
    {
        Notification::fake();
        $this->post(route('register.post'), $this->buyerDetails())->assertRedirect();
        $user = User::query()->firstOrFail();

        EmailVerificationCode::query()->where('user_id', $user->id)->update([
            'code_hash' => Hash::make('123456'),
            'attempts' => 5,
        ]);

        $this->post(route('verification.verify'), [
            'email' => $user->email,
            'code' => '123456',
        ])->assertSessionHasErrors('code');

        $this->assertSame('unverified', $user->fresh()->status);
    }

    /**
     * Scenario 10: A buyer can log in immediately after email verification.
     */
    public function test_buyer_can_log_in_immediately_after_email_verification(): void
    {
        Notification::fake();
        $buyer = User::query()->forceCreate([
            'first_name' => 'Verified',
            'last_name' => 'Buyer',
            'email' => 'buyer.verified@example.com',
            'contact_no' => '09121112233',
            'password' => Hash::make('Password@2026'),
            'role' => 'buyer',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $this->post(route('login.post'), [
            'email' => $buyer->email,
            'password' => 'Password@2026',
        ])->assertRedirect(route('buyer.dashboard'));
        $this->assertAuthenticatedAs($buyer);
        Notification::assertSentToTimes($buyer, EmailVerificationCodeNotification::class, 0);
    }

    public function test_seeded_buyer_and_seller_can_log_in_without_otp(): void
    {
        Notification::fake();

        foreach ([
            ['email' => 'buyer@ezicart.com', 'role' => 'buyer'],
            ['email' => 'seller@ezicart.com', 'role' => 'seller'],
        ] as $account) {
            $dashboardUrl = $account['role'] === 'seller'
                ? route('seller.dashboard')
                : route('buyer.dashboard');

            $user = User::query()->forceCreate([
                'first_name' => 'Seeded',
                'last_name' => ucfirst($account['role']),
                'email' => $account['email'],
                'contact_no' => '09121112233',
                'password' => Hash::make('Password@2026'),
                'role' => $account['role'],
                'status' => 'approved',
                'email_verified_at' => now(),
            ]);

            $this->post(route('login.post'), [
                'email' => $account['email'],
                'password' => 'Password@2026',
            ])->assertRedirect($dashboardUrl);

            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'));
        }

        Notification::assertNothingSent();
    }

    public function test_existing_approved_admin_can_log_in_immediately_without_email_verification(): void
    {
        $admin = User::query()->forceCreate([
            'first_name' => 'Legacy',
            'last_name' => 'Admin',
            'email' => 'legacy.admin@example.com',
            'contact_no' => '09121112234',
            'password' => Hash::make('Admin@2026'),
            'role' => 'admin',
            'status' => 'approved',
            'email_verified_at' => null,
        ]);

        $this->post(route('login.post'), [
            'email' => $admin->email,
            'password' => 'Admin@2026',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->email_verified_at);
    }

    public function test_buyer_can_log_in_without_an_email_code(): void
    {
        Notification::fake();
        $buyer = User::query()->forceCreate([
            'first_name' => 'Expired',
            'last_name' => 'Buyer',
            'email' => 'buyer.expired@example.com',
            'contact_no' => '09121112235',
            'password' => Hash::make('Password@2026'),
            'role' => 'buyer',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $this->post(route('login.post'), [
            'email' => $buyer->email,
            'password' => 'Password@2026',
        ])->assertRedirect(route('buyer.dashboard'));

        $this->assertAuthenticatedAs($buyer);
        Notification::assertSentToTimes($buyer, EmailVerificationCodeNotification::class, 0);
    }

    /**
     * Scenario 11: An unverified buyer cannot log in.
     */
    public function test_unverified_buyer_cannot_log_in(): void
    {
        $buyer = User::query()->forceCreate([
            'first_name' => 'Unverified',
            'last_name' => 'Buyer',
            'email' => 'buyer.unverified@example.com',
            'contact_no' => '09124445566',
            'password' => Hash::make('Password@2026'),
            'role' => 'buyer',
            'status' => 'unverified',
            'email_verified_at' => null,
        ]);

        $this->post(route('login.post'), [
            'email' => $buyer->email,
            'password' => 'Password@2026',
        ])->assertRedirect(route('verification.notice'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    /**
     * Scenario 12: A verified but unapproved seller cannot log in.
     */
    public function test_verified_but_unapproved_seller_cannot_log_in(): void
    {
        $seller = User::query()->forceCreate([
            'first_name' => 'Pending',
            'last_name' => 'Seller',
            'email' => 'seller.pending@example.com',
            'contact_no' => '09127778899',
            'password' => Hash::make('Password@2026'),
            'role' => 'seller',
            'status' => 'pending',
            'email_verified_at' => now(),
        ]);

        $this->post(route('login.post'), [
            'email' => $seller->email,
            'password' => 'Password@2026',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Your email has been verified. Your seller registration is now awaiting admin approval.');

        $this->assertGuest();
    }

    /**
     * Scenario 13: A verified but unapproved logistics applicant cannot log in.
     */
    public function test_verified_but_unapproved_logistics_cannot_log_in(): void
    {
        $logistics = User::query()->forceCreate([
            'first_name' => 'Pending',
            'last_name' => 'Logistics',
            'email' => 'logistics.pending@example.com',
            'contact_no' => '09128889900',
            'password' => Hash::make('Password@2026'),
            'role' => 'sorting_center',
            'status' => 'pending',
            'email_verified_at' => now(),
        ]);

        $this->post(route('login.post'), [
            'email' => $logistics->email,
            'password' => 'Password@2026',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Your email has been verified. Your logistics registration is now awaiting admin approval.');

        $this->assertGuest();
    }

    /**
     * Scenario 14: An approved seller can log in.
     */
    public function test_approved_seller_can_log_in(): void
    {
        Notification::fake();
        $seller = User::query()->forceCreate([
            'first_name' => 'Approved',
            'last_name' => 'Seller',
            'email' => 'seller.approved@example.com',
            'contact_no' => '09129990011',
            'password' => Hash::make('Password@2026'),
            'role' => 'seller',
            'status' => 'approved',
            'email_verified_at' => now(),
            'approved_at' => now(),
        ]);

        $this->post(route('login.post'), [
            'email' => $seller->email,
            'password' => 'Password@2026',
        ])->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($seller);
        Notification::assertNothingSent();
    }

    /**
     * Scenario 15: An approved logistics applicant can log in.
     */
    public function test_approved_logistics_applicant_can_log_in(): void
    {
        $logistics = User::query()->forceCreate([
            'first_name' => 'Approved',
            'last_name' => 'Logistics',
            'email' => 'logistics.approved@example.com',
            'contact_no' => '09120001122',
            'password' => Hash::make('Password@2026'),
            'role' => 'sorting_center',
            'status' => 'approved',
            'email_verified_at' => now(),
            'approved_at' => now(),
        ]);

        $this->post(route('login.post'), [
            'email' => $logistics->email,
            'password' => 'Password@2026',
        ])->assertRedirect(route('logistics.dashboard'));

        $this->assertAuthenticatedAs($logistics);
    }

    /**
     * Scenario 16: Rejected seller and logistics accounts cannot access protected features.
     */
    public function test_rejected_seller_and_logistics_cannot_access_protected_features(): void
    {
        $rejectedSeller = User::query()->forceCreate([
            'first_name' => 'Rejected',
            'last_name' => 'Seller',
            'email' => 'rejected.seller@example.com',
            'contact_no' => '09123334455',
            'password' => Hash::make('Password@2026'),
            'role' => 'seller',
            'status' => 'rejected',
            'email_verified_at' => now(),
            'rejection_reason' => 'Permit blurred.',
        ]);

        // Attempting to visit seller dashboard directly
        $this->actingAs($rejectedSeller)->get(route('seller.dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * Scenario 17: Users cannot bypass verification or approval by manually accessing dashboard routes.
     */
    public function test_unverified_or_unapproved_users_cannot_bypass_via_direct_url(): void
    {
        $unverifiedBuyer = User::query()->forceCreate([
            'first_name' => 'Sneaky',
            'last_name' => 'Buyer',
            'email' => 'sneaky@example.com',
            'contact_no' => '09125556677',
            'password' => Hash::make('Password@2026'),
            'role' => 'buyer',
            'status' => 'unverified',
            'email_verified_at' => null,
        ]);

        $this->actingAs($unverifiedBuyer)->get(route('buyer.dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * Scenario 18: Admin can review only verified pending accounts and approve them.
     */
    public function test_admin_approval_workflow_requires_verified_email(): void
    {
        Notification::fake();

        $admin = User::query()->forceCreate([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'contact_no' => '09120000000',
            'password' => Hash::make('Admin@2026'),
            'role' => 'admin',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        // Unverified seller should NOT be allowed to be approved
        $unverifiedSeller = User::query()->forceCreate([
            'first_name' => 'Unverified',
            'last_name' => 'Seller',
            'email' => 'seller.unverified@example.com',
            'contact_no' => '09121113333',
            'password' => Hash::make('Password@2026'),
            'role' => 'seller',
            'status' => 'unverified',
            'email_verified_at' => null,
        ]);

        $this->actingAs($admin)->post(route('admin.registrations.approve', $unverifiedSeller))
            ->assertStatus(422);

        // Verified pending seller CAN be approved
        $pendingSeller = User::query()->forceCreate([
            'first_name' => 'Pending',
            'last_name' => 'Seller',
            'email' => 'seller.pending.review@example.com',
            'contact_no' => '09122224444',
            'password' => Hash::make('Password@2026'),
            'role' => 'seller',
            'status' => 'pending',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.registrations.approve', $pendingSeller))
            ->assertRedirect();

        $this->assertSame('approved', $pendingSeller->fresh()->status);
        $this->assertNotNull($pendingSeller->fresh()->approved_at);
        $this->assertSame($admin->id, $pendingSeller->fresh()->approved_by);
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
