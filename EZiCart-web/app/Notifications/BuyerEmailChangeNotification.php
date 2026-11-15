<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BuyerEmailChangeNotification extends Notification
{
    public function __construct(public readonly ?string $code = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->code !== null) {
            return (new MailMessage)->subject('Verify your new EZiCart email')
                ->line('Enter this code in your signed-in buyer account to confirm your new login email:')
                ->line($this->code)->line('Expires in 10 minutes. You have five attempts. Requesting another code invalidates this one.')
                ->line('If you did not request this change, ignore this email.');
        }

        return (new MailMessage)->subject('Your EZiCart login email changed')
            ->line('Your buyer account login email was changed after verification.')
            ->line('If you did not make this change, contact EZiCart support immediately.');
    }
}
