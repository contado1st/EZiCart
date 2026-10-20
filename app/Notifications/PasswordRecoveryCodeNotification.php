<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordRecoveryCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your EZiCart password recovery code')
            ->greeting('Hello '.($notifiable->first_name ?: 'there').',')
            ->line('Use this code to verify your identity and reset your EZiCart password:')
            ->line($this->code)
            ->line('This code expires in 5 minutes and can be used once. You have up to 5 attempts.')
            ->line('If you did not request a password reset, you can ignore this email.');
    }
}
