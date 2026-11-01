<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelTransfer extends Model
{
    protected $guarded = ['id'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sourceHub(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_hub_id');
    }

    public function destinationHub(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destination_hub_id');
    }
}
