<?php

namespace App\Models;

use App\Services\CheckoutPricingService;
use App\Services\VoucherEligibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Voucher extends Model
{
    use HasFactory;

    protected $hidden = ['recipient_limit', 'claimed_count', 'per_buyer_limit', 'disabled_reason'];

    protected $fillable = [
        'seller_id',
        'code',
        'type',
        'value',
        'min_spend',
        'usage_limit',
        'used_count',
        'expires_at',
        'starts_at',
        'is_active',
        'max_discount',
        'discount_target',
        'recipient_limit', 'claimed_count', 'per_buyer_limit',
        'visibility', 'product_scoped', 'terms_version',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'starts_at' => 'datetime',
        'is_active' => 'boolean',
        'product_scoped' => 'boolean',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'voucher_products');
    }

    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }

    public function budget(): HasOne
    {
        return $this->hasOne(VoucherBudget::class);
    }

    public function isValidForAmount(float $subtotal): bool
    {
        return app(VoucherEligibilityService::class)->reason($this, CheckoutPricingService::cents($subtotal)) === null;
    }

    public function scopeAvailableNow(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->where(fn ($q) => $q->whereDoesntHave('budget')->orWhereHas('budget', fn ($budget) => $budget->where('status', 'RESERVED')
                ->whereHas('reserveAccount', fn ($account) => $account->where('frozen', false)->whereColumn('balance_cents', '>=', 'voucher_budgets.cap_cents'))));
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($this->type === 'percent') {
            return min($subtotal, $this->max_discount ?? $subtotal, round(($subtotal * ($this->value / 100)), 2));
        }

        return min($this->value, $this->max_discount ?? $subtotal, $subtotal);
    }
}
