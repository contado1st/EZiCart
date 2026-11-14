<?php

use App\Models\Order;
use App\Services\ReturnWorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Read-only metadata and an unsaved, zero-ID order; never use a live identity to exercise writes.
$tables = ['users', 'orders', 'order_items', 'return_inspection_items', 'refund_executions', 'financial_accounts', 'ledger_transactions', 'administrative_audits', 'notifications'];
$counts = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
$before = $counts();
$order = new Order;
$order->forceFill(['id' => 0, 'buyer_id' => 0, 'seller_id' => 0, 'status' => 'RETURNED_TO_SELLER', 'payment_method' => 'COD', 'total_amount' => 0]);
$summary = app(ReturnWorkflowService::class)->summary($order);
$indexes = collect(Schema::getIndexes('refund_executions'));
echo json_encode([
    'driver' => DB::getDriverName(),
    'unique_inspected_order_item' => collect(Schema::getIndexes('return_inspection_items'))->contains(fn ($index) => $index['unique'] && $index['columns'] === ['order_item_id']),
    'inspection_foreign_keys' => count(Schema::getForeignKeys('return_inspection_items')),
    'dispute_nullable' => collect(Schema::getColumns('refund_executions'))->firstWhere('name', 'dispute_id')['nullable'],
    'old_dispute_unique_retained' => $indexes->contains(fn ($index) => $index['unique'] && $index['columns'] === ['dispute_id']),
    'unique_rts_order' => $indexes->contains(fn ($index) => $index['unique'] && $index['columns'] === ['rts_order_id']),
    'refund_foreign_keys' => count(Schema::getForeignKeys('refund_executions')),
    'configured_inspection_lines' => DB::table('return_inspection_items')->count(),
    'configured_rts_authorizations' => DB::table('refund_executions')->whereNotNull('rts_order_id')->count(),
    'empty_owner_summary_executed' => $summary['lines']->isEmpty() && ! $summary['receipt_confirmed'] && ! $summary['can_authorize_rts'],
    'business_row_counts_unchanged' => $before === $counts(),
    'populated_mysql_workflow_tested' => false,
    'timed_mysql_race_tested' => false,
], JSON_PRETTY_PRINT).PHP_EOL;
