<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$tables = ['store_categories', 'product_store_category', 'seller_catalog_operations', 'review_replies'];
$counts = [];
foreach ($tables as $table) {
    if (! Schema::hasTable($table)) {
        throw new RuntimeException('Missing seller workspace table: '.$table);
    }
    $counts[$table] = DB::table($table)->count();
}
$invalidMemberships = DB::table('product_store_category as membership')
    ->join('products', 'products.id', '=', 'membership.product_id')
    ->join('store_categories', 'store_categories.id', '=', 'membership.store_category_id')
    ->whereColumn('products.user_id', '!=', 'store_categories.seller_id')->count();
$overLimitProducts = DB::table('product_store_category')->select('product_id')->groupBy('product_id')->havingRaw('COUNT(*) > 3')->get()->count();
$invalidParents = DB::table('store_categories as child')->join('store_categories as parent', 'parent.id', '=', 'child.parent_id')
    ->where(fn ($query) => $query->whereColumn('child.seller_id', '!=', 'parent.seller_id')->orWhereNotNull('parent.parent_id'))->count();
if ($invalidMemberships || $overLimitProducts || $invalidParents) {
    throw new RuntimeException('Seller category integrity needs review.');
}
echo json_encode(['result' => 'passed', 'scope' => 'read-only schema/integrity inspection; no browser or concurrency acceptance',
    'environment' => app()->environment(), 'driver' => DB::getDriverName(), 'workspace_rows' => $counts,
    'invalid_memberships' => $invalidMemberships, 'over_limit_products' => $overLimitProducts, 'invalid_parents' => $invalidParents], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
