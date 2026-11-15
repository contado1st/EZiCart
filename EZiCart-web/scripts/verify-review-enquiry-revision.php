<?php

use App\Models\DirectMessage;
use App\Models\Review;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Counts/schema and empty-owner queries only. No identities, feedback, files or messages are emitted.
$unique = fn (string $table, array $columns): bool => collect(Schema::getIndexes($table))
    ->contains(fn ($index) => $index['unique'] && $index['columns'] === $columns);
$emptySeller = Review::whereHas('order', fn ($q) => $q->where('seller_id', 0))->whereHas('photos')->count();
$emptyPath = DirectMessage::where('product_context->image_path', 'nonexistent-audit-image')->exists();
echo json_encode(['driver' => DB::getDriverName(), 'review_purchase_context' => Schema::hasColumn('reviews', 'purchase_context'),
    'review_version' => Schema::hasColumn('reviews', 'revision_version'), 'message_product_context' => Schema::hasColumn('direct_messages', 'product_context'),
    'review_order_product_unique' => $unique('reviews', ['order_id', 'product_id']), 'review_version_unique' => $unique('review_revisions', ['review_id', 'version']),
    'reviews' => Review::count(), 'reviews_without_recorded_context' => Review::whereNull('purchase_context')->count(),
    'photo_rows' => DB::table('review_photos')->count(), 'revision_rows' => DB::table('review_revisions')->count(),
    'product_enquiry_messages' => DirectMessage::whereNotNull('product_context')->count(),
    'empty_seller_photo_reviews' => $emptySeller, 'nonexistent_image_reference' => $emptyPath,
], JSON_PRETTY_PRINT).PHP_EOL;
