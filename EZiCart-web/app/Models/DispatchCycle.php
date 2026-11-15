<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispatchCycle extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['pickup_latitude', 'pickup_longitude', 'pickup_address_hash', 'operation_key'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hub_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(DispatchOffer::class);
    }
}
