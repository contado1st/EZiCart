<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ReturnInspectionItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expected_quantity' => 'integer', 'restock_quantity' => 'integer', 'excluded_quantity' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Return inspection items are immutable.'));
        static::deleting(fn () => throw new LogicException('Return inspection history cannot be deleted.'));
    }
}
