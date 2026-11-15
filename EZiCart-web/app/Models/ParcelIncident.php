<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelIncident extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['evidence_path', 'evidence_sha256', 'resolution_evidence_path', 'resolution_evidence_sha256', 'claim_notes', 'custody_snapshot', 'operation_key'];

    protected $visible = ['id', 'order_id', 'kind', 'status', 'claim_status', 'created_at', 'resolved_at'];

    protected $attributes = ['status' => 'OPEN', 'claim_status' => 'REVIEW_REQUIRED', 'version' => 1];

    protected function casts(): array
    {
        return ['custody_snapshot' => 'array', 'claim_reviewed_at' => 'datetime', 'resolved_at' => 'datetime', 'version' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
