<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Read-only inventory. Outputs counts and schema metadata, never account values.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$result = ['scope' => 'Laravel web', 'driver' => DB::getDriverName(), 'php' => PHP_VERSION,
    'upload_limits' => ['upload_max_filesize' => ini_get('upload_max_filesize'), 'post_max_size' => ini_get('post_max_size')],
    'capabilities' => ['gd' => extension_loaded('gd'), 'zip' => extension_loaded('zip')], 'tables' => []];
foreach (['users', 'products', 'product_variations', 'order_items', 'reviews', 'buyer_addresses', 'refund_executions', 'sessions'] as $table) {
    if (Schema::hasTable($table)) {
        $result['tables'][$table] = ['rows' => DB::table($table)->count(), 'columns' => Schema::getColumnListing($table), 'indexes' => Schema::getIndexes($table)];
    }
}
$result['canonical_email_collision_groups'] = DB::query()->fromSub(DB::table('users')->selectRaw('LOWER(TRIM(email)) as canonical')->groupByRaw('LOWER(TRIM(email))')->havingRaw('COUNT(*) > 1'), 'duplicates')->count();
if (Schema::hasColumn('product_variations', 'sku')) {
    $result['store_sku_collision_groups'] = DB::query()->fromSub(DB::table('product_variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
        ->whereNotNull('v.sku')->whereRaw("TRIM(v.sku) <> ''")->selectRaw('p.user_id, LOWER(TRIM(v.sku)) as canonical')
        ->groupBy('p.user_id')->groupByRaw('LOWER(TRIM(v.sku))')->havingRaw('COUNT(*) > 1'), 'duplicates')->count();
}
if (Schema::hasTable('buyer_addresses')) {
    $result['addresses_missing_street'] = DB::table('buyer_addresses')->where(fn ($q) => $q->whereNull('street_address')->orWhereRaw("TRIM(street_address) = ''"))->count();
}
if (Schema::hasTable('refund_executions')) {
    $result['refund_states'] = DB::table('refund_executions')->selectRaw('status, COUNT(*) as records')->groupBy('status')->pluck('records', 'status')->all();
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
