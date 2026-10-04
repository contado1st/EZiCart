<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
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
        return ['database'];
    }

    /** @return array<string, int|string> */
    public function toDatabase(User $notifiable): array
    {
        [$routeName, $parameters] = match ($notifiable->role) {
            'buyer' => ['buyer.orders.show', [$this->order->id]],
            'seller' => ['seller.orders.show', [$this->order->id]],
            'courier' => ['courier.orders.show', [$this->order->id]],
            'sorting_center' => ['logistics.tracking', ['search' => $this->order->order_number]],
            default => ['notifications.index', []],
        };

        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'event_type' => $this->eventType,
            'message' => $this->message,
            'url' => route($routeName, $parameters),
        ];
    }
}
