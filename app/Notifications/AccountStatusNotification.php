<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public string $status, public ?string $reason = null) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $subject = match ($this->status) {
            'approved' => 'Your EZiCart account was approved',
            'pending' => 'Your EZiCart account is under review',
            'suspended' => 'Your EZiCart account was suspended',
            default => 'Your EZiCart account was not approved',
        };
        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line($this->message());

        if ($this->status === 'approved') {
            $message->action('Sign in to EZiCart', route('login'));
        }

        return $message;
    }

    /** @return array<string, string> */
    public function toDatabase(User $notifiable): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message(),
            'url' => $this->status === 'approved' ? route('login') : '',
        ];
    }

    private function message(): string
    {
        return match ($this->status) {
            'approved' => 'Your account application has been approved. You can now sign in.',
            'pending' => 'Your account has been returned to pending review. Sign-in remains unavailable until the review is complete.',
            'suspended' => filled($this->reason)
                ? 'Your account has been suspended. Reason: '.$this->reason
                : 'Your account has been suspended and can no longer access the platform.',
            default => 'Your account application was not approved. Contact the administrator if you need clarification.',
        };
    }
}
