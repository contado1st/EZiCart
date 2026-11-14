<?php

namespace App\Console\Commands;

use App\Models\DispatchCycle;
use App\Models\DispatchLocation;
use App\Services\DispatchOfferService;
use Illuminate\Console\Command;

class ProcessDispatchOffers extends Command
{
    protected $signature = 'dispatch:process {--apply : Process expired/unavailable offers and prune old rider positions}';

    protected $description = 'Preview or process existing automatic offer cycles; never restart Unassigned work';

    public function handle(DispatchOfferService $offers): int
    {
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
