<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherBudget extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['maximum_cents' => 'integer', 'cap_cents' => 'integer', 'consumed_cents' => 'integer', 'released_cents' => 'integer'];
    }

    public function reserveAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'reserve_account_id');
    }
}
