<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RiderWorkAvailabilityService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('riders:reconcile-availability {--apply : Release only safely unaccepted offers under active enforcement}')]
#[Description('Reconcile offers for unavailable/unchosen riders; accepted parcels stay assigned')]
class ReconcileRiderAvailability extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RiderWorkAvailabilityService $availability): int
    {
        if (! $this->option('apply')) {
            $this->line(json_encode($availability->readiness(), JSON_PRETTY_PRINT));
            $this->comment('Read-only preview. No assignments changed. Use --apply only after announced activation.');

            return self::SUCCESS;
        }
        if (! $availability->enforced()) {
            $this->error('Enforcement is disabled. Run the readiness audit and announce activation first. No assignments changed.');

            return self::FAILURE;
        }
        $total = 0;
        foreach (User::where('role', 'courier')->where('status', 'approved')
            ->where(fn ($q) => $q->whereNull('work_availability')->orWhere('work_availability', '!=', 'available'))->lazyById(100) as $rider) {
            $total += array_sum($availability->withdrawUnacceptedOffers($rider));
        }
        $this->info($total.' unaccepted offer(s) returned to logistics; accepted work and physical custody were preserved.');

        return self::SUCCESS;
    }
}
