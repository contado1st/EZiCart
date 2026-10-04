<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** @return \Illuminate\Support\Collection<int, \App\Models\User> */
    public function participants(): \Illuminate\Support\Collection
    {
        return $this->order->messageParticipants();
    }
}
