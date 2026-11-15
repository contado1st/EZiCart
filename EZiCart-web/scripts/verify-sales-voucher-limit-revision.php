<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
foreach (['product_sales', 'voucher_products'] as $table) {
    if (! Schema::hasTable($table)) {
        throw new RuntimeException('Missing Batch 2 table: '.$table);
    }
}
foreach (['products' => ['purchase_limit'], 'vouchers' => ['visibility', 'product_scoped', 'terms_version'], 'order_items' => ['pricing_snapshot']] as $table => $columns) {
    foreach ($columns as $column) {
        if (! Schema::hasColumn($table, $column)) {
            throw new RuntimeException('Missing Batch 2 column: '.$table.'.'.$column);
        }
    }
}
$invalidSales = DB::table('product_sales')->join('products', 'products.id', '=', 'product_sales.product_id')->whereColumn('products.user_id', '!=', 'product_sales.seller_id')->count();
$invalidTargets = DB::table('voucher_products')->join('products', 'products.id', '=', 'voucher_products.product_id')->join('vouchers', 'vouchers.id', '=', 'voucher_products.voucher_id')
    ->whereNotNull('vouchers.seller_id')->whereColumn('products.user_id', '!=', 'vouchers.seller_id')->count();
if ($invalidSales || $invalidTargets) {
    throw new RuntimeException('Sales/voucher ownership needs review.');
}
echo json_encode(['result' => 'passed', 'scope' => 'Read-only configured schema and ownership inspection', 'environment' => app()->environment(), 'driver' => DB::getDriverName(),
    'product_sales' => DB::table('product_sales')->count(), 'voucher_products' => DB::table('voucher_products')->count(), 'products_with_purchase_limit' => DB::table('products')->whereNotNull('purchase_limit')->count(),
    'code_only_vouchers' => DB::table('vouchers')->where('visibility', 'code_only')->count(), 'users' => DB::table('users')->count(), 'orders' => DB::table('orders')->count(), 'invalid_sales' => $invalidSales, 'invalid_targets' => $invalidTargets], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
