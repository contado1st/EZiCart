<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourierApplicationSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public User $applicant) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New courier application to review')
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line("{$this->applicant->first_name} {$this->applicant->last_name} submitted a courier application.")
            ->line('Review the application and uploaded documents before approving or rejecting it.')
            ->action('Review courier applications', $this->reviewUrl($notifiable));
    }

    /** @return array<string, int|string> */
    public function toDatabase(User $notifiable): array
    {
        return [
            'event_type' => 'courier_application_submitted',
            'applicant_id' => $this->applicant->id,
            'applicant_name' => $this->applicant->first_name.' '.$this->applicant->last_name,
            'message' => 'A new courier application is waiting for review.',
            'url' => $this->reviewUrl($notifiable),
        ];
    }

    private function reviewUrl(User $notifiable): string
    {
        return $notifiable->role === 'admin'
            ? route('admin.registrations.index')
            : route('logistics.riders');
    }
}
