<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentPriceAdjustment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['measurements' => 'array', 'revised_quote' => 'array', 'difference_cents' => 'integer'];
    }
}
