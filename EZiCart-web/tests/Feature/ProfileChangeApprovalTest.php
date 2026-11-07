<?php

namespace Tests\Feature;

use App\Models\StaffProfileRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileChangeApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('private');
    }

    public function test_seller_contact_and_business_changes_wait_for_marketplace_admin_approval(): void
    {
        $seller = $this->user('seller');
        $commerce = $this->user('ecommerce_admin');
        $logistics = $this->user('logistics_admin');
        $this->signIn($seller)->patch(route('account.profile.update'), ['first_name' => 'Newname', 'last_name' => $seller->last_name, 'contact_no' => '09998887776', 'business_name' => 'Requested shop', 'role' => 'admin', 'status' => 'approved'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $revision = StaffProfileRevision::firstOrFail();
        $this->assertSame('Original', $seller->fresh()->first_name);
        $this->assertSame('Original shop', $seller->business_name);
        $this->assertSame('pending', $revision->status);
        $this->assertSame(1, $commerce->notifications()->count());
        $this->assertSame(0, $logistics->notifications()->count());
        $this->get(route('account.profile.edit'))->assertOk()->assertSee('Profile change requests')->assertSee('Newname');
        $this->signIn($logistics)->get(route('logistics-admin.profile-reviews.index'))->assertOk()->assertDontSee('Requested shop');
        $this->patch(route('logistics-admin.profile-reviews.review', $revision), ['decision' => 'approve'])->assertForbidden();
        $this->signIn($commerce)->get(route('admin.profile-reviews.index'))->assertOk()->assertSee('Requested shop')->assertSee('09998887776');
        $this->patch(route('admin.profile-reviews.review', $revision), ['decision' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $seller->refresh();
        $this->assertSame('Newname', $seller->first_name);
        $this->assertSame('09998887776', $seller->canonical_mobile);
        $this->assertSame('Requested shop', $seller->business_name);
        $this->assertSame('seller', $seller->role);
        $this->assertSame('approved', $revision->fresh()->status);
        $this->assertNull($revision->fresh()->pending_user_id);
    }

    public function test_rider_contact_request_is_visible_only_to_logistics_administration(): void
    {
        $rider = $this->user('courier');
        $logistics = $this->user('logistics_admin');
        $commerce = $this->user('admin');
        $this->signIn($rider)->patch(route('account.profile.update'), ['first_name' => 'Ridername', 'last_name' => $rider->last_name, 'contact_no' => '09998887775', 'plate_number' => 'NEW 456'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $revision = StaffProfileRevision::firstOrFail();
        $this->assertNull($rider->fresh()->plate_number);
        $this->signIn($commerce)->get(route('admin.profile-reviews.index'))->assertOk()->assertDontSee('NEW 456');
        $this->patch(route('admin.profile-reviews.review', $revision), ['decision' => 'approve'])->assertForbidden();
        $this->signIn($logistics)->patch(route('logistics-admin.profile-reviews.review', $revision), ['decision' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $rider->refresh();
        $this->assertSame('NEW 456', $rider->plate_number);
        $this->assertSame('NEW456', $rider->canonical_plate);
        $this->assertSame('Ridername', $rider->first_name);
    }

    public function test_staff_pages_are_admin_only_and_not_linked_from_non_admin_sidebars(): void
    {
        foreach (['buyer', 'seller', 'courier', 'sorting_center'] as $role) {
            $user = $this->user($role);
            $this->signIn($user)->get(route('staff.index'))->assertForbidden();
            $this->get(route('staff.show', $user))->assertForbidden();
            $this->get(route('staff.reviews'))->assertForbidden();
            $this->get(route('account.profile.edit'))->assertOk()->assertDontSee('title="Staff profiles"', false);
        }
        $this->signIn($this->user('logistics_admin'))->get(route('staff.index'))->assertOk();
        $this->signIn($this->user('ecommerce_admin'))->get(route('staff.index'))->assertOk();
    }

    public function test_seller_images_and_theme_remain_unchanged_until_the_right_admin_approves(): void
    {
        $seller = $this->user('seller');
        $old = UploadedFile::fake()->image('old.jpg', 200, 200)->store('profile-media', 'private');
        $seller->forceFill(['profile_avatar_path' => $old])->save();
        $this->signIn($seller)->patch(route('account.appearance.update'), ['profile_display_name' => 'Requested name', 'profile_theme' => 'wine', 'profile_bio' => 'Requested introduction', 'avatar' => UploadedFile::fake()->image('new.png', 200, 200), 'banner' => UploadedFile::fake()->image('banner.png', 900, 300)])
            ->assertRedirect()->assertSessionHasNoErrors();
        $revision = StaffProfileRevision::firstOrFail();
        $new = $revision->proposed_values['profile_avatar_path'];
        $this->assertSame($old, $seller->fresh()->profile_avatar_path);
        $this->assertNull($seller->profile_banner_path);
        $this->get(route('account.profile-requests.media', [$revision, 'avatar']))->assertOk();
        $this->signIn($this->user('logistics_admin'))->get(route('account.profile-requests.media', [$revision, 'avatar']))->assertForbidden();
        $this->signIn($this->user('buyer'))->get(route('account.profile-requests.media', [$revision, 'banner']))->assertForbidden();
        $this->signIn($this->user('ecommerce_admin'))->get(route('account.profile-requests.media', [$revision, 'avatar', 'version' => 'before']))->assertOk();
        $this->patch(route('admin.profile-reviews.review', $revision), ['decision' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $seller->refresh();
        $this->assertSame($new, $seller->profile_avatar_path);
        $this->assertSame('wine', $seller->profile_theme);
        $this->assertSame('Requested name', $seller->profile_display_name);
        Storage::disk('private')->assertExists($seller->profile_banner_path);
    }

    public function test_rejected_rider_appearance_keeps_verified_profile_and_can_be_resubmitted(): void
    {
        $rider = $this->user('courier');
        $admin = $this->user('logistics_admin');
        $this->signIn($rider)->patch(route('account.appearance.update'), ['profile_theme' => 'wine', 'profile_display_name' => 'Requested rider', 'avatar' => UploadedFile::fake()->image('avatar.png', 200, 200)])->assertRedirect()->assertSessionHasNoErrors();
        $revision = StaffProfileRevision::firstOrFail();
        $this->assertNull($rider->fresh()->profile_avatar_path);
        $this->signIn($admin)->patch(route('logistics-admin.profile-reviews.review', $revision), ['decision' => 'reject'])->assertSessionHasErrors('reason');
        $this->patch(route('logistics-admin.profile-reviews.review', $revision), ['decision' => 'reject', 'reason' => 'Please provide a clearer photo.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($rider->fresh()->profile_avatar_path);
        $this->assertSame('rejected', $revision->fresh()->status);
        $this->signIn($rider)->get(route('account.profile.edit'))->assertOk()->assertSee('Please provide a clearer photo.');
        $this->patch(route('account.appearance.update'), ['profile_theme' => 'navy'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, StaffProfileRevision::where('user_id', $rider->id)->count());
    }

    public function test_duplicate_contacts_and_conflicting_or_repeated_approvals_are_rejected(): void
    {
        $seller = $this->user('seller');
        $this->user('buyer', ['contact_no' => '09998887774', 'canonical_mobile' => '09998887774']);
        $payload = ['first_name' => 'Changed', 'last_name' => $seller->last_name, 'business_name' => $seller->business_name, 'contact_no' => '+639998887774'];
        $this->signIn($seller)->patch(route('account.profile.update'), $payload)->assertSessionHasErrors('canonical_mobile');
        $this->assertSame(0, StaffProfileRevision::count());
        $payload['contact_no'] = '09998887773';
        $this->patch(route('account.profile.update'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $revision = StaffProfileRevision::firstOrFail();
        $this->patch(route('account.profile.update'), $payload)->assertSessionHasErrors('profile');
        $seller->forceFill(['first_name' => 'Concurrent'])->save();
        $this->signIn($this->user('ecommerce_admin'))->patch(route('admin.profile-reviews.review', $revision), ['decision' => 'approve'])->assertSessionHasErrors('review');
        $this->assertSame('Concurrent', $seller->fresh()->first_name);
        $this->patch(route('admin.profile-reviews.review', $revision), ['decision' => 'reject', 'reason' => 'Details changed; please resubmit.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('admin.profile-reviews.review', $revision), ['decision' => 'approve'])->assertSessionHasErrors('review');
    }

    public function test_a_second_request_does_not_leave_uploaded_files_or_replace_a_pending_request(): void
    {
        $seller = $this->user('seller');
        $this->signIn($seller)->patch(route('account.profile.update'), ['first_name' => 'Requested', 'last_name' => $seller->last_name, 'business_name' => $seller->business_name, 'contact_no' => $seller->contact_no])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('account.appearance.update'), ['profile_theme' => 'wine', 'avatar' => UploadedFile::fake()->image('pending.png', 200, 200)])->assertSessionHasErrors('profile');
        $this->assertSame(1, StaffProfileRevision::count());
        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->assertNull($seller->fresh()->profile_avatar_path);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function user(string $role, array $attributes = []): User
    {
        return User::forceCreate(array_merge(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'first_name' => 'Original', 'last_name' => 'User', 'role' => $role, 'status' => 'approved', 'contact_no' => '09'.fake()->unique()->numerify('#########'), 'business_name' => 'Original shop'], $attributes));
    }
}
