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
        return $this->product->name.': '.match (true) {
            $this->state === 'unavailable' => 'currently unavailable.',
            $this->previous === 'unavailable' => 'back in stock'.($this->state === 'low' ? ' with limited availability.' : '.'),
            $this->state === 'low' => 'low stock. Availability may change before checkout.',
            default => 'stock is available again.',
        };
    }

    public function toDatabase(object $notifiable): array
    {
        return ['product_id' => $this->product->id, 'event_type' => 'stock_'.$this->state, 'message' => $this->message(), 'url' => route('buyer.stock.index')];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Stock update from EZiCart')->line($this->message())->action('View watched products', route('buyer.stock.index'));
    }
}
