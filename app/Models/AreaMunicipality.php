<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AreaMunicipality extends Model
{
    protected $fillable = [
        'area_id', 'province', 'municipality', 'province_normalized', 'municipality_normalized',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
