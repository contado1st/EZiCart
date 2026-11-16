<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotificationChannels;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_passes_for_an_empty_balanced_environment_without_writes(): void
    {
        $this->artisan('operations:readiness', ['--json' => true])->expectsOutputToContain('"ready": true')->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_failed_jobs_are_diagnosed_without_execution_deletion_or_exposing_payloads(): void
    {
        foreach ([Crypt::encryptString('PRIVATE-DO-NOT-PRINT'), 'unreadable-old-encrypted-payload'] as $command) {
            DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
                'payload' => json_encode(['displayName' => DeliverNotificationChannels::class, 'data' => ['command' => $command]], JSON_THROW_ON_ERROR),
                'exception' => 'Historical exception', 'failed_at' => now(),
            ]);
        }

        $this->assertSame(1, Artisan::call('operations:readiness', ['--json' => true]));
        $output = Artisan::output();
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['decryptable_requires_review', 'unreadable_or_legacy_payload'], array_column($report['issues']['failed_notifications']['jobs'], 'payload_state'));
        $this->assertStringNotContainsString('PRIVATE-DO-NOT-PRINT', $output);
        $this->assertDatabaseCount('failed_jobs', 2);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_missing_license_and_private_documents_are_reported_with_internal_ids(): void
    {
        Storage::fake('private');
        $rider = $this->user('courier');

        $this->assertSame(1, Artisan::call('operations:readiness', ['--json' => true]));
        $output = Artisan::output();
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$rider->id], $report['issues']['rider_licenses']['user_ids']);
        $this->assertSame(['identity', 'license', 'vehicle'], $report['issues']['private_documents']['accounts'][0]['types']);
        $this->assertStringNotContainsString($rider->email, $output);
        $this->assertNull($rider->fresh()->license_expires_at);
        $this->assertDatabaseCount('administrative_audits', 0);
    }

    public function test_staff_document_readiness_and_license_warning_remain_in_the_admin_domain(): void
    {
        $this->withoutVite();
        Storage::fake('private');
        Storage::disk('private')->put('identity/fixture.png', 'Synthetic private document');
        $rider = $this->user('courier', ['id_path' => 'identity/fixture.png', 'license_path' => 'missing/license.png']);
        $admin = $this->user('logistics_admin');

        $this->actingAs($admin)->get(route('staff.show', $rider))->assertOk()
            ->assertSee('License expiry date missing')->assertSee('File unavailable; resubmission required')
            ->assertSee(route('logistics-admin.users.documents', ['user' => $rider, 'type' => 'identity', 'preview' => 1]), false)
            ->assertDontSee('Synthetic private document');
        $this->flushSession();
        $this->actingAs($this->user('ecommerce_admin'))->get(route('staff.show', $rider))->assertForbidden();
        $this->flushSession();
        $this->actingAs($this->user('buyer'))->get(route('staff.show', $rider))->assertForbidden();
    }

    private function user(string $role, array $extra = []): User
    {
        return User::forceCreate(['role' => $role, 'status' => 'approved', 'first_name' => 'Readiness', 'last_name' => 'Fixture',
            'email' => Str::uuid().'@example.test', 'password' => 'Password123!', 'email_verified_at' => now(), 'contact_no' => '09123456789', ...$extra,
        ]);
    }
}
