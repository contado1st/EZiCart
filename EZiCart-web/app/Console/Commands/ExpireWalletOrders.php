<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderTransitionService;
use Illuminate\Console\Command;

class ExpireWalletOrders extends Command
{
    protected $signature = 'wallet:expire-orders';

    protected $description = 'Cancel expired unpaid wallet reservations and release stock/voucher liability once';

    public function handle(OrderTransitionService $transitions): int
    {
        $expired = 0;
        Order::awaitingWalletPayment()->where('wallet_payment_due_at', '<=', now())->chunkById(100, function ($orders) use ($transitions, &$expired): void {
            foreach ($orders as $order) {
                if ($transitions->expireWalletPayment($order)) {
                    $expired++;
                }
            }
        });
        $this->info('Expired '.$expired.' unpaid wallet reservation(s).');

        return self::SUCCESS;
    }
}
