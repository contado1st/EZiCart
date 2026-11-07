<?php

use App\Models\Order;
use App\Models\ParcelTransfer;
use App\Models\User;
use App\Services\OrderTransitionService;
use App\Services\ParcelLoadService;
use App\Services\ParcelTransferService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

// Exercise the seeded lanes against the configured local database, then roll back.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment(['local', 'testing'])) {
    throw new LogicException('Hub smoke checks are restricted to local/testing.');
}

Notification::fake();
$before = ParcelTransfer::count();
$results = [];
DB::beginTransaction();
try {
    $admin = User::where('email', 'hub.admin@ezicart.com')->firstOrFail();
    foreach (['HUB-DEMO-001' => 'transport.majayjay@ezicart.com', 'HUB-DEMO-002' => 'transport.santacruz@ezicart.com'] as $number => $email) {
        $order = Order::where('order_number', $number)->firstOrFail();
        if ($order->status !== 'SORTED' || $order->activeTransfer()->exists()) {
            throw new LogicException($number.' already progressed. This check does not reset demo progress.');
        }
        $driver = User::where('email', $email)->firstOrFail();
        $source = $order->sortingCenter;
        $destination = $order->destinationArea->sortingCenter;
        $service = $app->make(ParcelTransferService::class);
        $loads = $app->make(ParcelLoadService::class);
        $transfer = $service->plan($order, $admin, $destination, 'Rollback-only local hub smoke check.');
        $service->release($transfer, $source, 'EZP:'.$order->parcel_code, 'ROLLBACK-CHECK', $driver);
        if ($transfer->fresh()->status !== 'IN_TRANSIT' || $loads->summary($driver)['grams'] !== $order->parcel_weight_grams) {
            throw new LogicException('Release or load reservation failed for '.$number);
        }
        $app->make(OrderTransitionService::class)->receiveHubTransfer($transfer, $destination, 'EZP:'.$order->parcel_code);
        if ($transfer->fresh()->status !== 'RECEIVED' || $order->fresh()->sorting_center_id !== $destination->id || $loads->summary($driver)['grams'] !== 0) {
            throw new LogicException('Receipt, ownership or unloading failed for '.$number);
        }
        $results[] = ['parcel' => $number, 'result' => 'passed', 'source' => $source->email, 'destination' => $destination->email, 'driver' => $driver->email];
    }
} finally {
    DB::rollBack();
}
if (ParcelTransfer::count() !== $before) {
    throw new LogicException('Rollback verification failed.');
}
echo json_encode(['result' => 'passed', 'database' => DB::getDriverName(), 'checks' => $results, 'rolled_back' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
