<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$tables = ['flash_sales', 'flash_reservations', 'flash_reminders', 'bundle_deals', 'bundle_products'];
foreach ($tables as $table) {
    if (! Schema::hasTable($table)) {
        throw new RuntimeException('Missing promotion table: '.$table);
    }
}
$badSlots = DB::table('flash_sales')->join('products', 'products.id', '=', 'flash_sales.product_id')
    ->where(fn ($q) => $q->whereColumn('flash_sales.seller_id', '!=', 'products.user_id')->orWhereColumn('allocated_quantity', '>', 'quantity_limit')->orWhere('allocated_quantity', '<', 0))->count();
$badBundles = DB::table('bundle_products')->join('products', 'products.id', '=', 'bundle_products.product_id')->join('bundle_deals', 'bundle_deals.id', '=', 'bundle_products.bundle_deal_id')
    ->whereColumn('bundle_deals.seller_id', '!=', 'products.user_id')->count();
$badGifts = DB::table('bundle_deals')->join('products', 'products.id', '=', 'bundle_deals.gift_product_id')->whereColumn('bundle_deals.seller_id', '!=', 'products.user_id')->count();
if ($badSlots || $badBundles || $badGifts) {
    throw new RuntimeException('Promotion ownership/allocation requires review.');
}
echo json_encode(['result' => 'passed', 'scope' => 'Read-only configured schema, ownership and allocation bounds', 'environment' => app()->environment(), 'driver' => DB::getDriverName(),
    'promotion_rows' => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all(),
    'users' => DB::table('users')->count(), 'orders' => DB::table('orders')->count(), 'invalid_slots' => $badSlots, 'invalid_bundles' => $badBundles, 'invalid_gifts' => $badGifts], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
