<?php

use App\Models\User;
use App\Services\HubOrderScope;
use App\Services\LogisticsQueueService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tables = ['users', 'orders', 'logistics_exceptions', 'ledger_transactions', 'administrative_audits', 'notifications'];
$counts = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
$before = $counts();
$hub = new User;
$hub->forceFill(['id' => 0, 'role' => 'sorting_center', 'status' => 'approved']);
$base = app(HubOrderScope::class)->query($hub)->whereKey(0);
$queues = collect(array_keys(LogisticsQueueService::QUEUES))->mapWithKeys(fn ($key) => [$key => app(LogisticsQueueService::class)->filter(clone $base, $key)->count()]);
$exceptions = DB::table('logistics_exceptions')->join('orders', 'orders.id', '=', 'logistics_exceptions.order_id')
    ->where('logistics_exceptions.status', 'OPEN')->whereIn('orders.id', (clone $base)->select('orders.id'))->count();
$search = (clone $base)->where(function ($q): void {
    $q->whereRaw("order_number LIKE ? ESCAPE '!'", ['%probe!%!_%'])->orWhere('parcel_code', 'PROBE')
        ->orWhereRaw("recipient_name LIKE ? ESCAPE '!'", ['%probe%'])
        ->orWhereHas('buyer', fn ($buyer) => $buyer->whereRaw("first_name LIKE ? ESCAPE '!'", ['%probe%'])->orWhereRaw("last_name LIKE ? ESCAPE '!'", ['%probe%']));
})->count();
echo json_encode(['driver' => DB::getDriverName(), 'empty_queue_predicates_executed' => $queues->count(),
    'all_empty' => $queues->sum() === 0 && $exceptions === 0 && $search === 0,
    'business_row_counts_unchanged' => $before === $counts(),
    'search_probe' => 'Equivalent grouped escaped-name/reference SQL only; populated HTTP behavior is covered separately in SQLite.',
    'populated_mysql_workflow_tested' => false, 'timed_mysql_race_tested' => false,
], JSON_PRETTY_PRINT).PHP_EOL;
