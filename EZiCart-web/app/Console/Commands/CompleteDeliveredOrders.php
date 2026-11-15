<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderCompletionEligibilityService;
use App\Services\OrderTransitionService;
use Illuminate\Console\Command;

class CompleteDeliveredOrders extends Command
{
    protected $signature = 'orders:complete-delivered {--apply : Commit eligible completions} {--enroll-order= : Explicit opt-in for one historical delivered order} {--reason= : Required reason for historical enrollment}';

    protected $description = 'Preview held/due deliveries or complete eligible orders after 168 elapsed hours; never release earnings';

    public function handle(OrderTransitionService $transitions, OrderCompletionEligibilityService $eligibility): int
    {
        if ($this->option('enroll-order') !== null) {
            if (! ctype_digit((string) $this->option('enroll-order')) || mb_strlen(trim((string) $this->option('reason'))) < 10 || mb_strlen((string) $this->option('reason')) > 500) {
                $this->error('Historical enrollment requires an order ID and a 10–500 character reason.');

                return self::INVALID;
            }
            $order = Order::findOrFail((int) $this->option('enroll-order'));
            $reason = $eligibility->holdReason($order);
            if ($order->status !== 'DELIVERED' || $reason) {
                $this->error($reason ?? 'Historical enrollment requires Delivered status.');

                return self::FAILURE;
            }
            if ($this->option('apply')) {
                $transitions->enrollCompletion($order, trim($this->option('reason')));
            }
            $this->info($this->option('apply') ? 'Historical delivery enrolled. Run the preview before applying completions.' : 'Historical enrollment preview only; no changes made.');

            return self::SUCCESS;
        }
        $count = 0;
        Order::where('status', 'DELIVERED')->orderBy('id')->chunkById(100, function ($orders) use ($transitions, $eligibility, &$count) {
            foreach ($orders as $order) {
                $reason = $eligibility->automaticReason($order);
                if ($reason !== null) {
                    $this->line($order->order_number.' — held: '.$reason);

                    continue;
                }
                $this->line($order->order_number.' — eligible');
                $count += $this->option('apply') ? (int) $transitions->completeAutomatically($order) : 1;
            }
        });
        $this->info($count.($this->option('apply') ? ' orders automatically completed; no payout issued.' : ' eligible orders; no changes made.'));

        return self::SUCCESS;
    }
}
