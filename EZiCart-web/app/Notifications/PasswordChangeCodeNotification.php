<?php

namespace App\Notifications;

use App\Notifications\Channels\GmailApiChannel;
use App\Services\GmailApiOtpSender;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangeCodeNotification extends Notification
{
    public function __construct(#[\SensitiveParameter] public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return app(GmailApiOtpSender::class)->isConfigured() ? [GmailApiChannel::class] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Verify your EZiCart password change')
            ->line('Enter this code in your signed-in account to change your password:')->line($this->code)
            ->line('Expires in '.config('account-security.password_otp_minutes').' minutes. Never share this code. If you did not request this, ignore it and review your account security.');
    }

    public function toGmailApi(object $notifiable): array
    {
        return ['subject' => 'Verify your EZiCart password change', 'body' => 'Your password-change verification code is: '.$this->code.'. Expires in '.config('account-security.password_otp_minutes').' minutes. Never share it.'];
    }
}
