<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateSensitiveDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_documents_move_to_private_storage_and_command_is_idempotent(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $path = 'documents/ids/identity.pdf';
        Storage::disk('public')->put($path, 'sensitive identity content');
        $this->user(['id_path' => $path]);

        $this->artisan('app:migrate-sensitive-documents')->assertExitCode(0);

        Storage::disk('private')->assertExists($path);
        Storage::disk('public')->assertMissing($path);

        Storage::disk('public')->put($path, 'sensitive identity content');

        $this->artisan('app:migrate-sensitive-documents')->assertExitCode(0);

        Storage::disk('private')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_migration_reports_failure_and_preserves_public_file_when_private_copy_conflicts(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $path = 'documents/ids/identity.pdf';
        Storage::disk('public')->put($path, 'public identity content');
        Storage::disk('private')->put($path, 'different private content');
        $this->user(['id_path' => $path]);

        $this->artisan('app:migrate-sensitive-documents')->assertExitCode(1);

        Storage::disk('public')->assertExists($path);
        Storage::disk('private')->assertExists($path);
    }

    public function test_migration_reports_missing_referenced_files_as_failure(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $this->user(['id_path' => 'documents/ids/missing.pdf']);

        $this->artisan('app:migrate-sensitive-documents')->assertExitCode(1);
    }

    public function test_migration_moves_dispute_evidence_to_private_storage(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $path = 'disputes/evidence/receipt.pdf';
        Storage::disk('public')->put($path, 'dispute evidence');
        $buyer = $this->user([]);
        $seller = $this->user(['role' => 'seller']);
        $order = Order::query()->forceCreate([
            'order_number' => 'EZC-'.strtoupper(fake()->unique()->bothify('??????????')),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'recipient_name' => 'Recipient Test',
            'recipient_contact' => '09123456789',
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
            'street_address' => '1 Test Street',
            'subtotal' => 100,
            'shipping_fee' => 50,
            'commission_fee' => 10,
            'total_amount' => 150,
            'payment_method' => 'GCash',
            'status' => 'COMPLETED',
        ]);
        Dispute::query()->forceCreate([
            'order_id' => $order->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'reason' => 'Other',
            'description' => 'Evidence attached for the completed order dispute.',
            'evidence_path' => $path,
            'status' => 'PENDING',
        ]);

        $this->artisan('app:migrate-sensitive-documents')->assertExitCode(0);

        Storage::disk('private')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_migration_secures_orphaned_files_in_sensitive_public_directories(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $path = 'documents/licenses/orphaned-upload.pdf';
        Storage::disk('public')->put($path, 'orphaned license upload');

        $this->artisan('app:migrate-sensitive-documents')->assertExitCode(0);

        Storage::disk('private')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    /** @param array<string, mixed> $attributes */
    private function user(array $attributes): User
    {
        return User::query()->forceCreate(array_merge([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => 'buyer',
            'status' => 'approved',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'sex' => 'Other',
            'contact_no' => '09123456789',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
            'street_address' => '1 Test Street',
        ], $attributes));
    }
}
