<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AlertAgingParcels extends Command
{
    protected $signature = 'logistics:alert-aging-parcels';

    protected $description = 'Notify the control tower and responsible hub once for each stale parcel stage';

    public function handle(TransactionAwareNotificationSender $notifications): int
    {
        $admins = User::query()->where('role', 'logistics_admin')->where('status', 'approved')->get();
        $count = 0;
        Order::query()->whereIn('status', ['READY_FOR_PICKUP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT'])
            ->chunkById(100, function ($orders) use ($admins, $notifications, &$count): void {
                foreach ($orders as $order) {
                    DB::transaction(function () use ($order, $admins, $notifications, &$count): void {
                        $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                        if (! in_array($locked->status, ['READY_FOR_PICKUP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT'], true)) {
                            return;
                        }
                        $observedAt = $locked->trackingEvents()->first()?->created_at ?? $locked->created_at;
                        if ($observedAt->gt(now()->subHours((int) config('commerce.parcel_alert_hours')))) {
                            return;
                        }
                        $recipients = $admins->concat([$locked->sortingCenter])->filter()->unique('id');
                        if ($recipients->isEmpty()) {
                            return;
                        }
                        $inserted = DB::table('operational_alerts')->insertOrIgnore(['order_id' => $locked->id, 'stage' => $locked->status, 'observed_at' => $observedAt, 'created_at' => now()]);
                        if ($inserted) {
                            $notifications->send($recipients, new OrderWorkflowNotification($locked, 'parcel_aging_alert', 'Parcel has exceeded the stage aging threshold. Verify physical custody and coordinate recovery.'));
                            $count++;
                        }
                    });
                }
            });
        $this->info("Recorded {$count} new parcel aging alert(s).");

        return self::SUCCESS;
    }
}
