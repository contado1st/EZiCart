<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderMessageNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order, public string $senderName) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string> */
    public function toDatabase(User $notifiable): array
    {
        [$route, $parameters] = match ($notifiable->role) {
            'buyer' => ['buyer.orders.messages.show', [$this->order->id]],
            'seller' => ['seller.orders.messages.show', [$this->order->id]],
            'courier' => ['courier.orders.messages.show', [$this->order->id]],
            'sorting_center' => ['logistics.orders.messages.show', [$this->order->id]],
            default => ['notifications.index', []],
        };

        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'message' => "{$this->senderName} sent a message about this order.",
            'url' => route($route, $parameters),
        ];
    }
}
