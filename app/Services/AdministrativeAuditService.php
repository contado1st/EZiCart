<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdministrativeAuditService
{
    public function record(?User $actor, Model $entity, string $action, array $previous, array $next, string $reason): void
    {
        DB::table('administrative_audits')->insert([
            'actor_id' => $actor?->id, 'entity_type' => $entity->getMorphClass(), 'entity_id' => $entity->getKey(),
            'action' => $action, 'previous_values' => json_encode($previous, JSON_THROW_ON_ERROR),
            'new_values' => json_encode($next, JSON_THROW_ON_ERROR), 'reason' => $reason, 'created_at' => now(),
        ]);
    }
}
