<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderCancellationRequest;
use App\Services\OrderTransitionService;
use App\Services\StockSubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessRoadmapAutomation extends Command
{
    protected $signature = 'commerce:process-automation';

    protected $description = 'Process cancellation deadlines, rider acceptance deadlines, and watched stock changes';

    public function handle(OrderTransitionService $transitions, StockSubscriptionService $subscriptions): int
    {
        OrderCancellationRequest::where('status', 'PENDING')->where('respond_by', '<=', now())->chunkById(100, function ($requests) use ($transitions) {
            foreach ($requests as $request) {
                $transitions->resolveCancellation(Order::findOrFail($request->order_id), null, true, 'Automatically approved after the seller response window expired.');
            }
        });
        Order::where('status', 'ASSIGNED_TO_RIDER')->whereNull('hub_released_at')->where('assigned_at', '<=', now()->subMinutes((int) config('commerce.rider_acceptance_minutes', 15)))
            ->chunkById(100, function ($orders) use ($transitions) {
                foreach ($orders as $order) {
                    $transitions->expireRiderAssignment($order);
                }
            });
        DB::table('stock_subscriptions')->select('id')->orderBy('id')->chunkById(100, function ($rows) use ($subscriptions) {
            foreach ($rows as $row) {
                $subscriptions->check($row->id);
            }
        });
        $this->info('Commerce automation processed.');

        return self::SUCCESS;
    }
}
