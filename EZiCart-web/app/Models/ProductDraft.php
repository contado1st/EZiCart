<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDraft extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['uploads'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'uploads' => 'array', 'submitted_at' => 'datetime'];
    }
}
