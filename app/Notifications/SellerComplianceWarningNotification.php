<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerComplianceWarningNotification extends Notification
{
    use Queueable;

    public function __construct(public Product $product, public string $note) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seller compliance warning')
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line("A compliance warning was issued for {$this->product->name}.")
            ->line('Reason: '.$this->note)
            ->action('Review your products', route('seller.products.index'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'status' => 'warning_issued',
            'message' => "A compliance warning was issued for {$this->product->name}.",
            'note' => $this->note,
            'url' => route('seller.products.index'),
        ];
    }
}
