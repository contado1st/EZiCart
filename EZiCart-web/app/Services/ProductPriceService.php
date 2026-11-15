<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariation;

class ProductPriceService
{
    public function resolve(Product $product, ?ProductVariation $variation = null, bool $flash = true): array
    {
        return $this->amount($product, CheckoutPricingService::cents($product->price) + ($variation ? CheckoutPricingService::cents($variation->price_adjustment) : 0), $flash);
    }

    public function amount(Product $product, int $original, bool $flash = true): array
    {
        $sale = $product->currentSale;
        if ($sale && ($sale->ended_at || $sale->starts_at->isFuture() || $sale->ends_at->lte(now()))) {
            $sale = null;
        }
        $effective = $sale ? ($sale->kind === 'percent' ? intdiv($original * (100 - $sale->value) + 50, 100) : (int) $sale->value) : $original;
        // A later catalog edit must never turn an offer into a markup or invalid price.
        if ($effective < 100 || $effective >= $original) {
            $effective = $original;
            $sale = null;
        }

        $slot = $flash ? $product->currentFlashSale : null;
        if ($slot && ($slot->ended_at || $slot->starts_at->isFuture() || $slot->ends_at->lte(now()) || $slot->allocated_quantity >= $slot->quantity_limit || $slot->unit_cents >= $effective)) {
            $slot = null;
        }

        return ['original_unit_cents' => $original, 'unit_cents' => $slot ? (int) $slot->unit_cents : $effective, 'sale_id' => $sale?->id, 'sale_version' => $sale?->version];
    }

    public static function regularMinimumSql(): string
    {
        $original = Product::MINIMUM_PURCHASABLE_PRICE_SQL;
        $at = now()->format('Y-m-d H:i:s');
        $candidate = "(CASE WHEN ps.kind = 'percent' THEN ROUND(($original) * (100 - ps.value) / 100.0, 2) ELSE ps.value / 100.0 END)";

        return "COALESCE((SELECT $candidate FROM product_sales ps WHERE ps.product_id = products.id AND ps.ended_at IS NULL AND ps.starts_at <= '$at' AND ps.ends_at > '$at' AND $candidate >= 1 AND $candidate < ($original) ORDER BY ps.id DESC LIMIT 1), $original)";
    }

    public static function minimumSql(): string
    {
        $regular = self::regularMinimumSql();
        $at = now()->format('Y-m-d H:i:s');

        return "COALESCE((SELECT fs.unit_cents / 100.0 FROM flash_sales fs WHERE fs.product_id = products.id AND fs.ended_at IS NULL AND fs.starts_at <= '$at' AND fs.ends_at > '$at' AND fs.allocated_quantity < fs.quantity_limit AND fs.unit_cents / 100.0 < ($regular) ORDER BY fs.id DESC LIMIT 1), $regular)";
    }
}
