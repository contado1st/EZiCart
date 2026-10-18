<?php

namespace App\Notifications;

use App\Notifications\Channels\GmailApiChannel;
use App\Services\GmailApiOtpSender;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code)
    {
    }

    /**
     * Determine which channels the notification should be delivered on.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        // If Gmail API credentials are explicitly configured, also deliver via Gmail API
        try {
            if (app(GmailApiOtpSender::class)->isConfigured()) {
                $channels[] = GmailApiChannel::class;
            }
        } catch (\Throwable) {
            // Fall back to standard Laravel mail only
        }

        return $channels;
    }

    /**
     * Build the standard Laravel mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $userName = trim(($notifiable->first_name ?? '').' '.($notifiable->last_name ?? ''));
        if ($userName === '') {
            $userName = 'there';
        }

        return (new MailMessage)
            ->subject('Your EZiCart Verification Code: '.$this->code)
            ->view('emails.otp', [
                'user' => $notifiable,
                'userName' => $userName,
                'code' => $this->code,
                'expiresInMinutes' => 5,
            ])
            ->text('emails.otp-text', [
                'user' => $notifiable,
                'userName' => $userName,
                'code' => $this->code,
                'expiresInMinutes' => 5,
            ]);
    }

    /**
     * Support Gmail API direct delivery if configured.
     *
     * @return array{subject: string, body: string}
     */
    public function toGmailApi(object $notifiable): array
    {
        return [
            'subject' => 'Your EZiCart Verification Code: '.$this->code,
            'body' => "Verify your EZiCart email address\n\nYour 6-digit verification code is: {$this->code}\n\nThis code expires in 5 minutes. Never share this code with anyone.\n\nIf you did not initiate this registration on EZiCart, you can safely ignore this email.",
        ];
    }
}
