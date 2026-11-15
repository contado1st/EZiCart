<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ImageUploadProcessor;
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

    public function test_authorized_staff_id_has_a_profile_qr_without_changing_the_rider_badge(): void
    {
        $this->withoutVite();
        $admin = $this->user('logistics_admin');
        $rider = $this->user('courier');
        $rider->forceFill(['badge_code' => 'existing-operational-badge'])->save();
        $this->actingAs($admin)->get(route('staff.show', $rider))->assertOk()
            ->assertSee('data-staff-id-card', false)->assertSee('EZI-'.str_pad((string) $rider->id, 8, '0', STR_PAD_LEFT))
            ->assertSee('Profile QR')->assertSee('Update history');
        $this->assertSame('existing-operational-badge', $rider->fresh()->badge_code);
        $this->flushSession();
        Auth::forgetGuards();
        $this->actingAs($this->user('buyer'))->get(route('staff.show', $rider))->assertForbidden();
    }

    private function user(string $role): User
    {
        return User::forceCreate(['first_name' => 'Example', 'last_name' => ucfirst($role), 'role' => $role, 'status' => 'approved',
            'email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'contact_no' => '09'.fake()->unique()->numerify('#########')]);
    }
}
