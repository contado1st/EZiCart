<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'title',
        'content',
        'type',
        'target_role',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActiveForUser($query, ?User $user = null)
    {
        $role = $user ? $user->role : 'buyer';

        return $query->where('is_active', true)
            ->where(function ($q) use ($role) {
                $q->where('target_role', 'all')
                  ->orWhere('target_role', $role);
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }
}