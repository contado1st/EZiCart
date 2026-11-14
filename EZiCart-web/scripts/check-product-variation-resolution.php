<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryRestorationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
    throw new RuntimeException('Local/testing MySQL only; all fixtures must use a disposable copy.');
}
$connect = function (string $name) use ($source): void {
    if (! preg_match('/\Aezicart_variation_check_\d{14}_[a-f0-9]{8}\z/', $name) || $name === $source->getDatabaseName()) {
        throw new RuntimeException('Unsafe schema target.');
    }
    config(['database.connections.variation_copy' => [...$source->getConfig(), 'name' => 'variation_copy', 'database' => $name, 'url' => null], 'mail.default' => 'array']);
    DB::setDefaultConnection('variation_copy');
    Schema::clearResolvedInstance('db.schema');
    if ((new Product)->getConnection()->getDatabaseName() !== $name || Product::query()->getConnection()->getName() !== 'variation_copy') {
        throw new RuntimeException('Fixture isolation failed.');
    }
};
if (($argv[1] ?? null) === 'worker') {
    $connect($argv[2]);
    while (microtime(true) < (float) $argv[3]) {
        usleep(10000);
    }
    app(InventoryRestorationService::class)->restore(Order::findOrFail((int) $argv[4]));
    echo 'restored';
    exit;
}
$name = 'ezicart_variation_check_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
$quote = fn ($value) => '`'.str_replace('`', '``', $value).'`';
$business = ['users', 'products', 'product_variations', 'orders', 'order_items', 'administrative_audits', 'ledger_transactions'];
$counts = fn () => collect($business)->mapWithKeys(fn ($table) => [$table => $source->table($table)->count()])->all();
$before = $counts();
$created = false;
$workers = [];
$result = [];
try {
    $source->statement('CREATE DATABASE '.$quote($name));
    $created = true;
    $tables = $source->table('information_schema.tables')->where('table_schema', $source->getDatabaseName())->where('table_type', 'BASE TABLE')->selectRaw('TABLE_NAME AS table_name')->pluck('table_name')->all();
    foreach ($tables as $table) {
        if ($source->table($table)->count() > 10000) {
            throw new RuntimeException('Copy limit exceeded; use a prepared disposable snapshot.');
        }
        $source->statement('CREATE TABLE '.$quote($name).'.'.$quote($table).' LIKE '.$quote($source->getDatabaseName()).'.'.$quote($table));
        $columns = $source->table('information_schema.columns')->where('table_schema', $source->getDatabaseName())->where('table_name', $table)->whereRaw("COALESCE(GENERATION_EXPRESSION, '') = ''")->orderBy('ordinal_position')->selectRaw('COLUMN_NAME AS column_name')->pluck('column_name')->all();
        $list = implode(', ', array_map($quote, $columns));
        $source->statement('INSERT INTO '.$quote($name).'.'.$quote($table).' ('.$list.') SELECT '.$list.' FROM '.$quote($source->getDatabaseName()).'.'.$quote($table));
    }
    $connect($name);
    $migration = require dirname(__DIR__).'/database/migrations/2026_11_15_062908_add_variations_enabled_to_products_table.php';
    if (Schema::hasColumn('products', 'variations_enabled')) {
        $migration->down();
    }
    $copyCount = Product::count();
    $migration->up();
    $migration->down();
    $migration->up();
    $result['migration_up_down_reapply_preserves_rows'] = Product::count() === $copyCount && ! Product::whereNotNull('variations_enabled')->exists();
    $seller = User::forceCreate(['email' => Str::uuid().'@example.invalid', 'password' => 'IsolatedFixture123!', 'role' => 'seller', 'status' => 'approved', 'first_name' => 'Variation', 'last_name' => 'Fixture', 'contact_no' => '09123456789']);
    $makeProduct = fn ($mode) => Product::forceCreate(['name' => 'Isolated mode check', 'user_id' => $seller->id, 'price' => 100, 'stock' => 7, 'category' => 'Pet Supplies', 'weight_grams' => 1000, 'is_archived' => false, 'compliance_status' => 'approved', 'variations_enabled' => $mode]);
    $product = $makeProduct(true);
    $retired = $product->variations()->forceCreate(['type' => 'Size', 'value' => 'Old', 'stock' => 0, 'is_active' => false]);
    $product->variations()->create(['type' => 'Size', 'value' => 'Current', 'stock' => 7]);
    $legacy = $makeProduct(null);
    $legacy->variations()->forceCreate(['type' => 'Size', 'value' => 'Retired', 'stock' => 7, 'is_active' => false]);
    $base = $makeProduct(false);
    $base->variations()->forceCreate(['type' => 'Size', 'value' => 'Retired', 'stock' => 7, 'is_active' => false]);
    $result['explicit_base_and_legacy_visibility_differ'] = ! Product::availableForMarketplace()->whereKey($legacy->id)->exists() && Product::availableForMarketplace()->whereKey($base->id)->exists() && $legacy->usesVariations() && ! $base->usesVariations();
    $order = Order::forceCreate(['order_number' => 'MODE-'.Str::uuid(), 'buyer_id' => $seller->id, 'seller_id' => $seller->id, 'recipient_name' => 'Fixture only', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Isolated copy', 'subtotal' => 200, 'shipping_fee' => 50, 'total_amount' => 250, 'payment_method' => 'COD', 'status' => 'CANCELLED']);
    OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'variation_id' => $retired->id, 'product_name' => $product->name, 'quantity' => 2, 'unit_price' => 100, 'item_total' => 200]);
    $start = (string) (microtime(true) + 2);
    for ($i = 0; $i < 2; $i++) {
        $worker = new Process([PHP_BINARY, __FILE__, 'worker', $name, $start, (string) $order->id], dirname(__DIR__));
        $worker->setTimeout(45)->start();
        $workers[] = $worker;
    }
    foreach ($workers as $worker) {
        $worker->wait();
        if (! $worker->isSuccessful()) {
            throw new RuntimeException('Restore worker failed: '.$worker->getErrorOutput());
        }
    }
    $result['concurrent_restoration_once_without_sellable_inflation'] = $retired->fresh()->stock === 2 && $product->fresh()->stock === 7 && (int) $product->variations()->where('is_active', true)->sum('stock') === 7 && $order->fresh()->inventory_restored_at !== null;
    $result['one_retired_stock_audit'] = DB::table('administrative_audits')->where('entity_id', $retired->id)->where('action', 'retired_variation_stock_restored')->count() === 1;
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
    DB::setDefaultConnection($original);
    Schema::clearResolvedInstance('db.schema');
    DB::purge('variation_copy');
    if ($created) {
        if (! preg_match('/\Aezicart_variation_check_\d{14}_[a-f0-9]{8}\z/', $name) || $name === $source->getDatabaseName()) {
            throw new RuntimeException('Unsafe cleanup target.');
        }
        $source->statement('DROP DATABASE '.$quote($name));
    }
}
$result['configured_business_counts_unchanged'] = $before === $counts();
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit(in_array(false, $result, true) ? 1 : 0);
