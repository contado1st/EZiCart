<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'description', 'category',
        'price', 'stock', 'image_path', 'is_archived',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
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
}
