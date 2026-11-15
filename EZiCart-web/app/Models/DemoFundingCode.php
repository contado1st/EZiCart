<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoFundingCode extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_hash', 'request_hash', 'request_key'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'expires_at' => 'datetime', 'redeemed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }

    public function state(): string
    {
        return $this->redeemed_at ? 'Redeemed' : ($this->revoked_at ? 'Revoked' : (! $this->expires_at->isFuture() ? 'Expired' : 'Issued'));
    }
}
