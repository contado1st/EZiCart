<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Notifications\OrderWorkflowNotification;
use App\Services\OrderTransitionService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireSellerOrders extends Command
{
    protected $signature = 'orders:expire-seller-deadlines';

    protected $description = 'Remind sellers and expire overdue orders before physical custody';

    public function handle(OrderTransitionService $transitions, TransactionAwareNotificationSender $notifications): int
    {
        $expired = 0;
        Order::query()->whereIn('status', ['PLACED', 'CONFIRMED', 'PREPARING'])
            ->where(fn ($query) => $query->whereNotNull('seller_response_due_at')->orWhereNotNull('preparation_due_at'))
            ->chunkById(100, function ($orders) use ($transitions, $notifications, &$expired): void {
                foreach ($orders as $order) {
                    if ($transitions->expireSellerDeadline($order)) {
                        $expired++;

                        continue;
                    }
                    DB::transaction(function () use ($order, $notifications): void {
                        $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                        $due = $locked->status === 'PLACED' ? $locked->seller_response_due_at : $locked->preparation_due_at;
                        if (! in_array($locked->status, ['PLACED', 'CONFIRMED', 'PREPARING'], true) || $due === null || $due->isPast() || $due->gt(now()->addHours(4)) || $locked->seller_reminded_at !== null) {
                            return;
                        }
                        $locked->forceFill(['seller_reminded_at' => now()])->save();
                        $notifications->send($locked->seller, new OrderWorkflowNotification($locked, 'seller_deadline_reminder', 'The order deadline is within four hours. Confirm or finish preparation.'));
                    });
                }
            });
        $this->info("Expired {$expired} overdue order(s).");

        return self::SUCCESS;
    }
}
