<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! Schema::hasColumn('products', 'variations_enabled')) {
    throw new RuntimeException('The product inventory-mode migration is missing.');
}
$invalid = DB::table('products')->whereNotNull('variations_enabled')->whereNotIn('variations_enabled', [0, 1])->count();
$result = ['result' => $invalid === 0 ? 'passed' : 'failed', 'scope' => 'Read-only configured schema/mode counts; no historical mode backfill or live fixture',
    'users' => DB::table('users')->count(), 'orders' => DB::table('orders')->count(), 'products' => DB::table('products')->count(),
    'legacy_mode' => DB::table('products')->whereNull('variations_enabled')->count(), 'explicit_base' => DB::table('products')->where('variations_enabled', false)->count(), 'explicit_variations' => DB::table('products')->where('variations_enabled', true)->count(), 'invalid_modes' => $invalid];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($invalid === 0 ? 0 : 1);
