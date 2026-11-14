<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BundleDeal extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['operation_key', 'request_hash'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'bundle_products');
    }

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'gift_product_id');
    }
}
