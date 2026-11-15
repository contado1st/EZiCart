<?php

use App\Models\User;
use App\Services\ParcelCorrectionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$tables = ['users', 'orders', 'parcel_corrections', 'parcel_tracking_events', 'administrative_audits', 'ledger_transactions', 'notifications'];
$counts = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
$before = $counts();
$hub = new User;
$hub->forceFill(['id' => 0, 'role' => 'sorting_center', 'status' => 'approved']);
$empty = app(ParcelCorrectionService::class)->query($hub)->whereKey(0)->whereHas('order', fn ($q) => $q->whereRaw("order_number LIKE ? ESCAPE '!'", ['%probe!_%']))->count();
$json = DB::table('parcel_corrections')->where('id', 0)->where('snapshot_before->status', 'SORTED')->count();
echo json_encode(['driver' => DB::getDriverName(), 'table_present' => Schema::hasTable('parcel_corrections'),
    'unique_open_order' => collect(Schema::getIndexes('parcel_corrections'))->contains(fn ($index) => $index['unique'] && $index['columns'] === ['open_order_id']),
    'hub_status_index' => collect(Schema::getIndexes('parcel_corrections'))->contains(fn ($index) => $index['columns'] === ['hub_id', 'status', 'id']),
    'foreign_keys' => count(Schema::getForeignKeys('parcel_corrections')), 'configured_corrections' => DB::table('parcel_corrections')->count(),
    'empty_owner_and_json_queries_executed' => $empty === 0 && $json === 0, 'business_row_counts_unchanged' => $before === $counts(),
    'populated_mysql_workflow_tested' => false, 'timed_mysql_race_tested' => false,
], JSON_PRETTY_PRINT).PHP_EOL;
