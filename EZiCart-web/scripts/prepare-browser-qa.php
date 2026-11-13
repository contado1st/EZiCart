<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDraft;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__).'/vendor/autoload.php';
$qaRoot = dirname(__DIR__).'/storage/framework/testing/browser-qa-20261113';
if (! is_dir($qaRoot)) {
    mkdir($qaRoot, 0700, true);
}
if (! is_file($qaRoot.'/database.sqlite')) {
    touch($qaRoot.'/database.sqlite');
}
$app = require __DIR__.'/browser-qa-bootstrap.php';
$app->make(Kernel::class)->bootstrap();
abort_unless(config('database.default') === 'sqlite' && config('database.connections.sqlite.database') === $qaRoot.'/database.sqlite', 500);
Artisan::call('migrate', ['--force' => true]);
if (User::where('email', 'seller@browser-qa.invalid')->exists()) {
    echo "Existing isolated browser QA fixtures retained.\n";
    exit;
}
$make = fn ($role, $status = 'approved') => User::forceCreate(['email' => ($status === 'pending' ? 'pending' : $role).'@browser-qa.invalid', 'password' => 'BrowserQA123!',
    'first_name' => 'Browser', 'last_name' => 'Reviewer', 'contact_no' => '09123456789', 'role' => $role, 'status' => $status, 'email_verified_at' => now(),
    'business_name' => 'Browser QA Shop', 'region' => 'CALABARZON', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Synthetic QA address']);
$seller = $make('seller');
$buyer = $make('buyer');
$make('seller', 'pending');
$make('ecommerce_admin');
$image = imagecreatetruecolor(400, 400);
$color = imagecolorallocate($image, 245, 199, 214);
imagefill($image, 0, 0, $color);
$ink = imagecolorallocate($image, 80, 35, 55);
imagestring($image, 5, 125, 190, 'QA PRODUCT', $ink);
ob_start();
imagepng($image);
$png = ob_get_clean();
Storage::disk('public')->put('browser-qa/bag.png', $png);
$product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'QA variant travel bag', 'description' => 'Synthetic browser-check product. No real sale or delivery.', 'category' => 'Pet Supplies', 'price' => 199, 'stock' => 12, 'weight_grams' => 1000, 'compliance_status' => 'approved', 'is_archived' => false, 'image_path' => 'browser-qa/bag.png']);
foreach ([0, 50, 100] as $index => $adjustment) {
    $product->variations()->create(['type' => 'Size', 'value' => ['Small', 'Medium', 'Large'][$index], 'price_adjustment' => $adjustment, 'stock' => 4, 'sku' => 'QA-'.$index]);
}
Storage::disk('private')->put('product-drafts/qa/cover.png', $png);
ProductDraft::create(['user_id' => $seller->id, 'payload' => ['name' => 'QA saved private draft', 'category' => 'Pet Supplies', 'price' => 100, 'stock' => 2, 'weight_kg' => 1, 'variations' => []],
    'uploads' => ['image' => ['path' => 'product-drafts/qa/cover.png', 'name' => 'qa-cover.png', 'variant_key' => null]]]);
$order = Order::forceCreate(['order_number' => 'BROWSER-QA-001', 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'recipient_name' => 'Browser Reviewer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Synthetic QA address', 'subtotal' => 199, 'total_amount' => 249, 'payment_method' => 'COD', 'status' => 'COMPLETED']);
OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'variation_info' => 'Size: Small', 'quantity' => 1, 'unit_price' => 199, 'item_total' => 199, 'purchased_image_path' => 'browser-qa/bag.png']);
echo "Isolated browser QA ready on loopback port 8017. Synthetic account password: BrowserQA123!\n";
