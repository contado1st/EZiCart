<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductComplianceNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Product $product,
        public string $status,
        public ?string $note,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $approved = $this->status === 'approved';
        $message = $approved
            ? "{$this->product->name} was approved for sale."
            : "{$this->product->name} was flagged by the compliance team. Review the note and update the listing.";
        $mail = (new MailMessage)
            ->subject($approved ? 'Product approved for sale' : 'Product compliance action required')
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line($message);

        if (filled($this->note)) {
            $mail->line('Review note: '.$this->note);
        }

        return $mail->action('Review your products', route('seller.products.index'));
    }

    /** @return array<string, int|string> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'status' => $this->status,
            'message' => $this->status === 'approved'
                ? "{$this->product->name} was approved for sale."
                : "{$this->product->name} was flagged by the compliance team. Review the note and update the listing.",
            'note' => $this->note ?? '',
            'url' => route('seller.products.index'),
        ];
    }
}
