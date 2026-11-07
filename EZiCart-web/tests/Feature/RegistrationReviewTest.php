<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('private');
    }

    public function test_marketplace_admin_can_open_seller_details_and_legacy_document_fields(): void
    {
        $admin = $this->user('ecommerce_admin');
        $seller = $this->user('seller', ['status' => 'pending', 'business_name' => 'Review Store', 'tin' => '345-678-901-000', 'birthday' => '1995-02-03', 'municipality' => 'Majayjay', 'id_upload_path' => 'documents/legacy-id.pdf', 'business_permit_path' => 'documents/legacy-permit.pdf']);
        Storage::disk('private')->put('documents/legacy-id.pdf', "%PDF-1.4\nIdentity document\n%%EOF");
        Storage::disk('private')->put('documents/legacy-permit.pdf', "%PDF-1.4\nBusiness permit\n%%EOF");

        $this->actingAs($admin)->get(route('admin.registrations.index'))->assertOk()->assertSee(route('admin.registrations.show', $seller), false);
        $this->get(route('admin.registrations.show', $seller))->assertOk()
            ->assertSee('Review Store')->assertSee('345-678-901-000')->assertSee('03 Feb 1995')->assertSee('Majayjay')
            ->assertSee('View document')->assertSee('Business permit')->assertDontSee($seller->password);
        $this->get(route('admin.users.documents.show', [$seller, 'identity', 'preview' => 1]))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $download = $this->get(route('admin.users.documents.show', [$seller, 'permit']));
        $download->assertDownload('legacy-permit.pdf');
    }

    public function test_logistics_admin_can_review_company_certificates_and_rider_papers(): void
    {
        $admin = $this->user('logistics_admin');
        $hub = $this->user('sorting_center', ['status' => 'pending', 'business_name' => 'Review Logistics', 'sec_registration_no' => 'CS123456789', 'contact_position' => 'Operations manager', 'service_areas' => ['Majayjay'], 'sec_certificate_path' => 'documents/sec.pdf', 'bir_certificate_path' => 'documents/bir.pdf', 'representative_id_path' => 'documents/representative.pdf']);
        $rider = $this->user('courier', ['status' => 'pending', 'license_number' => 'A01-12-345678', 'license_expires_at' => '2030-01-01', 'plate_number' => 'ABC1234', 'license_path' => 'documents/license.pdf', 'or_cr_upload_path' => 'documents/vehicle.pdf']);
        foreach (['sec', 'bir', 'representative', 'license', 'vehicle'] as $type) {
            Storage::disk('private')->put('documents/'.$type.'.pdf', "%PDF-1.4\n{$type}\n%%EOF");
        }
        $this->actingAs($admin)->get(route('logistics-admin.dashboard'))->assertOk()->assertSee(route('logistics-admin.applications.show', $hub), false);
        $this->get(route('logistics-admin.applications.show', $hub))->assertOk()->assertSee('CS123456789')->assertSee('Operations manager')->assertSee('Majayjay')->assertSee('SEC certificate')->assertSee('BIR Form 2303')->assertSee('Representative ID');
        foreach (['sec', 'bir', 'representative'] as $type) {
            $this->get(route('logistics-admin.users.documents', [$hub, $type, 'preview' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
        $this->get(route('logistics-admin.applications.show', $rider))->assertOk()->assertSee('A01-12-345678')->assertSee('ABC1234')->assertSee('Vehicle OR/CR');
        $this->get(route('logistics-admin.users.documents', [$rider, 'vehicle']))->assertDownload('vehicle.pdf');
    }

    public function test_admins_cannot_review_or_download_documents_outside_their_domain(): void
    {
        $commerce = $this->user('ecommerce_admin');
        $logistics = $this->user('logistics_admin');
        $seller = $this->user('seller');
        $rider = $this->user('courier');
        $this->actingAs($commerce)->get(route('admin.registrations.show', $rider))->assertForbidden();
        $this->get(route('admin.users.documents.show', [$rider, 'identity']))->assertForbidden();
        $this->flushSession();
        $this->actingAs($logistics)->get(route('logistics-admin.applications.show', $seller))->assertForbidden();
        $this->get(route('logistics-admin.users.documents', [$seller, 'identity']))->assertForbidden();
        $this->flushSession();
        $this->actingAs($seller)->get(route('admin.registrations.show', $seller))->assertForbidden();
    }

    public function test_missing_documents_and_unavailable_files_are_reported_without_broken_links(): void
    {
        $seller = $this->user('seller', ['status' => 'pending', 'permit_path' => 'documents/missing.pdf']);
        $this->actingAs($this->user('admin'))->get(route('admin.registrations.show', $seller))->assertOk()
            ->assertSee('Not submitted')->assertSee('File unavailable')->assertDontSee('View document');
        $this->get(route('admin.users.documents.show', [$seller, 'permit', 'preview' => 1]))->assertNotFound();
        $this->get(route('admin.users.documents.show', [$seller, 'unknown']))->assertNotFound();
    }

    public function test_review_requires_authentication_and_escapes_applicant_content(): void
    {
        $seller = $this->user('seller', ['business_name' => '<script>alert(1)</script>']);
        $this->get(route('admin.registrations.show', $seller))->assertRedirect(route('login'));
        $this->actingAs($this->user('ecommerce_admin'))->get(route('admin.registrations.show', $seller))
            ->assertOk()->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_non_previewable_documents_download_instead_of_rendering_active_content(): void
    {
        $seller = $this->user('seller', ['id_path' => 'documents/unsafe.html']);
        Storage::disk('private')->put('documents/unsafe.html', '<!DOCTYPE html><script>alert(1)</script>');
        $this->actingAs($this->user('ecommerce_admin'))->get(route('admin.users.documents.show', [$seller, 'identity', 'preview' => 1]))
            ->assertDownload('unsafe.html')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_image_documents_preview_inline_and_existing_legacy_copies_remain_accessible(): void
    {
        $path = 'documents/identity.png';
        Storage::disk('private')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aMZkAAAAASUVORK5CYII='));
        $seller = $this->user('seller', ['id_path' => 'documents/stale.png', 'id_upload_path' => $path]);
        $response = $this->actingAs($this->user('ecommerce_admin'))->get(route('admin.users.documents.show', [$seller, 'identity', 'preview' => 1]));
        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
    }

    private function user(string $role, array $attributes = []): User
    {
        return User::forceCreate(array_merge(['first_name' => 'Application', 'last_name' => 'Reviewer', 'email' => fake()->unique()->safeEmail(), 'contact_no' => '09123456789', 'password' => 'Password123!', 'role' => $role, 'status' => 'approved'], $attributes));
    }
}
