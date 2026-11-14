<?php

use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryRetryService;
use App\Services\HubOrderScope;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Schema/counts and empty-owner SQL only; no live identity, proposal, scan or ledger posting.
if (! Schema::hasTable('delivery_retry_requests')) {
    throw new RuntimeException('Apply the delivery retry migration first.');
}
$tables = ['users', 'orders', 'delivery_attempts', 'delivery_retry_requests', 'ledger_transactions', 'administrative_audits', 'notifications'];
$counts = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
$before = $counts();
$rider = new User;
$rider->forceFill(['id' => 0, 'role' => 'courier']);
$hub = new User;
$hub->forceFill(['id' => 0, 'role' => 'sorting_center']);
$order = new Order;
$order->forceFill(['id' => 0, 'sorting_center_id' => 0, 'status' => 'SORTED']);
$contexts = app(DeliveryRetryService::class)->contexts(new Collection([$order]), $rider);
$queue = app(HubOrderScope::class)->query($hub)->whereKey(0)->whereIn('status', ['DELIVERY_FAILED', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])
    ->whereHas('deliveryAttempts', fn ($q) => $q->where('outcome', 'failed'), '>=', max(1, (int) config('logistics.maximum_delivery_attempts', 3)))->count();
echo json_encode(['driver' => DB::getDriverName(), 'retry_table_present' => true,
    'unique_failed_attempt' => collect(Schema::getIndexes('delivery_retry_requests'))->contains(fn ($index) => $index['unique'] && $index['columns'] === ['failed_attempt_id']),
    'hub_pending_index' => collect(Schema::getIndexes('delivery_retry_requests'))->contains(fn ($index) => $index['columns'] === ['hub_id', 'status', 'id']),
    'foreign_keys' => count(Schema::getForeignKeys('delivery_retry_requests')),
    'configured_retry_requests' => DB::table('delivery_retry_requests')->count(),
    'empty_owner_context_executed' => isset($contexts[0]), 'empty_owner_return_queue' => $queue,
    'business_row_counts_unchanged' => $before === $counts(), 'populated_mysql_workflow_tested' => false,
], JSON_PRETTY_PRINT).PHP_EOL;
