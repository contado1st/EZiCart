<?php

use App\Http\Controllers\Admin\AdminDisputeController;
use App\Http\Controllers\Admin\LogisticsAdminController;
use App\Http\Controllers\Api\CartApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Logistics\LogisticsController;
use App\Http\Controllers\Seller\SellerController;
use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\CheckoutPricingService;
use App\Services\DeliveryCodeService;
use App\Services\DirectMessagingService;
use App\Services\LogisticsExceptionService;
use App\Services\OrderAreaService;
use App\Services\OrderFinancialService;
use App\Services\OrderTransitionService;
use App\Services\ParcelLoadService;
use App\Services\ParcelTransferService;
use App\Services\PlacePurchaseService;
use App\Services\QrCodeService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Read application behavior using disposable data only. Never migrates the configured MySQL database.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null,
    'mail.default' => 'array', 'queue.default' => 'sync', 'cache.default' => 'array', 'session.driver' => 'array',
    'hashing.driver' => 'bcrypt', 'hashing.bcrypt.rounds' => 4,
]);
DB::purge('sqlite');
if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
    throw new LogicException('Audit probes require an isolated in-memory SQLite database.');
}
Artisan::call('migrate', ['--force' => true]);
Notification::fake();

$sequence = 0;
$user = function (string $role, array $extra = []) use (&$sequence): User {
    return User::forceCreate(array_merge(['email' => 'audit-'.(++$sequence).'@example.test', 'password' => 'AuditOnly123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Audit', 'last_name' => $role, 'business_name' => 'Audit '.$role, 'contact_no' => '09123456789'], $extra));
};
$source = $user('sorting_center');
$destination = $user('sorting_center');
$admin = $user('logistics_admin');
$commerce = $user('ecommerce_admin');
$seller = $user('seller');
$buyer = $user('buyer');
$local = $user('courier', ['logistics_owner_id' => $source->id]);
$driver = $user('courier', ['logistics_owner_id' => $source->id, 'hub_transfer_only' => true, 'vehicle_capacity_grams' => 100000, 'vehicle_capacity_verified_at' => now(), 'vehicle_capacity_verified_by' => $admin->id]);
$area = Area::create(['name' => 'Audit destination', 'code' => 'AUDIT-DEST', 'is_active' => true, 'sorting_center_id' => $destination->id]);
$sourceArea = Area::create(['name' => 'Audit source', 'code' => 'AUDIT-SRC', 'is_active' => true, 'sorting_center_id' => $source->id]);
$driver->serviceAreas()->attach($sourceArea->id, ['is_active' => true, 'is_primary' => true]);
AreaMunicipality::create(['area_id' => $area->id, 'province' => 'Audit Province', 'municipality' => 'Audit Town', 'province_normalized' => 'audit province', 'municipality_normalized' => 'audit town']);
$orderSequence = 0;
$order = function (array $extra = []) use ($seller, $buyer, $area, &$orderSequence): Order {
    return Order::forceCreate(array_merge(['order_number' => 'AUDIT-'.(++$orderSequence), 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'recipient_name' => 'Audit Buyer', 'recipient_contact' => '09123456789', 'province' => 'Audit Province', 'municipality' => 'Audit Town', 'barangay' => 'Audit Barangay', 'street_address' => 'Audit street', 'subtotal' => 100, 'total_amount' => 150, 'shipping_fee' => 50, 'payment_method' => 'COD', 'status' => 'SORTED', 'destination_area_id' => $area->id, 'parcel_weight_grams' => 1000], $extra));
};
$results = [];

$pickup = $order(['status' => 'PICKED_UP', 'pickup_courier_id' => $local->id]);
Auth::setUser($source);
try {
    $app->make(LogisticsController::class)->receiveParcel($pickup, $app->make(OrderTransitionService::class), $app->make(OrderAreaService::class));
    $results['origin_hub_intake'] = ['status' => 'accepted'];
} catch (HttpException $exception) {
    $results['origin_hub_intake'] = ['http_status' => $exception->getStatusCode(), 'message' => $exception->getMessage(), 'order_status' => $pickup->fresh()->status];
}

$parcel = $order(['sorting_center_id' => $source->id]);
$transfers = $app->make(ParcelTransferService::class);
$transfer = $transfers->plan($parcel, $admin, $destination, 'Isolated audit transfer.');
$transfers->release($transfer, $source, 'EZP:'.$parcel->parcel_code, 'AUDIT-MANIFEST', $driver);
$messages = $app->make(DirectMessagingService::class);
$results['driver_messaging'] = ['transfer_status' => $transfer->fresh()->status, 'can_contact_source' => $messages->canContact($driver, $source), 'can_contact_destination' => $messages->canContact($driver, $destination)];
try {
    $messages->authorizeOrder($parcel, $driver);
    $results['driver_messaging']['parcel_access'] = 'allowed';
} catch (HttpException $exception) {
    $results['driver_messaging']['parcel_access_http_status'] = $exception->getStatusCode();
}
$results['driver_analytics'] = ['assigned_transfer_visible' => $app->make(AnalyticsService::class)->scope($driver)->whereKey($parcel->id)->exists()];
$driver->forceFill(['status' => 'suspended'])->save();
$app->make(LogisticsExceptionService::class)->openForRiderSuspension($driver, $admin, 'Audit suspended hub driver.');
$results['suspended_hub_driver'] = ['transfer_status' => $transfer->fresh()->status, 'recovery_exceptions' => DB::table('logistics_exceptions')->where('order_id', $parcel->id)->count()];

$completed = $order(['status' => 'COMPLETED', 'discount_amount' => 20, 'commission_fee' => 10, 'total_amount' => 130]);
$product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Audit item', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 5]);
Review::create(['order_id' => $completed->id, 'product_id' => $product->id, 'buyer_id' => $buyer->id, 'rating' => 1, 'comment' => 'Audit rating.']);
Auth::setUser($seller);
$stats = $app->make(SellerController::class)->dashboard()->getData()['stats'];
$finance = $app->make(OrderFinancialService::class)->initialize($completed);
$results['seller_dashboard'] = ['shown_rating' => $stats['rating'], 'actual_average_rating' => (float) Review::avg('rating'), 'shown_net_sales' => (float) $stats['total_sales'], 'recorded_seller_payable' => $finance->seller_payable_cents / 100];

$returnRider = $user('courier');
$delivered = $order(['status' => 'COMPLETED', 'delivery_courier_id' => $returnRider->id, 'delivered_at' => now()]);
$dispute = Dispute::create(['order_id' => $delivered->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'reason' => 'Damaged Item', 'description' => 'Isolated audit of return authorization.', 'status' => 'PENDING']);
Auth::setUser($commerce);
$request = Request::create('/audit/dispute', 'PATCH', ['status' => 'REFUND_APPROVED', 'admin_notes' => 'Audit return authorization only.']);
$request->setUserResolver(fn () => $commerce);
$app->make(AdminDisputeController::class)->resolve($request, $dispute, $app->make(OrderTransitionService::class), $app->make(OrderFinancialService::class));
$results['return_approval'] = ['order_status_after_approval' => $delivered->fresh()->status, 'vehicle_verified' => $returnRider->vehicle_capacity_verified_at !== null, 'reserved_parcels' => $app->make(ParcelLoadService::class)->summary($returnRider)['count'], 'refund_status' => $delivered->refundExecutions()->first()?->status];

$apiRequest = Request::create('/api/orders/'.$completed->id, 'GET');
$apiRequest->setUserResolver(fn () => $buyer);
try {
    $response = $app->make(OrderApiController::class)->show($completed->id, $apiRequest, $app->make(DeliveryCodeService::class), $app->make(QrCodeService::class));
    $results['buyer_api_order_detail'] = ['http_status' => $response->getStatusCode()];
} catch (RelationNotFoundException $exception) {
    $results['buyer_api_order_detail'] = ['exception' => class_basename($exception), 'message' => $exception->getMessage()];
}

$product->forceFill(['compliance_status' => 'approved'])->save();
$otherSeller = $user('seller');
$otherProduct = Product::forceCreate(['user_id' => $otherSeller->id, 'name' => 'Other audit item', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 5, 'compliance_status' => 'approved']);
$mixed = [['product_id' => $product->id, 'quantity' => 1], ['product_id' => $otherProduct->id, 'quantity' => 1]];
$quoteRequest = Request::create('/api/cart/quote', 'POST', ['items' => $mixed]);
$quoteResponse = $app->make(CartApiController::class)->quote($quoteRequest, $app->make(CheckoutPricingService::class));
$results['mixed_shop_api_preview'] = ['quote_http_status' => $quoteResponse->getStatusCode(), 'quote_success' => $quoteResponse->getData(true)['success']];
try {
    $app->make(PlacePurchaseService::class)->place($buyer, (string) Str::uuid(), str_repeat('a', 64), $mixed, $quoteResponse->getData(true)['data']['quote_hash'], null, []);
    $results['mixed_shop_api_preview']['purchase'] = 'accepted';
} catch (ValidationException $exception) {
    $results['mixed_shop_api_preview']['purchase'] = 'rejected';
    $results['mixed_shop_api_preview']['message'] = collect($exception->errors())->flatten()->first();
}

$local->forceFill(['license_expires_at' => now()->subDay(), 'vehicle_capacity_grams' => 100000, 'vehicle_capacity_verified_at' => now()])->save();
DB::transaction(fn () => $app->make(ParcelLoadService::class)->assertFits($pickup, $local));
$results['expired_license_dispatch_guard'] = ['license_expired' => $local->license_expires_at->isPast(), 'guard_result' => 'allowed'];

$oldestPending = null;
for ($index = 0; $index < 26; $index++) {
    $pending = $user('courier', ['status' => 'pending', 'created_at' => now()->subMinutes(30 - $index)]);
    $oldestPending ??= $pending;
}
Auth::setUser($admin);
$adminRequest = Request::create('/logistics-admin/dashboard', 'GET');
$adminRequest->setUserResolver(fn () => $admin);
$applications = $app->make(LogisticsAdminController::class)->index($adminRequest)->getData()['pendingUsers'];
$results['logistics_application_queue'] = ['pending_count' => 26, 'shown_count' => $applications->count(), 'oldest_pending_visible' => $applications->contains('id', $oldestPending->id)];

echo json_encode(['database' => 'isolated SQLite :memory:', 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
