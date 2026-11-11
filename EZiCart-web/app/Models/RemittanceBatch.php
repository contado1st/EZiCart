<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemittanceBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expected_cents' => 'integer', 'submitted_cents' => 'integer', 'received_cents' => 'integer', 'discrepancy_cents' => 'integer', 'submitted_at' => 'datetime', 'verified_at' => 'datetime'];
    }
}
