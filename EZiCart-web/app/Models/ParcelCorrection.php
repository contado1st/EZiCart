<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ParcelCorrection extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['evidence_path', 'evidence_sha256', 'open_order_id', 'baseline_event_id'];

    protected function casts(): array
    {
        return ['snapshot_before' => 'array', 'snapshot_after' => 'array', 'reviewed_at' => 'datetime', 'tracking_event_id' => 'integer', 'baseline_event_id' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $correction): void {
            if ($correction->getOriginal('status') !== 'OPEN' || $correction->isDirty(['order_id', 'hub_id', 'requested_by', 'snapshot_before', 'baseline_event_id', 'reason', 'evidence_path', 'evidence_sha256', 'created_at'])
                || ! in_array($correction->status, ['RESOLVED', 'REJECTED'], true) || $correction->open_order_id !== null) {
                throw new LogicException('Correction request origins and completed decisions are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Correction review history cannot be deleted.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hub_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ParcelTrackingEvent::class, 'tracking_event_id');
    }
}
