<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$originalConnection = DB::getDefaultConnection();
$source = DB::connection();
if ($source->getDriverName() !== 'mysql') {
    throw new RuntimeException('This isolated DDL probe requires the configured MySQL connection.');
}
$database = $source->getDatabaseName();
$copyName = 'ezicart_wallet_ddl_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
$quote = fn (string $name): string => '`'.str_replace('`', '``', $name).'`';
$tables = ['users', 'orders', 'wallet_funding_intents'];
$counts = fn (): array => collect($tables)->mapWithKeys(fn ($table) => [$table => $source->table($table)->count()])->all();
$before = $counts();
$sizes = $source->table('information_schema.tables')->where('table_schema', $database)->whereIn('table_name', $tables)
    ->get(['table_name', 'table_rows', 'data_length', 'index_length'])->toArray();
$created = false;
$result = [];
try {
    $source->statement('CREATE DATABASE '.$quote($copyName));
    $created = true;
    foreach ($tables as $table) {
        $source->statement('CREATE TABLE '.$quote($copyName).'.'.$quote($table).' LIKE '.$quote($database).'.'.$quote($table));
        $source->statement('INSERT INTO '.$quote($copyName).'.'.$quote($table).' SELECT * FROM '.$quote($database).'.'.$quote($table).' LIMIT 10000');
    }
    config(['database.connections.wallet_ddl_copy' => [...$source->getConfig(), 'database' => $copyName]]);
    DB::setDefaultConnection('wallet_ddl_copy');
    Schema::clearResolvedInstance('db.schema');
    $copyBefore = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $migration = require dirname(__DIR__).'/database/migrations/2026_11_14_141449_add_internal_demo_wallet_requests_and_payment_deadline.php';
    $started = microtime(true);
    $migration->up();
    $elapsed = round((microtime(true) - $started) * 1000, 1);
    $result['copy_deadline_index'] = collect(Schema::getIndexes('orders'))->contains(fn ($index) => $index['name'] === 'orders_wallet_deadline');
    $result['copy_receipt_unique'] = collect(Schema::getIndexes('wallet_funding_intents'))->contains(fn ($index) => $index['unique'] && $index['columns'] === ['deposit_reference_hash']);
    $result['copy_review_fk'] = collect(Schema::getForeignKeys('wallet_funding_intents'))->contains(fn ($fk) => $fk['columns'] === ['reviewed_by']);
    $result['copy_existing_rows_unchanged'] = $copyBefore === collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $migration->down();
    $result['copy_down_removed_only_new_fields'] = ! Schema::hasColumn('orders', 'wallet_payment_due_at')
        && ! Schema::hasColumn('wallet_funding_intents', 'request_kind') && Schema::hasColumn('wallet_funding_intents', 'amount_cents')
        && $copyBefore === collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $migration->up();
    $result['copy_reapply_passed'] = Schema::hasColumn('orders', 'wallet_payment_due_at');
    $result['copy_ddl_ms'] = $elapsed;
    $result['copied_counts'] = $copyBefore;
} finally {
    DB::setDefaultConnection($originalConnection);
    Schema::clearResolvedInstance('db.schema');
    DB::purge('wallet_ddl_copy');
    if ($created) {
        if (! preg_match('/\Aezicart_wallet_ddl_\d{14}_[a-f0-9]{8}\z/', $copyName) || $copyName === $database) {
            throw new RuntimeException('Unsafe temporary schema cleanup target.');
        }
        $source->statement('DROP DATABASE '.$quote($copyName));
    }
}
$result['configured_sizes'] = $sizes;
$result['configured_business_counts_unchanged'] = $before === $counts();
$result['configured_schema_not_migrated_by_probe'] = ! Schema::hasColumn('orders', 'wallet_payment_due_at');
$result['timed_mysql_race_tested'] = false;
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
foreach (['copy_deadline_index', 'copy_receipt_unique', 'copy_review_fk', 'copy_existing_rows_unchanged', 'copy_down_removed_only_new_fields', 'copy_reapply_passed', 'configured_business_counts_unchanged'] as $check) {
    if (! ($result[$check] ?? false)) {
        exit(1);
    }
}
