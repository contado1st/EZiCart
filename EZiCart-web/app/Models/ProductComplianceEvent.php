<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductComplianceEvent extends Model
{
    protected $fillable = ['product_id', 'actor_id', 'action', 'previous_status', 'new_status', 'note'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
