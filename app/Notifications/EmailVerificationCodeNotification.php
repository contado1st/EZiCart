<?php

namespace App\Notifications;

use App\Notifications\Channels\GmailApiChannel;
use Illuminate\Notifications\Notification;

class EmailVerificationCodeNotification extends Notification
{
    public function __construct(private readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return [GmailApiChannel::class];
    }

    /** @return array{subject: string, body: string} */
    public function toGmailApi(object $notifiable): array
    {
        return [
            'subject' => 'Verify your EZiCart email',
            'body' => "Verify your email address\n\nEnter this six-digit code on EZiCart to continue:\n{$this->code}\n\nThis code expires in five minutes. If you did not create an account, you can ignore this email.",
        ];
    }
}
