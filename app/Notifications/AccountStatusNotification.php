<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public string $status) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your EZiCart account was '.($this->status === 'approved' ? 'approved' : 'not approved'))
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
        return $this->status === 'approved'
            ? 'Your account application has been approved. You can now sign in.'
            : 'Your account application was not approved. Contact the administrator if you need clarification.';
    }
}
