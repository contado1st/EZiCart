<?php

namespace App\Notifications;

use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DirectMessageNotification extends Notification
{
    use Queueable;

    public function __construct(public DirectMessage $message, public string $senderName) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toDatabase(User $notifiable): array
    {
        return ['conversation_id' => $this->message->conversation_id, 'message_id' => $this->message->id,
            'message' => $this->senderName.' sent you a private message.',
            'url' => route('chat.show', $this->message->conversation_id)];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)->subject('New private message | EZiCart')
            ->line($this->senderName.' sent you a private message.')
            ->action('View conversation', route('chat.show', $this->message->conversation_id));
    }
}
