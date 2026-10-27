<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class OrderConversation extends Model
{
    protected $fillable = ['order_id'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OrderMessage::class, 'conversation_id')->orderBy('created_at')->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(OrderMessage::class, 'conversation_id')->latestOfMany();
    }

    /** @return Collection<int, User> */
    public function participants(): Collection
    {
        return $this->order->messageParticipants();
    }
}
