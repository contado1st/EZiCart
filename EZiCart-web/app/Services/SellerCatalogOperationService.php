<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDraft;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SellerCatalogOperationService
{
    public function __construct(private AdministrativeAuditService $audits, private ProductMediaService $media) {}

    private function operation(User $seller, string $key, array $payload, Closure $action): array
    {
        return DB::transaction(function () use ($seller, $key, $payload, $action) {
            $fresh = User::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            abort_unless($fresh->role === 'seller' && $fresh->status === 'approved', 403);
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $existing = DB::table('seller_catalog_operations')->where('seller_id', $seller->id)->where('operation_key', $key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'This action token was already used with different details. Refresh the page.');

                return json_decode($existing->result, true, flags: JSON_THROW_ON_ERROR);
            }
            $result = $action();
            DB::table('seller_catalog_operations')->insert(['seller_id' => $seller->id, 'operation_key' => $key, 'request_hash' => $hash, 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

            return $result;
        });
    }

    public function duplicate(User $seller, Product $source, string $key): ProductDraft
    {
        $staged = [];
        try {
            $result = $this->operation($seller, $key, ['duplicate', $source->id], function () use ($seller, $source, &$staged) {
                $source = Product::whereKey($source->id)->where('user_id', $seller->id)->lockForUpdate()->firstOrFail();
                $source->load('media', 'variations');
                $payload = $source->only(['name', 'category', 'description', 'price', 'stock', 'length_mm', 'width_mm', 'height_mm', 'variations_enabled']);
                $payload['weight_kg'] = $source->weight_grams ? $source->weight_grams / 1000 : null;
                $payload['variations_present'] = true;
                $payload['variations'] = [];
                $uploads = [];
                $copy = function (?string $path, string $field, ?string $variantKey = null) use ($seller, &$staged, &$uploads) {
                    if (! $path) {
                        return;
                    }
                    abort_unless(Storage::disk('public')->exists($path), 422, 'A product file is missing. Replace it before duplicating.');
                    $target = 'product-drafts/'.$seller->id.'/'.Str::uuid().'.'.pathinfo($path, PATHINFO_EXTENSION);
                    $stream = Storage::disk('public')->readStream($path);
                    if (! is_resource($stream)) {
                        throw new \RuntimeException('The original product file could not be read.');
                    }
                    $staged[] = $target;
                    try {
                        if (! Storage::disk('private')->put($target, $stream)) {
                            throw new \RuntimeException('The private copy could not be saved.');
                        }
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                    $uploads[$field] = ['path' => $target, 'name' => basename($path), 'variant_key' => $variantKey];
                };
                $copy($source->image_path, 'image');
                $image = 0;
                foreach ($source->media as $item) {
                    $copy($item->path, $item->kind === 'video' ? 'product_video' : 'gallery_images.'.$image++);
                }
                foreach ($source->variations->where('is_active', true)->values() as $index => $variant) {
                    $payload['variations'][] = [...$variant->only(['type', 'value', 'price_adjustment', 'stock']), 'sku' => null];
                    $copy($variant->image_path, 'variations.'.$index.'.image', trim($variant->type).':'.trim($variant->value));
                }
                $draft = ProductDraft::create(['user_id' => $seller->id, 'payload' => $payload, 'uploads' => $uploads]);
                $this->audits->record($seller, $draft, 'product_duplicated_to_draft', ['source_product_id' => $source->id], ['draft_id' => $draft->id], 'Independent private copy created. SKU and publication identities are reset.');

                return ['draft_id' => $draft->id];
            });

            return ProductDraft::where('user_id', $seller->id)->findOrFail($result['draft_id']);
        } catch (\Throwable $error) {
            app(ProductDraftService::class)->deleteFiles($staged);
            throw $error;
        }
    }

    public function bulk(User $seller, array $ids, string $action, string $key): array
    {
        sort($ids, SORT_NUMERIC);

        return $this->operation($seller, $key, [$action, $ids], function () use ($seller, $ids, $action) {
            $products = Product::whereIn('id', $ids)->where('user_id', $seller->id)->orderBy('id')->lockForUpdate()->get();
            abort_unless($products->count() === count($ids), 404, 'One or more selected products are not in your inventory.');
            $results = [];
            foreach ($products as $product) {
                if ($action === 'deactivate') {
                    $this->availability($seller, $product, false);
                    $outcome = 'Sales paused';
                } elseif (OrderItem::where('product_id', $product->id)->whereHas('order', fn ($q) => $q->whereNotIn('status', ['COMPLETED', 'CANCELLED', 'RETURNED_TO_SELLER']))->exists()) {
                    $outcome = 'Skipped: unfinished orders';
                } else {
                    $outcome = $this->remove($seller, $product) ? 'Archived: history retained' : 'Removed';
                }
                $results[] = ['product_id' => $product->id, 'name' => $product->name, 'outcome' => $outcome];
            }

            return $results;
        });
    }

    public function availability(User $seller, Product $product, bool $active): void
    {
        abort_unless($product->user_id === $seller->id, 403);
        DB::transaction(function () use ($seller, $product, $active) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = ['is_seller_active' => (bool) $locked->is_seller_active];
            $locked->forceFill(['is_seller_active' => $active])->save();
            $this->audits->record($seller, $locked, 'seller_product_availability', $before, ['is_seller_active' => $active], 'Seller changed sales availability; compliance approval is retained.');
        });
    }

    public function remove(User $seller, Product $product): bool
    {
        abort_unless($product->user_id === $seller->id, 403);

        return DB::transaction(function () use ($seller, $product) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $archived = $product->complianceEvents()->exists() || OrderItem::where('product_id', $product->id)->exists() || DB::table('voucher_products')->where('product_id', $product->id)->exists()
                || DB::table('flash_sales')->where('product_id', $product->id)->exists() || DB::table('bundle_products')->where('product_id', $product->id)->exists() || DB::table('bundle_deals')->where('gift_product_id', $product->id)->exists();
            $this->audits->record($seller, $product, 'seller_product_removed', $product->only(['name', 'is_archived']), ['archived' => $archived], 'Seller removed listing; order and compliance history are retained.');
            if ($archived) {
                $product->update(['is_archived' => true]);
            } else {
                $paths = [$product->image_path, ...$product->media()->pluck('path')->all(), ...$product->variations()->pluck('image_path')->all()];
                $product->delete();
                DB::afterCommit(fn () => $this->media->deleteFiles($paths));
            }

            return $archived;
        });
    }
}
