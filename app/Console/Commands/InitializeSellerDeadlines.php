<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Notifications\OrderWorkflowNotification;
use App\Services\AdministrativeAuditService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InitializeSellerDeadlines extends Command
{
    protected $signature = 'orders:initialize-seller-deadlines';

    protected $description = 'Give legacy unshipped orders a fresh seller-response or preparation grace period';

    public function handle(AdministrativeAuditService $audits, TransactionAwareNotificationSender $notifications): int
    {
        $count = 0;
        Order::query()->whereIn('status', ['PLACED', 'CONFIRMED', 'PREPARING'])->chunkById(100, function ($orders) use ($audits, $notifications, &$count): void {
            foreach ($orders as $order) {
                DB::transaction(function () use ($order, $audits, $notifications, &$count): void {
                    $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                    if (! in_array($locked->status, ['PLACED', 'CONFIRMED', 'PREPARING'], true) || $locked->seller_handover_at !== null || $locked->picked_up_at !== null || $locked->received_at !== null) {
                        return;
                    }
                    $field = $locked->status === 'PLACED' ? 'seller_response_due_at' : 'preparation_due_at';
                    if ($locked->{$field} !== null) {
                        return;
                    }
                    $hours = (int) config($locked->status === 'PLACED' ? 'commerce.seller_response_hours' : 'commerce.seller_preparation_hours');
                    $locked->forceFill([$field => now()->addHours($hours), 'seller_reminded_at' => null])->save();
                    $audits->record(null, $locked, 'legacy_seller_deadline_initialized', [$field => null], [$field => $locked->{$field}->toIso8601String()], 'Fresh grace period assigned without retroactive cancellation.');
                    $notifications->send($locked->seller, new OrderWorkflowNotification($locked, 'seller_deadline_initialized', "This existing order now has a fresh {$hours}-hour seller deadline."));
                    $count++;
                });
            }
        });
        $this->info("Initialized {$count} legacy seller deadline(s).");

        return self::SUCCESS;
    }
}
