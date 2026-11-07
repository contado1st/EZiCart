<?php

namespace App\Notifications\Channels;

use App\Services\GmailApiOtpSender;
use Illuminate\Notifications\Notification;
use RuntimeException;

class GmailApiChannel
{
    public function __construct(private readonly GmailApiOtpSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toGmailApi')) {
            throw new RuntimeException('The notification does not define a Gmail API message.');
        }

        $recipient = $notifiable->routeNotificationFor('mail', $notification);
        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('The notification recipient does not have a valid email address.');
        }

        $message = $notification->toGmailApi($notifiable);
        $this->sender->send($recipient, $message['subject'], $message['body']);
    }
}
