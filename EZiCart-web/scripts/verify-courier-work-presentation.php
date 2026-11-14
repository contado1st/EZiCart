<?php

use App\Models\Order;
use App\Models\User;
use App\Services\CourierCompensationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Unsaved zero-ID models exercise empty read SQL without inserting business fixtures or exposing identities.
$rider = new User;
$rider->forceFill(['id' => 0, 'role' => 'courier']);
$order = new Order;
$order->forceFill(['id' => 0, 'pickup_courier_id' => 0]);
$tables = ['users', 'orders', 'financial_accounts', 'ledger_transactions', 'logistics_allocations', 'parcel_tracking_events'];
$counts = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
$before = $counts();
$summaries = app(CourierCompensationService::class)->summaries(new Collection([$order]), $rider);
// Execute the same grouped credit query with an empty owner; the empty-summary path has no allocation transactions.
$credits = DB::table('ledger_entries')->join('financial_accounts', 'financial_accounts.id', '=', 'ledger_entries.account_id')
    ->where('financial_accounts.user_id', 0)->where('financial_accounts.key', 'user:0:available')
    ->where('financial_accounts.bucket', 'available')->where('delta_cents', '>', 0)
    ->selectRaw('transaction_id, SUM(delta_cents) AS credited')->groupBy('transaction_id')->get();
echo json_encode(['driver' => DB::getDriverName(), 'empty_order_summary_executed' => isset($summaries[0]),
    'empty_owner_grouped_credits' => $credits->count(), 'unsaved_fixture_status' => $summaries[0]['rows'][0]['status'] ?? null,
    'business_row_counts_unchanged' => $before === $counts(), 'populated_workflow_tested' => false,
], JSON_PRETTY_PRINT).PHP_EOL;
