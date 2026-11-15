<?php

namespace App\Console\Commands;

use App\Models\DispatchCycle;
use App\Models\DispatchLocation;
use App\Models\User;
use App\Services\DispatchOfferService;
use App\Services\HubOrderScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessDispatchOffers extends Command
{
    protected $signature = 'dispatch:process {--apply : Process expired/unavailable offers and prune old rider positions}';

    protected $description = 'Preview or process dispatch offers; apply starts eligible stages once and never restarts Unassigned work';

    public function handle(DispatchOfferService $offers): int
    {
        if ($this->option('apply') && config('dispatch.automatic_start')) {
            User::where('role', 'sorting_center')->where('status', 'approved')->chunkById(25, function ($hubs) use ($offers) {
                foreach ($hubs as $hub) {
                    $orders = app(HubOrderScope::class)->query($hub)->whereIn('status', ['READY_FOR_PICKUP', 'SORTED'])
                        ->whereDoesntHave('dispatchCycles', fn ($q) => $q->whereColumn('kind', DB::raw("CASE WHEN orders.status = 'READY_FOR_PICKUP' THEN 'pickup' ELSE 'delivery' END")))
                        ->oldest('orders.id')->limit(25)->get();
                    foreach ($orders as $order) {
                        $offers->initiateAutomatically($order, $hub);
                    }
                }
            });
        }
        $count = 0;
        DispatchCycle::where('status', 'OFFERING')->chunkById(100, function ($cycles) use ($offers, &$count) {
            foreach ($cycles as $cycle) {
                if ($this->option('apply')) {
                    $offers->process($cycle);
                }
                $this->line('Cycle #'.$cycle->id.' / '.$cycle->fresh()->status);
                $count++;
            }
        });
        if ($this->option('apply')) {
            DispatchLocation::where('source', 'device_reported')->where('recorded_at', '<', now()->subHours((int) config('dispatch.position_retention_hours')))->delete();
        }
        $this->info($count.' cycles inspected. '.($this->option('apply') ? 'Due work processed; old device positions pruned.' : 'Preview only; no changes made.'));

        return self::SUCCESS;
    }
}
