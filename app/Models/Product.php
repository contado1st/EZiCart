<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'category',
        'price',
        'stock',
        'image_path',
        'is_archived',
        'status',
        'rejection_reason',
        'discount_type',
        'discount_value',
    ];

    protected $casts = [
        'price' => 'float',
        'stock' => 'integer',
        'is_archived' => 'boolean',
        'discount_value' => 'float',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function activeVoucher(): HasOne
    {
        return $this->hasOne(Voucher::class)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latestOfMany();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function getAverageRatingAttribute(): float
    {
        return round((float) $this->reviews()->avg('rating'), 1);
    }

    public function getReviewCountAttribute(): int
    {
        return $this->reviews()->count();
    }

    /**
     * Check if product has an active valid discount.
     */
    public function getHasDiscountAttribute(): bool
    {
        return ! empty($this->discount_type)
            && $this->discount_value !== null
            && (float) $this->discount_value > 0;
    }

    /**
     * Calculate effective price after applying product discount.
     */
    public function getDiscountedPriceAttribute(): float
    {
        if (! $this->has_discount) {
            return (float) $this->price;
        }

        if ($this->discount_type === 'percent') {
            $deduction = (float) $this->price * ((float) $this->discount_value / 100);

            return max(0.00, round((float) $this->price - $deduction, 2));
        }

        if ($this->discount_type === 'fixed') {
            return max(0.00, round((float) $this->price - (float) $this->discount_value, 2));
        }

        return (float) $this->price;
    }

    /**
     * Calculate discount percentage for display.
     */
    public function getDiscountPercentAttribute(): float
    {
        if (! $this->has_discount || (float) $this->price <= 0) {
            return 0.0;
        }

        if ($this->discount_type === 'percent') {
            return round((float) $this->discount_value, 0);
        }

        if ($this->discount_type === 'fixed') {
            return round(((float) $this->discount_value / (float) $this->price) * 100, 0);
        }

        return 0.0;
    }

    /**
     * Scope query to only include admin-approved products.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }
}
