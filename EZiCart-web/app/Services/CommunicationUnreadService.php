<?php

namespace App\Services;

use App\Models\DirectMessage;
use App\Models\User;

class CommunicationUnreadService
{
    /** @return array{messages: int, notifications: int} */
    public function counts(User $user): array
    {
        return ['messages' => DirectMessage::query()->where('recipient_id', $user->id)->whereNull('read_at')->count(),
            'notifications' => $user->unreadNotifications()->count()];
    }
}
