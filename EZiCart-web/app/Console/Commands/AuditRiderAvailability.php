<?php

namespace App\Console\Commands;

use App\Services\RiderWorkAvailabilityService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('riders:availability-audit {--check : Fail when choices or legacy assignment evidence need review} {--json : Output counts as JSON}')]
#[Description('Read-only readiness counts before activating rider work availability')]
class AuditRiderAvailability extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RiderWorkAvailabilityService $availability): int
    {
        $report = $availability->readiness();
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
        } else {
            $this->table(['Readiness check', 'Result'], collect($report)->map(fn ($value, $name) => [$name, is_bool($value) ? ($value ? 'Yes' : 'No') : $value])->values()->all());
            $this->comment('Read-only: no rider, assignment, custody or enforcement setting changed.');
        }

        return $this->option('check') && ! $report['ready_for_activation'] ? self::FAILURE : self::SUCCESS;
    }
}
