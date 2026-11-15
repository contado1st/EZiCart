<?php

use App\Models\Order;
use App\Models\User;
use App\Services\BuyerOrderGroupingService;
use App\Services\DemoWalletFundingService;
use App\Services\WalletFundingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$tables = ['users', 'orders', 'wallet_funding_intents', 'financial_accounts', 'ledger_transactions', 'ledger_entries', 'notifications', 'administrative_audits'];
$counts = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
$before = $counts();
$owner = new User;
$owner->forceFill(['id' => 0, 'role' => 'ecommerce_admin', 'status' => 'approved']);
$groups = app(BuyerOrderGroupingService::class)->counts(Order::where('buyer_id', 0));
$visible = app(DemoWalletFundingService::class)->visible($owner)->where('provider', 'internal_demo')->where('status', 'PENDING')->count();
$result = ['driver' => DB::getDriverName(), 'environment' => app()->environment(), 'demo_enabled' => app(WalletFundingService::class)->enabled(),
    'payment_deadline_column' => Schema::hasColumn('orders', 'wallet_payment_due_at'),
    'expiry_index' => collect(Schema::getIndexes('orders'))->contains(fn ($index) => $index['name'] === 'orders_wallet_deadline'),
    'review_foreign_key' => collect(Schema::getForeignKeys('wallet_funding_intents'))->contains(fn ($fk) => $fk['columns'] === ['reviewed_by']),
    'unique_receipt_hash' => collect(Schema::getIndexes('wallet_funding_intents'))->contains(fn ($index) => $index['unique'] && $index['columns'] === ['deposit_reference_hash']),
    'empty_owner_to_pay_query_passed' => $groups['all'] === 0 && $groups['to_pay'] === 0,
    'scoped_funding_query_executed' => is_int($visible), 'configured_unpaid_wallet_orders' => Order::awaitingWalletPayment()->count(),
    'configured_internal_demo_requests' => DB::table('wallet_funding_intents')->where('provider', 'internal_demo')->count(),
    'business_counts_unchanged' => $before === $counts(), 'populated_mysql_workflow_tested' => false, 'timed_mysql_race_tested' => false];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
foreach (['payment_deadline_column', 'expiry_index', 'review_foreign_key', 'unique_receipt_hash', 'empty_owner_to_pay_query_passed', 'scoped_funding_query_executed', 'business_counts_unchanged'] as $check) {
    if (! $result[$check]) {
        exit(1);
    }
}
