<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockAvailabilityNotification extends Notification
{
    public function __construct(public Product $product, public string $state, public string $previous) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function message(): string
    {
        return match (true) {
            $this->state === 'unavailable' => "{$this->product->name} is currently out of stock.",
            $this->previous === 'unavailable' => "{$this->product->name} is available again! Back in stock to purchase.",
            $this->state === 'low' => "{$this->product->name} is low on stock. Only a few items left.",
            default => "{$this->product->name} is available again! In stock to purchase.",
        };
    }

    public function toDatabase(object $notifiable): array
    {
        $title = match ($this->state) {
            'unavailable' => 'Out of Stock: '.$this->product->name,
            default => 'Available Again: '.$this->product->name,
        };

        return [
            'product_id' => $this->product->id,
            'event_type' => 'stock_'.$this->state,
            'title' => $title,
            'message' => $this->message(),
            'url' => route('buyer.stock.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Stock update from EZiCart')->line($this->message())->action('View watched products', route('buyer.stock.index'));
    }
}
