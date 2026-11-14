<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutPricingService;
use App\Services\FlashReminderService;
use App\Services\FlashSaleService;
use App\Services\InventoryRestorationService;
use App\Services\OrderTransitionService;
use App\Services\PlacePurchaseService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
    throw new RuntimeException('Local/testing MySQL only.');
}
$connect = function (string $name) use ($source) {
    if (! preg_match('/\Aezicart_b3_check_\d{14}_[a-f0-9]{8}\z/', $name) || $name === $source->getDatabaseName()) {
        throw new RuntimeException('Invalid isolated schema.');
    }
    config(['database.connections.batch3_copy' => [...$source->getConfig(), 'name' => 'batch3_copy', 'database' => $name, 'url' => null], 'mail.default' => 'array']);
    DB::setDefaultConnection('batch3_copy');
    Schema::clearResolvedInstance('db.schema');
    if (User::query()->getConnection()->getName() !== 'batch3_copy' || (new User)->getConnection()->getDatabaseName() !== $name) {
        throw new RuntimeException('Model isolation failed before any fixture write.');
    }
};
$address = ['recipient_name' => 'Isolated fixture', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Isolated fixture'];
if (($argv[1] ?? null) === 'worker') {
    $connect($argv[2]);
    while (microtime(true) < (float) $argv[4]) {
        usleep(10000);
    }
    try {
        if ($argv[3] === 'place') {
            Notification::fake();
            $buyer = User::findOrFail((int) $argv[5]);
            $token = (string) Str::uuid();
            $purchase = app(PlacePurchaseService::class)->place($buyer, $token, hash('sha256', $token), [['product_id' => (int) $argv[6], 'quantity' => 1]], $argv[7], null, $address);
            echo json_encode(['result' => 'committed', 'purchase_id' => $purchase->id], JSON_THROW_ON_ERROR);
        } elseif ($argv[3] === 'reminder') {
            echo json_encode(['result' => 'delivered', 'count' => app(FlashReminderService::class)->deliver()], JSON_THROW_ON_ERROR);
        } else {
            throw new RuntimeException('Unknown worker mode.');
        }
    } catch (ValidationException $error) {
        echo json_encode(['result' => 'rejected', 'fields' => array_keys($error->errors())], JSON_THROW_ON_ERROR);
    }
    exit;
}

$name = 'ezicart_b3_check_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
$quote = fn ($value) => '`'.str_replace('`', '``', $value).'`';
$features = ['flash_sales', 'flash_reservations', 'flash_reminders', 'bundle_deals', 'bundle_products'];
$business = ['users', 'products', 'orders', 'order_items', 'vouchers', 'financial_accounts', 'ledger_transactions', 'ledger_entries'];
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
        $source->statement('CREATE TABLE '.$quote($name).'.'.$quote($table).' LIKE '.$quote($source->getDatabaseName()).'.'.$quote($table));
        $columns = $source->table('information_schema.columns')->where('table_schema', $source->getDatabaseName())->where('table_name', $table)
            ->whereRaw("COALESCE(GENERATION_EXPRESSION, '') = ''")->orderBy('ordinal_position')->selectRaw('COLUMN_NAME AS column_name')->get()->pluck('column_name')->all();
        $list = implode(', ', array_map($quote, $columns));
        $source->statement('INSERT INTO '.$quote($name).'.'.$quote($table).' ('.$list.') SELECT '.$list.' FROM '.$quote($source->getDatabaseName()).'.'.$quote($table).' LIMIT 10000');
    }
    $connect($name);
    $copyBefore = collect($business)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $migration = require dirname(__DIR__).'/database/migrations/2026_11_14_224226_add_flash_sales_bundles_and_reminders.php';
    $migration->up();
    $migration->down();
    $migration->up();
    $result['mysql_up_down_reapply'] = Schema::hasTable('flash_reservations') && Schema::hasTable('bundle_products') && $copyBefore === collect($business)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $user = fn ($role) => User::forceCreate(['email' => Str::uuid().'@example.invalid', 'password' => 'IsolatedFixture123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Isolated', 'last_name' => 'FlashFixture', 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion']);
    $seller = $user('seller');
    $buyer = $user('buyer');
    $other = $user('buyer');
    $product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Isolated flash fixture', 'category' => 'Home and Garden', 'price' => 450, 'stock' => 2, 'weight_grams' => 1000, 'compliance_status' => 'approved']);
    $slot = app(FlashSaleService::class)->create($seller, ['product_id' => $product->id, 'price' => 299, 'quantity_limit' => 1, 'starts_at' => now()->addMinute()->toIso8601String(), 'ends_at' => now()->addHour()->toIso8601String(), 'operation_key' => (string) Str::uuid()]);
    $slot->update(['starts_at' => now()->subMinute()]);
    $cart = [['product_id' => $product->id, 'quantity' => 1]];
    $reviewed = app(CheckoutPricingService::class)->quote($cart);
    $race = function (string $mode, array $arguments) use ($name, &$workers) {
        $workers = [];
        $start = (string) (microtime(true) + 2);
        foreach ($arguments as $args) {
            $worker = new Process([PHP_BINARY, __FILE__, 'worker', $name, $mode, $start, ...$args], dirname(__DIR__));
            $worker->setTimeout(40)->start();
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
    $result['placement_workers'] = $race('place', [[(string) $buyer->id, (string) $product->id, $reviewed['hash']], [(string) $other->id, (string) $product->id, $reviewed['hash']]]);
    $reservation = DB::table('flash_reservations')->where('flash_sale_id', $slot->id)->sole();
    $result['last_flash_unit_once'] = (int) $slot->fresh()->allocated_quantity === 1 && (int) $product->fresh()->stock === 1
        && count(array_filter($result['placement_workers'], fn ($row) => $row['result'] === 'committed')) === 1;
    Notification::fake();
    $fallback = app(CheckoutPricingService::class)->quote($cart);
    $token = (string) Str::uuid();
    $order = app(PlacePurchaseService::class)->place($other, $token, hash('sha256', $token), $cart, $fallback['hash'], null, $address)->orders()->firstOrFail();
    $result['fallback_price_is_regular'] = (int) round($order->items()->sole()->unit_price * 100) === 45000;
    $first = Order::findOrFail($reservation->order_id);
    app(OrderTransitionService::class)->transition($first, $seller, OrderStatus::Cancelled, 'isolated_probe_cancelled', notes: 'Isolated unfulfilled COD cancellation.');
    app(InventoryRestorationService::class)->restore($first->fresh());
    $result['cancellation_releases_once'] = (int) $slot->fresh()->allocated_quantity === 0 && (int) $product->fresh()->stock === 1;
    $reminderProduct = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Isolated reminder fixture', 'category' => 'Home and Garden', 'price' => 450, 'stock' => 10, 'weight_grams' => 1000, 'compliance_status' => 'approved']);
    $reminderSlot = app(FlashSaleService::class)->create($seller, ['product_id' => $reminderProduct->id, 'price' => 299, 'quantity_limit' => 3, 'starts_at' => now()->addSeconds(45)->toIso8601String(), 'ends_at' => now()->addHour()->toIso8601String(), 'operation_key' => (string) Str::uuid()]);
    DB::table('flash_reminders')->insert(['flash_sale_id' => $reminderSlot->id, 'buyer_id' => $buyer->id, 'created_at' => now(), 'updated_at' => now()]);
    $result['reminder_workers'] = $race('reminder', [[], []]);
    $result['reminder_delivered_once'] = array_sum(array_column($result['reminder_workers'], 'count')) === 1
        && DB::table('notifications')->where('notifiable_id', $buyer->id)->where('notifiable_type', $buyer->getMorphClass())->count() === 1;
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
    DB::setDefaultConnection($original);
    Schema::clearResolvedInstance('db.schema');
    DB::purge('batch3_copy');
    if ($created) {
        if (! preg_match('/\Aezicart_b3_check_\d{14}_[a-f0-9]{8}\z/', $name) || $name === $source->getDatabaseName()) {
            throw new RuntimeException('Unsafe cleanup target.');
        }
        $source->statement('DROP DATABASE '.$quote($name));
    }
}
$result['configured_business_counts_unchanged'] = $before === $counts();
$result['scope'] = 'Isolated MySQL full COD placement race, fallback, cancellation and database-reminder race; external mail delivery excluded.';
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
foreach (['mysql_up_down_reapply', 'last_flash_unit_once', 'fallback_price_is_regular', 'cancellation_releases_once', 'reminder_delivered_once', 'configured_business_counts_unchanged'] as $check) {
    if (! ($result[$check] ?? false)) {
        exit(1);
    }
}
