<?php

namespace Tests\Feature;

use App\Models\ProductDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicantDraftWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_verified_pending_seller_gets_only_a_draft_capability_and_can_keep_private_work(): void
    {
        Storage::fake('private');
        $seller = $this->applicant();
        $this->post(route('login.post'), ['email' => $seller->email, 'password' => 'Password123!'])->assertRedirectToRoute('applicant-drafts.index');
        $this->assertGuest();
        $this->get(route('applicant-drafts.index'))->assertOk()->assertSee('Seller application under review');
        $this->get(route('applicant-drafts.create'))->assertOk()->assertSee('Submission available after approval');
        $this->post(route('applicant-drafts.store'), ['name' => 'Pending seller private draft', 'image' => UploadedFile::fake()->image('cover.png')])->assertSessionHasNoErrors();
        $draft = ProductDraft::sole();
        $this->assertSame($seller->id, $draft->user_id);
        $this->get(route('applicant-drafts.edit', $draft))->assertOk()->assertSee('Pending seller private draft');
        $this->get(route('applicant-drafts.media', [$draft, 'image']))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('products', 0);
        $this->assertGuest();
        $this->post(route('seller.products.store'), ['draft_id' => $draft->id])->assertRedirectToRoute('login');
        foreach (['seller.products.index', 'seller.orders.index', 'finance.wallet', 'chat.index'] as $route) {
            $this->get(route($route))->assertRedirectToRoute('login');
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_another_applicant_cannot_access_media_edit_delete_or_submit_someone_elses_draft(): void
    {
        Storage::fake('private');
        $a = $this->applicant();
        $this->post(route('login.post'), ['email' => $a->email, 'password' => 'Password123!']);
        $this->post(route('applicant-drafts.store'), ['name' => 'Owner draft', 'image' => UploadedFile::fake()->image('owner.png')]);
        $draft = ProductDraft::sole();
        $b = $this->applicant();
        $this->post(route('login.post'), ['email' => $b->email, 'password' => 'Password123!']);
        $this->get(route('applicant-drafts.edit', $draft))->assertNotFound();
        $this->get(route('applicant-drafts.media', [$draft, 'image']))->assertNotFound();
        $this->post(route('applicant-drafts.update', $draft), ['name' => 'Hijack'])->assertNotFound();
        $this->delete(route('applicant-drafts.destroy', $draft))->assertNotFound();
        $this->assertSame('Owner draft', $draft->fresh()->payload['name']);
    }

    public function test_expiry_and_tab_binding_revoke_the_capability_without_losing_saved_work(): void
    {
        $this->freezeTime();
        $seller = $this->applicant();
        $this->post(route('login.post'), ['email' => $seller->email, 'password' => 'Password123!']);
        $this->post(route('applicant-drafts.store'), ['name' => 'Retained draft']);
        $this->travel(30)->minutes();
        $this->get(route('applicant-drafts.index'))->assertRedirectToRoute('login');
        $this->assertDatabaseCount('product_drafts', 1);
        $this->assertGuest();
        $this->post(route('login.post'), ['email' => $seller->email, 'password' => 'Password123!']);
        $this->withHeader('X-Ezi-Tab-Session', str_repeat('a', 32))->get(route('applicant-drafts.index'))->assertRedirect();
    }

    public function test_status_or_password_change_revokes_access_and_approval_preserves_drafts_for_normal_login(): void
    {
        $seller = $this->applicant();
        $this->post(route('login.post'), ['email' => $seller->email, 'password' => 'Password123!']);
        $this->post(route('applicant-drafts.store'), ['name' => 'Prepared for review']);
        $seller->forceFill(['password' => 'Changed123!'])->save();
        $this->get(route('applicant-drafts.index'))->assertRedirectToRoute('login');
        $this->post(route('login.post'), ['email' => $seller->email, 'password' => 'Changed123!']);
        $seller->forceFill(['status' => 'approved'])->save();
        $this->get(route('applicant-drafts.index'))->assertRedirectToRoute('login');
        $this->assertGuest();
        $this->post(route('login.post'), ['email' => $seller->email, 'password' => 'Changed123!'])->assertRedirectToRoute('seller.dashboard');
        $this->get(route('seller.product-drafts.index'))->assertOk()->assertSee('Prepared for review');
        $this->assertFalse(session()->has('applicant_drafts'));
    }

    public function test_unverified_suspended_and_other_pending_roles_cannot_obtain_an_applicant_capability(): void
    {
        foreach ([['seller', 'unverified'], ['seller', 'suspended'], ['sorting_center', 'pending']] as [$role, $status]) {
            $user = $this->applicant(['role' => $role, 'status' => $status]);
            $this->post(route('login.post'), ['email' => $user->email, 'password' => 'Password123!'])->assertSessionMissing('applicant_drafts');
            $this->get(route('applicant-drafts.index'))->assertRedirectToRoute('login');
        }
        $this->assertDatabaseCount('product_drafts', 0);
    }

    public function test_logout_discards_the_capability_and_an_approved_login_cannot_inherit_it(): void
    {
        $user = $this->applicant();
        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'Password123!']);
        $this->post(route('logout'))->assertRedirect();
        $this->get(route('applicant-drafts.index'))->assertRedirectToRoute('login');
        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'Password123!']);
        $other = $this->applicant(['status' => 'approved', 'role' => 'buyer']);
        $this->post(route('login.post'), ['email' => $other->email, 'password' => 'Password123!'])->assertSessionMissing('applicant_drafts');
        $this->assertAuthenticatedAs($other);
        $this->get(route('applicant-drafts.index'))->assertRedirectToRoute('home');
    }

    private function applicant(array $extra = []): User
    {
        return User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'first_name' => 'Pending', 'last_name' => 'Seller', 'contact_no' => '09123456789', 'business_name' => 'Applicant shop', 'role' => 'seller', 'status' => 'pending', 'email_verified_at' => now(), ...$extra]);
    }
}
