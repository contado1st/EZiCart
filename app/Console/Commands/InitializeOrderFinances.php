<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\CheckoutPricingService;
use App\Services\OrderFinancialService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InitializeOrderFinances extends Command
{
    protected $signature = 'finance:initialize-orders';

    protected $description = 'Create historical financial snapshots without assuming remittances or settlements';

    public function handle(OrderFinancialService $finances): int
    {
        $count = 0;
        Order::query()->where('payment_method', 'COD')->chunkById(100, function ($orders) use ($finances, &$count): void {
            foreach ($orders as $order) {
                DB::transaction(function () use ($order, $finances, &$count): void {
                    $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                    $finances->initialize($locked);
                    $dispute = $locked->dispute;
                    if ($dispute?->status === 'REFUND_APPROVED' && $dispute->resolver?->isCommerceAdmin()) {
                        $finances->authorizeRefund($dispute, $dispute->resolver);
                    }
                    // Missing delivery evidence remains uncollected for manual investigation.
                    if ($locked->deliveryCourier && $locked->delivery_code_used_at !== null && $locked->cod_collected_amount !== null
                        && in_array($locked->status, ['DELIVERED', 'COMPLETED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'], true)
                        && CheckoutPricingService::cents($locked->cod_collected_amount) === CheckoutPricingService::cents($locked->total_amount)
                        && $locked->deliveryAttempts()->where('rider_id', $locked->delivery_courier_id)->where('outcome', 'delivered')->whereNotNull('proof_path')->exists()) {
                        $finances->recordCollection($locked, $locked->deliveryCourier);
                    }
                    $count++;
                });
            }
        });
        $this->info("Initialized {$count} COD order snapshot(s). Remittances and settlements were not inferred.");

        return self::SUCCESS;
    }
}
