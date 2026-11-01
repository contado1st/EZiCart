<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DirectConversation extends Model
{
    protected $fillable = ['first_user_id', 'second_user_id'];

    protected static function booted(): void
    {
        static::creating(function (self $conversation) {
            $ids = [(int) $conversation->first_user_id, (int) $conversation->second_user_id];
            sort($ids);
            if ($ids[0] < 1 || $ids[0] === $ids[1]) {
                throw new \LogicException('A conversation requires two distinct users.');
            }
            [$conversation->first_user_id, $conversation->second_user_id] = $ids;
        });
        static::updating(function (self $conversation) {
            if ($conversation->isDirty(['first_user_id', 'second_user_id'])) {
                throw new \LogicException('Conversation participants cannot change.');
            }
        });
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q->where('first_user_id', $user->id)->orWhere('second_user_id', $user->id));
    }

    public function firstUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_user_id');
    }

    public function secondUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DirectMessage::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(DirectMessage::class, 'conversation_id')->latestOfMany();
    }

    public function hasParticipant(User $user): bool
    {
        return in_array($user->id, [(int) $this->first_user_id, (int) $this->second_user_id], true);
    }

    public function otherUser(User $user): User
    {
        abort_unless($this->hasParticipant($user), 403);

        return (int) $this->first_user_id === $user->id ? $this->secondUser : $this->firstUser;
    }
}
