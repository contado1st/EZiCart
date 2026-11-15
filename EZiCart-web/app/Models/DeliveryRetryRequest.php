<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DeliveryRetryRequest extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            if ($request->getOriginal('status') !== 'PENDING' || $request->isDirty(['order_id', 'failed_attempt_id', 'requested_by', 'hub_id', 'proposed_for', 'reason'])) {
                throw new LogicException('Retry request origins and completed reviews are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Retry request history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['proposed_for' => 'datetime', 'scheduled_for' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function failedAttempt(): BelongsTo
    {
        return $this->belongsTo(DeliveryAttempt::class, 'failed_attempt_id');
    }
}
