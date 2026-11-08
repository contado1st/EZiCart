<?php

/** Rollback-only smoke check against the configured MySQL database. No DDL or commits. */

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutPricingService;
use App\Services\FinancialLedgerService;
use App\Services\OrderTransitionService;
use App\Services\PlacePurchaseService;
use App\Services\VoucherFundingService;
use App\Services\WalletFundingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (($argv[1] ?? '') !== '--rollback-only' || DB::connection()->getDriverName() !== 'mysql' || ! $app->environment(['local', 'testing'])) {
    throw new RuntimeException('Requires --rollback-only, local/testing, and the configured MySQL connection.');
}
config(['financial.mode' => 'sandbox', 'financial.sandbox_enabled' => true, 'hashing.driver' => 'bcrypt', 'hashing.bcrypt.rounds' => 4, 'cache.default' => 'array', 'mail.default' => 'array']);
Notification::fake();
$reference = 'rollback-'.Str::uuid();
$initial = DB::transactionLevel();
DB::beginTransaction();
$passed = false;
try {
    $users = [];
    foreach (['buyer', 'seller', 'ecommerce_admin'] as $role) {
        $users[$role] = User::forceCreate(['email' => $role.'-'.$reference.'@example.test', 'first_name' => 'Rollback', 'last_name' => 'Verification', 'password' => Str::random(40), 'contact_no' => '09123456789', 'role' => $role, 'status' => 'approved', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro']);
    }
    DB::table('commerce_settings')->updateOrInsert(['key' => 'shipping_model'], ['value' => 0]);
    DB::table('commerce_settings')->updateOrInsert(['key' => 'financial_sandbox_enabled'], ['value' => 1]);
    $funding = app(WalletFundingService::class);
    $ledger = app(FinancialLedgerService::class);
    foreach (['buyer' => 100000, 'seller' => 10000] as $role => $amount) {
        $intent = $funding->create($users[$role], $amount, (string) Str::uuid());
        $funding->simulate($intent, $users['ecommerce_admin'], 'paid');
    }
    $voucher = Voucher::create(['seller_id' => $users['seller']->id, 'code' => 'SMOKE'.Str::upper(Str::random(16)), 'type' => 'fixed', 'value' => 20, 'usage_limit' => 2, 'is_active' => true]);
    app(VoucherFundingService::class)->reserve($voucher, $users['seller']);
    $product = Product::forceCreate(['user_id' => $users['seller']->id, 'name' => 'Rollback-only financial verification', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 3, 'weight_grams' => 1000, 'compliance_status' => 'approved']);
    $cart = [['product_id' => $product->id, 'quantity' => 1]];
    $address = ['recipient_name' => 'Rollback Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'street_address' => 'Synthetic rollback check'];
    $quote = app(CheckoutPricingService::class)->quote($cart, $voucher, false, ['address' => $address, 'payment_method' => 'WALLET']);
    $purchase = app(PlacePurchaseService::class)->place($users['buyer'], (string) Str::uuid(), $reference, $cart, $quote['hash'], $voucher->id, $address, 'WALLET');
    $order = $purchase->orders()->firstOrFail();
    app(OrderTransitionService::class)->transition($order, $users['ecommerce_admin'], OrderStatus::Cancelled, 'admin_cancelled', null, 'Rollback-only database verification.');
    $reconciliation = $ledger->reconcile();
    $passed = $ledger->wallet($users['buyer'])->balance_cents === 100000 && $product->refresh()->stock === 3 && $reconciliation['balanced'];
    $result = ['passed' => $passed, 'driver' => 'mysql', 'scope' => 'rollback-only synthetic funding, reservation, wallet checkout, cancellation, stock and ledger reconciliation', 'reconciliation' => $reconciliation];
} finally {
    while (DB::transactionLevel() > $initial) {
        DB::rollBack();
    }
}
$result['synthetic_users_remaining'] = User::where('email', 'like', '%'.$reference.'@example.test')->count();
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed && $result['synthetic_users_remaining'] === 0 ? 0 : 1);
