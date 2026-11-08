<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class DeliveryRateVersion extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (DeliveryRateVersion $rate): void {
            if ($rate->getOriginal('status') === 'APPROVED') {
                throw new LogicException('Approved tariff versions are immutable. Create a new draft.');
            }
        });
        static::deleting(fn () => throw new LogicException('Tariff history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['rules' => 'array', 'sandbox' => 'boolean', 'effective_at' => 'datetime', 'approved_at' => 'datetime'];
    }
}
