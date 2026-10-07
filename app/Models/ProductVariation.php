<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type',
        'value',
        'price_adjustment',
        'stock',
        'image_path',
        'sku',
        'price',
    ];

    protected $casts = [
        'price_adjustment' => 'float',
        'price' => 'float',
        'stock' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Compute regular original price of this variation before discounts.
     */
    public function getOriginalPriceAttribute(): float
    {
        if ($this->price !== null && $this->price > 0) {
            return (float) $this->price;
        }

        $base = $this->product ? (float) $this->product->price : 0.00;

        return max(0.00, round($base + (float) ($this->price_adjustment ?? 0.00), 2));
    }

    /**
     * Compute final discounted price for this variation by applying the parent product's discount.
     */
    public function getDiscountedPriceAttribute(): float
    {
        $original = $this->original_price;

        if (! $this->product || ! $this->product->has_discount) {
            return $original;
        }

        if ($this->product->discount_type === 'percent') {
            $deduction = $original * ((float) $this->product->discount_value / 100);

            return max(0.00, round($original - $deduction, 2));
        }

        if ($this->product->discount_type === 'fixed') {
            return max(0.00, round($original - (float) $this->product->discount_value, 2));
        }

        return $original;
    }

    /**
     * Compute effective price for this variation (alias to discounted_price).
     */
    public function getEffectivePriceAttribute(): float
    {
        return $this->discounted_price;
    }
}
