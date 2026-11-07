<?php
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode([
    'environment' => app()->environment(),
    'database_driver' => DB::connection()->getDriverName(),
    'accounts' => App\Models\User::whereIn('email', ['buyer@ezicart.com', 'seller@ezicart.com', 'courier@ezicart.com', 'sorting@ezicart.com', 'admin@ezicart.com'])->get(['id', 'email', 'role', 'status', 'province', 'municipality', 'barangay', 'logistics_owner_id'])->toArray(),
    'mapping' => App\Models\AreaMunicipality::with('area')->where('province_normalized', 'laguna')->where('municipality_normalized', 'majayjay')->get()->toArray(),
    'coverage' => DB::table('barangay_rider_assignments')->get(['area_municipality_id', 'barangay', 'primary_rider_id'])->toArray(),
    'rider_membership' => App\Models\User::findOrFail(4)->serviceAreas()->get()->toArray(),
    'staging' => DB::table('storage_locations')->where('hub_id', 5)->where('code', 'DEMO-STAGE-01')->get()->toArray(),
    'address' => App\Models\BuyerAddress::where('user_id', 2)->where('label', 'Demo delivery — Majayjay')->get(['id', 'province', 'municipality', 'barangay', 'house_number', 'street_address', 'postal_code'])->toArray(),
    'orders' => App\Models\Order::select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->get()->toArray(),
], JSON_PRETTY_PRINT);
