<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderCancellationRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['respond_by' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
