<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RiderActivationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Activate your EZiCart rider account')
            ->greeting('Your rider account is ready')
            ->line('A logistics company added you to its EZiCart rider roster.')
            ->action('Set your password', route('rider.activation.show', ['token' => $this->token]))
            ->line('This one-use link expires in 24 hours. If you were not expecting it, contact the logistics company directly.');
    }
}
