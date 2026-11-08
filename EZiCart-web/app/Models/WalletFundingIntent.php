<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletFundingIntent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'expires_at' => 'datetime'];
    }
}
