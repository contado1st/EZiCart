<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordChangeCodeNotification;
use App\Services\PasswordChangeService;
use App\Services\ProductUploadProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SystemRevisionBriefTest extends TestCase
{
    use RefreshDatabase;

    private function account(): User
    {
        return User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'first_name' => 'Brief', 'last_name' => 'Fixture', 'role' => 'buyer', 'status' => 'approved', 'contact_no' => '09123456789']);
    }

    private function requestCode(User $user): string
    {
        Notification::fake();
        app(PasswordChangeService::class)->request($user, 'Password123!');

        return Notification::sent($user, PasswordChangeCodeNotification::class)->sole()->code;
    }

    public function test_password_change_requires_registered_email_code_and_consumes_it_once(): void
    {
        $this->withoutVite();
        $user = $this->account();
        $this->actingAs($user)->patch(route('account.password.update'), ['current_password' => 'Password123!', 'password' => 'New-Password123!', 'password_confirmation' => 'New-Password123!'])->assertSessionHasErrors('password_code');
        $code = $this->requestCode($user);
        $row = DB::table('password_change_challenges')->sole();
        $this->assertTrue(Hash::check($code, $row->code_hash));
        $this->assertSame($user->email, $row->email);
        $this->assertStringNotContainsString($code, json_encode($row));
        $this->patch(route('account.password.update'), ['current_password' => 'Password123!', 'password_code' => $code, 'password' => 'New-Password123!', 'password_confirmation' => 'New-Password123!'])->assertSessionHas('success');
        $this->assertTrue(Hash::check('New-Password123!', $user->fresh()->password));
        $this->assertDatabaseCount('password_change_challenges', 0);
        $this->expectException(ValidationException::class);
        app(PasswordChangeService::class)->change($user->fresh(), $code, 'New-Password123!', 'Another-Password123!');
    }

    public function test_wrong_attempts_persist_and_exhausted_code_cannot_change_password(): void
    {
        $user = $this->account();
        $this->actingAs($user);
        $code = $this->requestCode($user);
        for ($i = 0; $i < 5; $i++) {
            try {
                app(PasswordChangeService::class)->change($user, '000000', 'Password123!', 'New-Password123!');
                $this->fail('Wrong code accepted.');
            } catch (ValidationException) {
                $this->assertSame($i + 1, DB::table('password_change_challenges')->value('attempts'));
            }
        }
        $this->expectException(ValidationException::class);
        app(PasswordChangeService::class)->change($user, $code, 'Password123!', 'New-Password123!');
    }

    public function test_expired_code_is_rejected_at_exact_deadline(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->account();
        $this->actingAs($user);
        $code = $this->requestCode($user);
        $this->travel(config('account-security.password_otp_minutes'))->minutes();
        $this->expectException(ValidationException::class);
        app(PasswordChangeService::class)->change($user, $code, 'Password123!', 'New-Password123!');
    }

    public function test_resend_cooldown_preserves_the_challenge(): void
    {
        $user = $this->account();
        $this->requestCode($user);
        $reference = DB::table('password_change_challenges')->value('reference');
        try {
            app(PasswordChangeService::class)->request($user, 'Password123!');
            $this->fail('Cooldown was bypassed.');
        } catch (ValidationException) {
            $this->assertSame($reference, DB::table('password_change_challenges')->value('reference'));
        }
        $this->travel(60)->seconds();
        app(PasswordChangeService::class)->request($user, 'Password123!');
        $this->assertNotSame($reference, DB::table('password_change_challenges')->value('reference'));
    }

    public function test_code_is_invalid_after_email_or_password_changes(): void
    {
        $user = $this->account();
        $this->actingAs($user);
        $code = $this->requestCode($user);
        $user->forceFill(['email' => 'changed@example.test'])->save();
        $this->expectException(ValidationException::class);
        app(PasswordChangeService::class)->change($user, $code, 'Password123!', 'New-Password123!');
    }

    public function test_logging_mail_transport_cannot_expose_password_codes(): void
    {
        config(['mail.default' => 'log', 'services.gmail_api.client_id' => null]);
        $this->expectException(ValidationException::class);
        app(PasswordChangeService::class)->request($this->account(), 'Password123!');
    }

    public function test_invalid_code_is_not_flashed_back_to_the_browser(): void
    {
        $user = $this->account();
        $this->actingAs($user);
        $this->requestCode($user);
        $this->patch(route('account.password.update'), ['current_password' => 'Password123!', 'password_code' => '000000', 'password' => 'New-Password123!', 'password_confirmation' => 'New-Password123!'])
            ->assertSessionHasErrors('password_code')->assertSessionMissing('_old_input.password_code');
        $this->assertTrue(Hash::check('Password123!', $user->fresh()->password));
    }

    public function test_email_delivery_failure_invalidates_the_pending_challenge(): void
    {
        config(['mail.default' => 'smtp', 'services.gmail_api.client_id' => null]);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Sensitive sender exception must not be reported.'));
        try {
            app(PasswordChangeService::class)->request($this->account(), 'Password123!');
            $this->fail('Failed delivery was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('password_change_challenges', 0);
        }
    }

    public function test_wide_and_portrait_product_images_fit_720p_without_stretching(): void
    {
        Storage::fake('public');
        foreach ([[2560, 1440, 1280, 720], [1200, 2400, 360, 720], [320, 240, 320, 240]] as [$width, $height, $expectedWidth, $expectedHeight]) {
            $path = app(ProductUploadProcessor::class)->image(UploadedFile::fake()->image('product.png', $width, $height), 'products');
            $size = getimagesize(Storage::disk('public')->path($path));
            $this->assertSame([$expectedWidth, $expectedHeight], array_slice($size, 0, 2));
        }
    }

    public function test_invalid_image_bytes_are_rejected_without_stored_files(): void
    {
        Storage::fake('public');
        try {
            app(ProductUploadProcessor::class)->image(UploadedFile::fake()->createWithContent('fake.jpg', '<?php invalid ?>'), 'products');
            $this->fail('Invalid image accepted.');
        } catch (ValidationException) {
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_real_short_video_is_verified_and_optimized_as_mp4(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->createWithContent('short.mp4', file_get_contents(base_path('tests/Fixtures/media/product-short.mp4')))->mimeType('video/mp4');
        $path = app(ProductUploadProcessor::class)->video($file, 'products');
        $this->assertStringEndsWith('.mp4', $path);
        $info = app(ProductUploadProcessor::class)->probe(Storage::disk('public')->path($path));
        $this->assertLessThanOrEqual(60, (float) $info['format']['duration']);
    }

    public function test_invalid_video_and_missing_probe_are_rejected(): void
    {
        config(['product-media.ffprobe_path' => base_path('missing-ffprobe')]);
        $this->expectException(ValidationException::class);
        app(ProductUploadProcessor::class)->probe(base_path('tests/Fixtures/media/product-short.mp4'));
    }

    public function test_video_longer_than_one_minute_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(ProductUploadProcessor::class)->probe(base_path('tests/Fixtures/media/product-long.mp4'));
    }
}
