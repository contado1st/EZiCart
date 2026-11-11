<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ShipmentFinancial extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (ShipmentFinancial $shipment): void {
            if ($shipment->isDirty(['order_id', 'quote_snapshot', 'rate_version_id', 'buyer_shipping_cents', 'seller_shipping_cents', 'platform_shipping_cents'])) {
                throw new LogicException('Original shipment prices and funding snapshots are immutable. Record an approved adjustment.');
            }
            if ($shipment->getOriginal('settled_at') !== null) {
                throw new LogicException('Settled shipment allocations cannot be edited.');
            }
        });
        static::deleting(fn () => throw new LogicException('Shipment financial snapshots cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['quote_snapshot' => 'array', 'settlement_snapshot' => 'array', 'settled_at' => 'datetime', 'buyer_shipping_cents' => 'integer', 'seller_shipping_cents' => 'integer', 'platform_shipping_cents' => 'integer', 'additional_funding_cents' => 'integer'];
    }
}
