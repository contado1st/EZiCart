<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
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
        return ['database'];
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
