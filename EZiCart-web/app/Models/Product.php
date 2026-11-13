<?php

namespace App\Models;

use App\Services\ProductCategoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    public const MINIMUM_PURCHASABLE_PRICE_SQL = '(products.price + COALESCE((SELECT MIN(pv.price_adjustment) FROM product_variations pv WHERE pv.product_id = products.id AND pv.is_active = 1 AND pv.stock > 0), 0))';

    protected $hidden = ['weight_grams', 'is_seller_active'];

    protected $attributes = ['is_seller_active' => true];

    protected $fillable = [
        'user_id', 'name', 'description', 'category',
        'price', 'stock', 'image_path', 'is_archived', 'weight_grams',
        'length_mm', 'width_mm', 'height_mm',
    ];

    public function scopeAvailableForMarketplace(Builder $query): Builder
    {
        return $query->where('compliance_status', 'approved')
            ->where('is_archived', false)->where('is_seller_active', true)
            ->where('stock', '>', 0)
            ->where(fn (Builder $products) => $products->whereDoesntHave('variations')
                ->orWhereHas('variations', fn (Builder $variations) => $variations->where('is_active', true)->where('stock', '>', 0)));
    }

    public function isListingVisible(): bool
    {
        return $this->compliance_status === 'approved' && ! $this->is_archived && (bool) $this->is_seller_active;
    }

    public function scopeWithVariantPrices(Builder $query): Builder
    {
        return $query->withMin(['variations as sellable_min_adjustment' => fn ($q) => $q->where('is_active', true)->where('stock', '>', 0)], 'price_adjustment')
            ->withMax(['variations as sellable_max_adjustment' => fn ($q) => $q->where('is_active', true)->where('stock', '>', 0)], 'price_adjustment');
    }

    public function getDisplayPriceLabelAttribute(): string
    {
        if ($this->relationLoaded('variations')) {
            $adjustments = $this->variations->where('is_active', true)->where('stock', '>', 0)->pluck('price_adjustment');
            $minimum = $adjustments->min() ?? 0;
            $maximum = $adjustments->max() ?? 0;
        } else {
            $minimum = $this->getAttribute('sellable_min_adjustment') ?? 0;
            $maximum = $this->getAttribute('sellable_max_adjustment') ?? 0;
        }
        $low = number_format((float) $this->price + (float) $minimum, 2);
        $high = number_format((float) $this->price + (float) $maximum, 2);

        return '₱'.$low.($low !== $high ? ' – ₱'.$high : '');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function complianceEvents(): HasMany
    {
        return $this->hasMany(ProductComplianceEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    protected function casts(): array
    {
        return ['compliance_reviewed_at' => 'datetime'];
    }

    public function getAverageRatingAttribute(): float
    {
        return round((float) $this->reviews()->avg('rating'), 1);
    }

    public function getReviewCountAttribute(): int
    {
        return $this->reviews()->count();
    }

    public function getCategoryLabelAttribute(): string
    {
        return app(ProductCategoryService::class)->label((string) $this->category);
    }
}
