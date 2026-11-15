<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariation extends Model
{
    use HasFactory;

    protected $hidden = ['sku', 'seller_id', 'canonical_sku'];

    protected static function booted(): void
    {
        static::saving(function (self $variation): void {
            $variation->seller_id = $variation->product()->value('user_id');
            if ($variation->isDirty('sku')) {
                $variation->sku = filled($variation->sku) ? trim($variation->sku) : null;
            }
        });
    }

    protected $fillable = [
        'product_id',
        'type',
        'value',
        'price_adjustment',
        'stock',
        'sku', 'image_path',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
