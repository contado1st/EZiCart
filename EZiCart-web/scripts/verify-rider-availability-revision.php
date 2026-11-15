<?php

use App\Models\User;
use App\Services\RiderWorkAvailabilityService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Read-only schema/count checks. Never choose for a rider, reconcile offers or emit identities.
$fields = ['work_availability', 'work_availability_changed_at', 'work_availability_version', 'suspending_hub_id'];
$columns = collect($fields)->mapWithKeys(fn ($field) => [$field => Schema::hasColumn('users', $field)]);
if ($columns->contains(false)) {
    throw new RuntimeException('Apply the rider availability migration before running this probe.');
}
$availability = app(RiderWorkAvailabilityService::class);
echo json_encode(['driver' => DB::getDriverName(), 'columns' => $columns,
    'suspending_hub_foreign_key' => collect(Schema::getForeignKeys('users'))->contains(fn ($key) => $key['columns'] === ['suspending_hub_id'] && $key['foreign_table'] === 'users'),
    'current_mode_new_work_candidates' => User::availableForNewWork()->count(),
    'legacy_logistics_suspensions_without_recorded_hub' => User::where('role', 'courier')->where('status', 'suspended')->where('suspension_source', 'logistics')->whereNull('suspending_hub_id')->count(),
    'readiness' => $availability->readiness(),
], JSON_PRETTY_PRINT).PHP_EOL;
