<?php

use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\DispatchCycle;
use App\Models\DispatchOffer;
use App\Models\Order;
use App\Models\ParcelIncident;
use App\Models\User;
use App\Services\DispatchLocationService;
use App\Services\DispatchOfferService;
use App\Services\ParcelIncidentService;
use App\Services\ParcelLoadService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
});
$original = DB::getDefaultConnection();
$source = DB::connection();
if ($source->getDriverName() !== 'mysql' || ! app()->environment(['local', 'testing'])) {
    throw new RuntimeException('Local/testing MySQL copy only.');
}
$connect = function (string $name) use ($source) {
    if (! preg_match('/\Aezicart_final_check_\d{14}_[a-f0-9]{8}\z/', $name) || $name === $source->getDatabaseName()) {
        throw new RuntimeException('Unsafe schema target.');
    }
    config(['database.connections.final_copy' => [...$source->getConfig(), 'name' => 'final_copy', 'database' => $name, 'url' => null], 'mail.default' => 'array', 'queue.default' => 'sync']);
    DB::setDefaultConnection('final_copy');
    Schema::clearResolvedInstance('db.schema');
    if ((new User)->getConnection()->getDatabaseName() !== $name || User::query()->getConnection()->getName() !== 'final_copy') {
        throw new RuntimeException('Model isolation failed before fixture writes.');
    }
};
if (($argv[1] ?? null) === 'worker') {
    $connect($argv[2]);
    while (microtime(true) < (float) $argv[4]) {
        usleep(10000);
    }
    try {
        $id = (int) $argv[5];
        $result = match ($argv[3]) {
            'accept' => app(DispatchOfferService::class)->respond(DispatchOffer::findOrFail($id), User::findOrFail((int) $argv[6]), true),
            'expire' => (function () use ($id) {
                app(DispatchOfferService::class)->process(DispatchCycle::findOrFail($id));

                return true;
            })(),
            'start' => app(DispatchOfferService::class)->start(Order::findOrFail($id), User::findOrFail((int) $argv[6]), $argv[7])->status,
            'manual' => DB::transaction(function () use ($id, $argv) {
                $rider = app(ParcelLoadService::class)->lockVehicle((int) $argv[6]);
                $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
                DispatchOfferService::assertNoOffer($order);
                app(ParcelLoadService::class)->assertFits($order, $rider, newWork: true);
                $order->forceFill(['pickup_courier_id' => $rider->id])->save();

                return 'ASSIGNED';
            }, 3),
            'incident' => app(ParcelIncidentService::class)->report(Order::findOrFail($id), User::findOrFail((int) $argv[6]), ['kind' => 'LOST', 'reason' => 'Isolated concurrent incident report; no physical or money mutation.', 'operation_key' => $argv[7]], null)->id,
            default => throw new RuntimeException('Unknown worker mode.'),
        };
        echo json_encode(['result' => 'processed', 'value' => $result], JSON_THROW_ON_ERROR);
    } catch (ValidationException $error) {
        echo json_encode(['result' => 'rejected', 'fields' => array_keys($error->errors())], JSON_THROW_ON_ERROR);
    } catch (HttpExceptionInterface $error) {
        echo json_encode(['result' => 'rejected', 'status' => $error->getStatusCode()], JSON_THROW_ON_ERROR);
    }
    exit;
}
$name = 'ezicart_final_check_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
$quote = fn ($value) => '`'.str_replace('`', '``', $value).'`';
$features = ['dispatch_locations', 'dispatch_cycles', 'dispatch_offers', 'parcel_incidents'];
$business = ['users', 'products', 'orders', 'order_items', 'disputes', 'notifications', 'financial_accounts', 'ledger_transactions', 'ledger_entries', 'refund_executions', 'seller_payouts'];
$counts = fn () => collect($business)->mapWithKeys(fn ($table) => [$table => $source->table($table)->count()])->all();
$before = $counts();
$created = false;
$workers = [];
$result = [];
try {
    $source->statement('CREATE DATABASE '.$quote($name));
    $created = true;
    $tables = $source->table('information_schema.tables')->where('table_schema', $source->getDatabaseName())->where('table_type', 'BASE TABLE')->selectRaw('TABLE_NAME AS table_name')->get()->pluck('table_name')->all();
    foreach (array_diff($tables, $features) as $table) {
        if ($source->table($table)->count() > 10000) {
            throw new RuntimeException('Copy limit exceeded; use a prepared disposable snapshot.');
        }
        $source->statement('CREATE TABLE '.$quote($name).'.'.$quote($table).' LIKE '.$quote($source->getDatabaseName()).'.'.$quote($table));
        $columns = $source->table('information_schema.columns')->where('table_schema', $source->getDatabaseName())->where('table_name', $table)->whereRaw("COALESCE(GENERATION_EXPRESSION, '') = ''")->orderBy('ordinal_position')->selectRaw('COLUMN_NAME AS column_name')->get()->pluck('column_name')->all();
        $list = implode(', ', array_map($quote, $columns));
        $source->statement('INSERT INTO '.$quote($name).'.'.$quote($table).' ('.$list.') SELECT '.$list.' FROM '.$quote($source->getDatabaseName()).'.'.$quote($table));
    }
    $connect($name);
    $copyBefore = collect($business)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $migration = require dirname(__DIR__).'/database/migrations/2026_11_15_014300_create_dispatch_offers_and_parcel_incidents.php';
    $migration->up();
    $migration->down();
    $migration->up();
    $result['mysql_up_down_reapply'] = $copyBefore === collect($business)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $province = 'Probe '.substr($name, -8);
    $user = fn ($role, $extra = []) => User::forceCreate(['email' => Str::uuid().'@example.invalid', 'password' => 'IsolatedFixture123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Isolated', 'last_name' => 'FinalFixture', 'contact_no' => '09123456789', 'province' => $province, 'municipality' => 'Probe Town', 'barangay' => 'Fixture', 'street_address' => 'Isolated origin', ...$extra]);
    $hub = $user('sorting_center');
    $seller = $user('seller');
    $buyer = $user('buyer');
    $area = Area::create(['name' => $name, 'code' => Str::upper(Str::random(12)), 'sorting_center_id' => $hub->id, 'is_active' => true]);
    AreaMunicipality::create(['area_id' => $area->id, 'province' => $province, 'municipality' => 'Probe Town', 'province_normalized' => strtolower($province), 'municipality_normalized' => 'probe town']);
    $makeOrder = fn () => Order::forceCreate(['order_number' => 'FINAL-'.Str::random(12), 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'recipient_name' => 'Isolated buyer', 'recipient_contact' => '09123456789', 'province' => $province, 'municipality' => 'Probe Town', 'barangay' => 'Fixture', 'street_address' => 'Isolated fixture', 'subtotal' => 100, 'shipping_fee' => 50, 'total_amount' => 150, 'commission_fee' => 10, 'payment_method' => 'COD', 'status' => 'READY_FOR_PICKUP', 'pickup_requested_at' => now(), 'destination_area_id' => $area->id, 'parcel_weight_grams' => 1000]);
    $order = $makeOrder();
    app(DispatchLocationService::class)->pin($hub, $seller, ['latitude' => 14.14, 'longitude' => 121.47, 'reason' => 'Isolated confirmed origin pin for concurrency checks.']);
    $makeRider = function (float $lat) use ($user, $hub, $area) {
        $rider = $user('courier', ['logistics_owner_id' => $hub->id, 'work_availability' => 'available', 'vehicle_capacity_grams' => 2000, 'vehicle_capacity_verified_at' => now(), 'license_expires_at' => today()->addYear(), 'hub_transfer_only' => false]);
        $rider->serviceAreas()->attach($area->id, ['is_active' => true, 'is_primary' => true]);
        app(DispatchLocationService::class)->rider($rider, ['latitude' => $lat, 'longitude' => 121.47, 'accuracy_meters' => 5, 'captured_at' => now()->toIso8601String()]);

        return $rider;
    };
    $rider = $makeRider(14.1401);
    $race = function (array $tasks) use ($name, &$workers) {
        $workers = [];
        $start = (string) (microtime(true) + 2);
        foreach ($tasks as $task) {
            $worker = new Process([PHP_BINARY, __FILE__, 'worker', $name, $task[0], $start, ...array_map('strval', array_slice($task, 1))], dirname(__DIR__));
            $worker->setTimeout(60)->start();
            $workers[] = $worker;
        }
        $outcomes = [];
        foreach ($workers as $worker) {
            $worker->wait();
            if (! $worker->isSuccessful()) {
                throw new RuntimeException('Isolated worker failed: '.$worker->getErrorOutput());
            }
            $outcomes[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $outcomes;
    };
    $cycle = app(DispatchOfferService::class)->start($order, $hub, (string) Str::uuid());
    $offer = $cycle->offers()->sole();
    $result['accept_workers'] = $race([['accept', $offer->id, $rider->id], ['accept', $offer->id, $rider->id]]);
    $result['one_accepted_assignment'] = $order->fresh()->pickup_courier_id === $rider->id && $order->trackingEvents()->where('event_type', 'automatic_offer_accepted')->count() === 1 && app(ParcelLoadService::class)->summary($rider)['grams'] === 1000;
    $autoOrder = $makeOrder();
    $manualOrder = $makeOrder();
    $result['capacity_workers'] = $race([['start', $autoOrder->id, $hub->id, (string) Str::uuid()], ['manual', $manualOrder->id, $rider->id]]);
    $summary = app(ParcelLoadService::class)->summary($rider);
    $result['shared_capacity_respected'] = $summary['grams'] === 2000 && ! $summary['overweight'] && $summary['count'] === 2;
    $second = $makeRider(14.1402);
    $third = $makeRider(14.1403);
    $expiring = app(DispatchOfferService::class)->start($makeOrder(), $hub, (string) Str::uuid());
    $expiring->offers()->where('status', 'PENDING')->update(['expires_at' => now()->subSecond()]);
    $result['expiry_workers'] = $race([['expire', $expiring->id], ['expire', $expiring->id]]);
    $result['one_expiry_cascade'] = $expiring->offers()->count() === 2 && $expiring->offers()->where('status', 'PENDING')->count() === 1 && $expiring->offers()->where('status', 'EXPIRED')->count() === 1;
    $loadBeforeIncident = app(ParcelLoadService::class)->summary($rider)['grams'];
    $ledgerBefore = DB::table('ledger_transactions')->count();
    $result['incident_workers'] = $race([['incident', $order->id, $hub->id, (string) Str::uuid()], ['incident', $order->id, $hub->id, (string) Str::uuid()]]);
    $result['one_incident_preserves_reservations'] = ParcelIncident::where('open_order_id', $order->id)->count() === 1 && $order->fresh()->status === 'READY_FOR_PICKUP' && app(ParcelLoadService::class)->summary($rider)['grams'] === $loadBeforeIncident;
    $result['incident_does_not_post_money'] = DB::table('ledger_transactions')->count() === $ledgerBefore && ! $order->payout()->exists() && ! $order->refundExecutions()->exists();
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
    DB::setDefaultConnection($original);
    Schema::clearResolvedInstance('db.schema');
    DB::purge('final_copy');
    if ($created) {
        if (! preg_match('/\Aezicart_final_check_\d{14}_[a-f0-9]{8}\z/', $name) || $name === $source->getDatabaseName()) {
            throw new RuntimeException('Unsafe cleanup target.');
        }
        $source->statement('DROP DATABASE '.$quote($name));
    }
}
$result['configured_business_counts_unchanged'] = $before === $counts();
$result['scope'] = 'Disposable local MySQL service races: accept replay, automatic offer versus manual pickup capacity guard, expiry cascade and incident reporting. Array mail; manual guard uses existing vehicle/order lock sequence, not a full HTTP logistics handoff.';
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
foreach (['mysql_up_down_reapply', 'one_accepted_assignment', 'shared_capacity_respected', 'one_expiry_cascade', 'one_incident_preserves_reservations', 'incident_does_not_post_money', 'configured_business_counts_unchanged'] as $check) {
    if (! ($result[$check] ?? false)) {
        exit(1);
    }
}
