<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\BuyerOrderGroupingService;
use App\Services\ProductDiscoveryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Read-only SQL compatibility probe. A synthetic owner has no stored account or business data.
$buyer = new User;
$buyer->id = 0;
$groups = app(BuyerOrderGroupingService::class)->counts(Order::query()->where('buyer_id', 0));
$ranking = app(ProductDiscoveryService::class)->bestSelling(Product::query()->availableForMarketplace()->select('products.id')->whereRaw('1 = 0'))->limit(1)->get();
$unique = collect(Schema::getIndexes('product_wishlists'))->contains(fn ($index) => $index['unique'] && $index['columns'] === ['buyer_id', 'product_id']);

echo json_encode(['driver' => DB::getDriverName(), 'wishlist_table' => Schema::hasTable('product_wishlists'),
    'unique_buyer_product_index' => $unique, 'wishlist_rows' => DB::table('product_wishlists')->count(),
    'synthetic_owner_orders' => $groups['all'], 'synthetic_owner_wishlist' => $buyer->wishlistedProducts()->count(),
    'empty_ranking_query_rows' => $ranking->count(),
], JSON_PRETTY_PRINT).PHP_EOL;
