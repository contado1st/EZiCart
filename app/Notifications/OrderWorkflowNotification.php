<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $eventType,
        public string $message,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order update: {$this->order->order_number}")
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line($this->message)
            ->line("Order reference: {$this->order->order_number}")
            ->action('View order update', $this->destinationUrl($notifiable));
    }

    /** @return array<string, int|string> */
    public function toDatabase(User $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'event_type' => $this->eventType,
            'message' => $this->message,
            'url' => $this->destinationUrl($notifiable),
        ];
    }

    private function destinationUrl(User $notifiable): string
    {
        [$routeName, $parameters] = match ($notifiable->role) {
            'buyer' => ['buyer.orders.show', [$this->order->id]],
            'seller' => ['seller.orders.show', [$this->order->id]],
            'courier' => ['courier.orders.show', [$this->order->id]],
            'sorting_center' => ['logistics.tracking', ['search' => $this->order->order_number]],
            default => ['notifications.index', []],
        };

        return route($routeName, $parameters);
    }
}
