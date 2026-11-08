<?php

namespace App\Console\Commands;

use App\Services\FinancialLedgerService;
use Illuminate\Console\Command;

class ReconcileFinancialLedger extends Command
{
    protected $signature = 'finance:reconcile';

    protected $description = 'Read-only reconciliation of balanced entries and cached financial account balances';

    public function handle(FinancialLedgerService $ledger): int
    {
        $result = $ledger->reconcile();
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $result['balanced'] ? self::SUCCESS : self::FAILURE;
    }
}
