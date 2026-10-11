<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your EZiCart email')
            ->greeting('Verify your email address')
            ->line('Enter this six-digit code on EZiCart to continue:')
            ->line($this->code)
            ->line('This code expires in five minutes. If you did not create an account, you can ignore this email.');
    }
}
