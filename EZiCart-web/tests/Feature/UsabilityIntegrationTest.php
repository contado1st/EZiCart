<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ImageUploadProcessor;
use App\Services\QrCodeService;
use App\Services\RiderBadgeService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UsabilityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_has_one_floating_inbox_entry_and_shared_feedback_for_each_role(): void
    {
        $this->withoutVite();
        foreach (['buyer', 'seller', 'courier', 'sorting_center', 'ecommerce_admin', 'logistics_admin'] as $role) {
            $user = $this->user($role);
            $this->flushSession();
            Auth::forgetGuards();
            $this->actingAs($user);
            $response = $this->get(route('account.profile.edit'))->assertOk();
            $dom = new DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new DOMXPath($dom);
            $this->assertCount(0, $xpath->query('//header[contains(@class,"workspace-topbar")]//*[@data-message-drawer-toggle]'));
            $this->assertCount(1, $xpath->query('//a[contains(@class,"message-drawer-launcher")][@href]'));
            $this->assertCount(1, $xpath->query('//*[@data-action-feedback-region]'));
        }
    }

    public function test_review_display_images_are_resized_without_cropping_or_public_storage(): void
    {
        Storage::fake('private');
        $file = UploadedFile::fake()->image('review.jpg', 2400, 1200);
        $path = app(ImageUploadProcessor::class)->image($file, 'reviews/photos', 'private', 'photos', 1600, 1600, 16777216);
        $bytes = Storage::disk('private')->get($path);
        $dimensions = getimagesizefromstring($bytes);
        $this->assertSame(1600, $dimensions[0]);
        $this->assertSame(800, $dimensions[1]);
        $this->assertLessThan(filesize($file->getPathname()), strlen($bytes));
        $this->assertStringStartsWith('reviews/photos/', $path);
    }

    public function test_authorized_staff_id_uses_the_existing_rider_badge_without_rotating_it(): void
    {
        $this->withoutVite();
        $admin = $this->user('logistics_admin');
        $rider = $this->user('courier');
        $rider->forceFill(['badge_code' => 'existing-operational-badge'])->save();
        $this->actingAs($admin)->get(route('staff.show', $rider))->assertOk()
            ->assertSee('data-staff-id-card', false)->assertSee('EZI-'.str_pad((string) $rider->id, 8, '0', STR_PAD_LEFT))
            ->assertSee('Rider badge QR')->assertSee('Update history')
            ->assertViewHas('qrSvg', app(QrCodeService::class)->svg('EZR:existing-operational-badge'));
        $this->assertSame('existing-operational-badge', $rider->fresh()->badge_code);
        $this->flushSession();
        Auth::forgetGuards();
        $this->actingAs($this->user('buyer'))->get(route('staff.show', $rider))->assertForbidden();
    }

    public function test_staff_profile_and_sorting_center_use_the_same_new_and_rotated_rider_badge(): void
    {
        $this->withoutVite();
        $admin = $this->user('logistics_admin');
        $hub = $this->user('sorting_center');
        $rider = $this->user('courier');
        $rider->forceFill(['logistics_owner_id' => $hub->id])->save();

        $profile = $this->actingAs($admin)->get(route('staff.show', $rider))->assertOk();
        $firstBadge = $rider->fresh()->badge_code;
        $this->assertNotEmpty($firstBadge);
        $profile->assertViewHas('qrSvg', app(QrCodeService::class)->svg('EZR:'.$firstBadge));

        $this->flushSession();
        Auth::forgetGuards();
        $listing = $this->actingAs($hub)->get(route('logistics.riders'))->assertOk()->assertSee('EZR:'.$firstBadge);
        $listedRider = $listing->viewData('riders')->getCollection()->firstWhere('id', $rider->id);
        $this->assertNotNull($listedRider);
        $this->assertSame($firstBadge, $listedRider->badge_code);
        $this->assertSame(app(QrCodeService::class)->svg('EZR:'.$firstBadge, 120), $listedRider->badge_qr);

        $rotatedBadge = app(RiderBadgeService::class)->rotate($rider->fresh());
        $this->assertNotSame($firstBadge, $rotatedBadge);
        $this->flushSession();
        Auth::forgetGuards();
        $this->actingAs($admin)->get(route('staff.show', $rider))->assertOk()
            ->assertViewHas('qrSvg', app(QrCodeService::class)->svg('EZR:'.$rotatedBadge));
        $this->flushSession();
        Auth::forgetGuards();
        $this->actingAs($hub)->get(route('logistics.riders'))->assertOk()
            ->assertSee('EZR:'.$rotatedBadge)->assertDontSee('EZR:'.$firstBadge);
    }

    public function test_non_rider_staff_keeps_the_profile_link_qr(): void
    {
        $this->withoutVite();
        $admin = $this->user('ecommerce_admin');
        $seller = $this->user('seller');

        $this->actingAs($admin)->get(route('staff.show', $seller))->assertOk()
            ->assertSee('Profile QR')->assertDontSee('Rider badge QR')
            ->assertViewHas('qrSvg', app(QrCodeService::class)->svg(url('/staff/'.$seller->id)));
        $this->assertNull($seller->fresh()->badge_code);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['first_name' => 'Example', 'last_name' => ucfirst($role), 'role' => $role, 'status' => 'approved',
            'email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'contact_no' => '09'.fake()->unique()->numerify('#########')]);
    }
}
