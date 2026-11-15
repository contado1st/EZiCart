<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WalletFundingIntent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'expires_at' => 'datetime', 'request_details' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::updating(function (WalletFundingIntent $intent): void {
            if ($intent->getOriginal('provider') !== 'internal_demo') {
                return;
            }
            if ($intent->isDirty(['reference', 'user_id', 'account_id', 'idempotency_key', 'amount_cents', 'currency', 'provider', 'request_kind', 'request_details', 'deposit_reference_hash', 'expires_at'])
                || ($intent->getOriginal('reviewed_at') !== null && $intent->isDirty())) {
                throw new LogicException('Demo funding requests and recorded decisions are immutable.');
            }
        });
        static::deleting(function (WalletFundingIntent $intent): void {
            if ($intent->provider === 'internal_demo') {
                throw new LogicException('Retain demo funding request and review history.');
            }
        });
    }
}
