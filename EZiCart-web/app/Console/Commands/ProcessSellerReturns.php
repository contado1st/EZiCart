<?php

namespace App\Console\Commands;

use App\Models\SellerReturnResponse;
use App\Services\SellerReturnService;
use Illuminate\Console\Command;

class ProcessSellerReturns extends Command
{
    protected $signature = 'returns:process-deadlines {--apply : Apply deadline decisions; otherwise preview only}';

    protected $description = 'Preview or apply seller-response and buyer-escalation deadlines without moving stock or money';

    public function handle(SellerReturnService $returns): int
    {
        $count = 0;
        SellerReturnResponse::whereHas('dispute', fn ($q) => $q->where('status', 'PENDING'))->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('stage', 'awaiting_seller')->where('response_due_at', '<=', now()))
            ->orWhere(fn ($q) => $q->where('stage', 'seller_rejected')->where('escalation_due_at', '<=', now())))
            ->with('dispute')->chunkById(100, function ($rows) use ($returns, &$count) {
                foreach ($rows as $row) {
                    $this->line('Case '.$row->dispute_id.' / '.$row->stage.' / '.($this->option('apply') ? 'processing' : 'preview'));
                    $count += $this->option('apply') ? (int) $returns->expire($row->dispute) : 1;
                }
            });
        $this->info($count.($this->option('apply') ? ' deadline decisions applied.' : ' eligible decisions; no changes made.'));

        return self::SUCCESS;
    }
}
