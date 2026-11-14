<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Review extends Model
{
    use HasFactory;

    public function reply(): HasOne
    {
        return $this->hasOne(ReviewReply::class);
    }

    protected $hidden = ['purchase_context', 'revision_version'];

    protected function casts(): array
    {
        return ['purchase_context' => 'array', 'revision_version' => 'integer'];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ReviewPhoto::class)->whereNull('removed_at')->orderBy('id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ReviewRevision::class)->orderByDesc('version');
    }

    public function getPublicBuyerNameAttribute(): string
    {
        return $this->buyer ? mb_substr($this->buyer->first_name, 0, 1).'***'.mb_substr($this->buyer->last_name, -1) : 'Verified buyer';
    }

    public function getPurchasedVariantsAttribute(): array
    {
        return $this->purchase_context['variants'] ?? $this->order?->items->where('product_id', $this->product_id)
            ->map(fn ($item) => $item->variation_info ?: 'Standard item')->unique()->values()->all() ?? [];
    }

    protected $fillable = [
        'order_id',
        'product_id',
        'buyer_id',
        'rating',
        'comment',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}
