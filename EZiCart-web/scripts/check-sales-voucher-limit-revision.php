<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use App\Models\Voucher;
use App\Services\ClaimedVoucherEditService;
use App\Services\FinancialLedgerService;
use App\Services\ProductPurchaseLimitService;
use App\Services\ProductSaleService;
use App\Services\VoucherFundingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
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
    throw new RuntimeException('This probe requires local/testing MySQL and only writes to an isolated schema.');
}
$connectCopy = function (string $name) use ($source) {
    if (! preg_match('/\Aezicart_b2_check_\d{14}_[a-f0-9]{8}\z/', $name) || $name === $source->getDatabaseName()) {
        throw new RuntimeException('Invalid isolated schema name.');
    }
    config(['database.connections.batch2_copy' => [...$source->getConfig(), 'name' => 'batch2_copy', 'database' => $name, 'url' => null]]);
    DB::setDefaultConnection('batch2_copy');
    Schema::clearResolvedInstance('db.schema');
    if ((new User)->getConnection()->getDatabaseName() !== $name || User::query()->getConnection()->getName() !== 'batch2_copy') {
        throw new RuntimeException('Model isolation failed before fixture creation.');
    }
};

if (($argv[1] ?? null) === 'worker') {
    $connectCopy($argv[2]);
    $mode = $argv[3];
    $seller = User::findOrFail((int) $argv[4]);
    $product = Product::findOrFail((int) $argv[5]);
    $start = (float) $argv[6];
    while (microtime(true) < $start) {
        usleep(10000);
    }
    try {
        if ($mode === 'sale') {
            app(ProductSaleService::class)->save($seller, ['product_id' => $product->id, 'kind' => 'percent', 'value' => 20,
                'starts_at' => $argv[7], 'ends_at' => $argv[8], 'operation_key' => (string) Str::uuid()]);
        } elseif ($mode === 'cap') {
            DB::transaction(function () use ($seller, $product) {
                User::whereKey($seller->id)->lockForUpdate()->firstOrFail();
                Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                app(ProductPurchaseLimitService::class)->assertDemand($seller, [$product->id => 1]);
                $order = Order::forceCreate(['buyer_id' => $seller->id, 'seller_id' => $product->user_id, 'order_number' => 'B2-'.Str::random(15),
                    'recipient_name' => 'Isolated fixture', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Isolated fixture',
                    'subtotal' => 450, 'total_amount' => 500, 'shipping_fee' => 50, 'commission_fee' => 45, 'status' => 'PLACED', 'payment_method' => 'COD']);
                OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Isolated fixture', 'quantity' => 1, 'unit_price' => 450, 'item_total' => 450]);
            });
        } elseif ($mode === 'funding') {
            app(ClaimedVoucherEditService::class)->update($seller, Voucher::findOrFail((int) $argv[7]), ['usage_limit' => 4]);
        } else {
            throw new RuntimeException('Unknown worker mode.');
        }
        echo json_encode(['result' => 'committed'], JSON_THROW_ON_ERROR);
    } catch (ValidationException $error) {
        echo json_encode(['result' => 'rejected', 'fields' => array_keys($error->errors())], JSON_THROW_ON_ERROR);
    }
    exit;
}

$name = 'ezicart_b2_check_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
$quote = fn (string $value): string => '`'.str_replace('`', '``', $value).'`';
$tables = ['users', 'products', 'product_variations', 'orders', 'order_items', 'vouchers', 'voucher_budgets', 'voucher_claims', 'financial_accounts', 'ledger_transactions', 'ledger_entries', 'administrative_audits'];
$counts = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => $source->table($table)->count()])->all();
$before = $counts();
$created = false;
$workers = [];
$result = [];
try {
    $source->statement('CREATE DATABASE '.$quote($name));
    $created = true;
    foreach ($tables as $table) {
        $source->statement('CREATE TABLE '.$quote($name).'.'.$quote($table).' LIKE '.$quote($source->getDatabaseName()).'.'.$quote($table));
        $columns = $source->table('information_schema.columns')->where('table_schema', $source->getDatabaseName())->where('table_name', $table)
            ->whereRaw("COALESCE(GENERATION_EXPRESSION, '') = ''")->orderBy('ordinal_position')->selectRaw('COLUMN_NAME AS column_name')->get()->pluck('column_name')->all();
        $columnSql = implode(', ', array_map($quote, $columns));
        $source->statement('INSERT INTO '.$quote($name).'.'.$quote($table).' ('.$columnSql.') SELECT '.$columnSql.' FROM '.$quote($source->getDatabaseName()).'.'.$quote($table).' LIMIT 10000');
    }
    $connectCopy($name);
    $migration = require dirname(__DIR__).'/database/migrations/2026_11_14_214751_add_scheduled_sales_voucher_scope_and_purchase_limits.php';
    if (Schema::hasColumn('products', 'purchase_limit')) {
        $migration->down();
    }
    $copyBefore = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $migration->up();
    $migration->down();
    $migration->up();
    // Current sale validation also checks flash conflicts in the isolated copy.
    $flashMigration = require dirname(__DIR__).'/database/migrations/2026_11_14_224226_add_flash_sales_bundles_and_reminders.php';
    $flashMigration->up();
    $result['mysql_migration_up_down_reapply'] = Schema::hasTable('product_sales') && Schema::hasColumn('order_items', 'pricing_snapshot')
        && $copyBefore === collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $user = fn ($role) => User::forceCreate(['email' => Str::uuid().'@example.invalid', 'password' => 'IsolatedFixture123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Isolated', 'last_name' => 'Fixture', 'contact_no' => '09123456789']);
    $seller = $user('seller');
    $buyer = $user('buyer');
    $product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Isolated fixture', 'category' => 'Home and Garden', 'price' => 450, 'stock' => 20, 'purchase_limit' => 1]);
    $race = function (string $mode, int $actor, array $extra = []) use ($name, $product, &$workers): array {
        $start = (string) (microtime(true) + 2);
        $workers = [];
        for ($i = 0; $i < 2; $i++) {
            $process = new Process([PHP_BINARY, __FILE__, 'worker', $name, $mode, (string) $actor, (string) $product->id, $start, ...$extra], dirname(__DIR__));
            $process->setTimeout(30)->start();
            $workers[] = $process;
        }
        $outcomes = [];
        foreach ($workers as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Isolated MySQL worker failed: '.$mode);
            }
            $outcomes[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $outcomes;
    };
    $result['sale_workers'] = $race('sale', $seller->id, [now()->addMinutes(10)->toIso8601String(), now()->addHour()->toIso8601String()]);
    $result['one_overlapping_sale'] = ProductSale::where('product_id', $product->id)->count() === 1
        && count(array_filter($result['sale_workers'], fn ($row) => $row['result'] === 'committed')) === 1;
    $result['cap_workers'] = $race('cap', $buyer->id);
    $result['one_capped_purchase'] = (int) OrderItem::where('product_id', $product->id)->sum('quantity') === 1
        && count(array_filter($result['cap_workers'], fn ($row) => $row['result'] === 'committed')) === 1;
    $ledger = app(FinancialLedgerService::class);
    $wallet = $ledger->wallet($seller);
    $fund = $ledger->account('isolated:'.Str::uuid(), 'sandbox', null, 'source', true);
    $ledger->transfer('isolated:'.Str::uuid(), 'sandbox_funding', $fund, $wallet, 10000, $seller);
    $voucher = Voucher::create(['seller_id' => $seller->id, 'code' => 'B2'.Str::upper(Str::random(12)), 'type' => 'fixed', 'value' => 10, 'usage_limit' => 2, 'is_active' => true, 'terms_version' => 2]);
    app(VoucherFundingService::class)->reserve($voucher, $seller);
    $result['funding_workers'] = $race('funding', $seller->id, [(string) $voucher->id]);
    $result['one_budget_topup'] = (int) $wallet->fresh()->balance_cents === 6000 && (int) $voucher->fresh()->budget->maximum_cents === 4000
        && DB::table('ledger_transactions')->where('idempotency_key', 'like', 'voucher:'.$voucher->id.':extend:%')->count() === 1;
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
    DB::setDefaultConnection($original);
    Schema::clearResolvedInstance('db.schema');
    DB::purge('batch2_copy');
    if ($created) {
        if (! preg_match('/\Aezicart_b2_check_\d{14}_[a-f0-9]{8}\z/', $name) || $name === $source->getDatabaseName()) {
            throw new RuntimeException('Unsafe temporary schema cleanup target.');
        }
        $source->statement('DROP DATABASE '.$quote($name));
    }
}
$result['configured_business_counts_unchanged'] = $before === $counts();
$result['scope'] = 'Isolated MySQL DDL and two-worker sale, cap-guard and funding checks; cap check uses placement locks/service, not full financial checkout.';
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
foreach (['mysql_migration_up_down_reapply', 'one_overlapping_sale', 'one_capped_purchase', 'one_budget_topup', 'configured_business_counts_unchanged'] as $check) {
    if (! ($result[$check] ?? false)) {
        exit(1);
    }
}
