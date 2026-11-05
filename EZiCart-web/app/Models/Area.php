<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    protected $fillable = ['name', 'code', 'is_active', 'sorting_center_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function municipalities(): HasMany
    {
        return $this->hasMany(AreaMunicipality::class);
    }

    public function sortingCenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorting_center_id');
    }

    public function riders(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['is_primary', 'is_active'])
            ->withTimestamps()
            ->wherePivot('is_active', true)
            ->where('users.status', 'approved');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'destination_area_id');
    }
}
