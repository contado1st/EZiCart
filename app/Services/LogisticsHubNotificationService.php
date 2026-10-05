<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;

class LogisticsHubNotificationService
{
    public function sendForOrder(
        Order $order,
        TransactionAwareNotificationSender $notifications,
        OrderWorkflowNotification $notification,
    ): void {
        $hub = $order->sortingCenter ?? $order->destinationArea?->sortingCenter;
        if ($hub === null) {
            $activeHubs = User::query()->where('role', 'sorting_center')->where('status', 'approved')->limit(2)->get();
            $hub = $activeHubs->count() === 1 ? $activeHubs->first() : null;
        }

        if ($hub !== null) {
            $notifications->send($hub, $notification);
        }
    }
}
