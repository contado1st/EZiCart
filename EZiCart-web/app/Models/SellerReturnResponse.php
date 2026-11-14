<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerReturnResponse extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['stage' => 'awaiting_seller', 'version' => 1];

    protected function casts(): array
    {
        return ['response_due_at' => 'datetime', 'seller_responded_at' => 'datetime', 'escalation_due_at' => 'datetime', 'escalated_at' => 'datetime', 'version' => 'integer'];
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function label(): string
    {
        return match ($this->stage) {
            'awaiting_seller' => 'Awaiting seller response',
            'seller_approved' => 'Approved by seller — awaiting admin authorization',
            'seller_rejected' => 'Rejected by seller',
            'escalated' => 'In Dispute — administrator review',
            'deadline_approved' => 'Auto-approved request — awaiting admin authorization',
            default => 'Seller response stage closed',
        };
    }
}
