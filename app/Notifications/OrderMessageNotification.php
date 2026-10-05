<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderMessageNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order, public string $senderName) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New message about order {$this->order->order_number}")
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line("{$this->senderName} sent a message about this order.")
            ->line("Order reference: {$this->order->order_number}")
            ->action('View conversation', $this->conversationUrl($notifiable));
    }

    /** @return array<string, int|string> */
    public function toDatabase(User $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'message' => "{$this->senderName} sent a message about this order.",
            'url' => $this->conversationUrl($notifiable),
        ];
    }

    private function conversationUrl(User $notifiable): string
    {
        [$route, $parameters] = match ($notifiable->role) {
            'buyer' => ['buyer.orders.messages.show', [$this->order->id]],
            'seller' => ['seller.orders.messages.show', [$this->order->id]],
            'courier' => ['courier.orders.messages.show', [$this->order->id]],
            'sorting_center' => ['logistics.orders.messages.show', [$this->order->id]],
            default => ['notifications.index', []],
        };

        return route($route, $parameters);
    }
}
