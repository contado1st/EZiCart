<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$tables = ['dispatch_locations', 'dispatch_cycles', 'dispatch_offers', 'parcel_incidents'];
foreach ($tables as $table) {
    if (! Schema::hasTable($table)) {
        throw new RuntimeException('Missing final revision schema: '.$table);
    }
}
$invalidOffers = DB::table('dispatch_offers')->where('status', 'PENDING')->where(fn ($q) => $q->whereNull('active_cycle_id')->orWhere('weight_grams', '<', 1))->count();
$invalidIncidents = DB::table('parcel_incidents')->where('status', 'OPEN')->whereNull('open_order_id')->count();
$result = ['result' => $invalidOffers + $invalidIncidents === 0 ? 'passed' : 'failed', 'scope' => 'Read-only configured schema/count checks; no dispatch or incident fixture activated', 'users' => DB::table('users')->count(), 'orders' => DB::table('orders')->count(), 'rows' => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all(), 'invalid_offers' => $invalidOffers, 'invalid_incidents' => $invalidIncidents];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($result['result'] === 'passed' ? 0 : 1);
