<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchLocation extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['latitude', 'longitude', 'address_hash'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'recorded_at' => 'datetime'];
    }
}
