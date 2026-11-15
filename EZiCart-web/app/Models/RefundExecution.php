<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundExecution extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['rts_order_id', 'authorization_reason'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
