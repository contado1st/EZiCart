<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFinance extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_cents' => 'integer', 'commission_cents' => 'integer', 'seller_payable_cents' => 'integer',
            'platform_subsidy_cents' => 'integer', 'funded_cents' => 'integer', 'collected_cents' => 'integer', 'remitted_cents' => 'integer',
            'collected_at' => 'datetime', 'submitted_at' => 'datetime', 'verified_at' => 'datetime', 'funded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
