<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'product_id',
        'code',
        'type',
        'value',
        'min_spend',
        'max_discount',
        'usage_limit',
        'used_count',
        'start_date',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'float',
        'min_spend' => 'float',
        'max_discount' => 'float',
        'start_date' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isValidForAmount(float $subtotal): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->start_date && Carbon::parse($this->start_date)->isFuture()) {
            return false;
        }

        if ($this->expires_at && Carbon::parse($this->expires_at)->isPast()) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        if ($subtotal < (float) $this->min_spend) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($this->type === 'percent') {
            $discount = round(($subtotal * ($this->value / 100)), 2);
        } else {
            $discount = min($this->value, $subtotal);
        }

        if ($this->max_discount !== null && (float) $this->max_discount > 0) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return round($discount, 2);
    }
}
