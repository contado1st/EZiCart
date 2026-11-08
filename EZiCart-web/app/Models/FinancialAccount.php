<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['balance_cents' => 'integer', 'allow_negative' => 'boolean', 'frozen' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id');
    }
}
