<?php

namespace App\Console\Commands;

use App\Models\Voucher;
use App\Services\VoucherFundingService;
use Illuminate\Console\Command;

class ReleaseExpiredVoucherFunds extends Command
{
    protected $signature = 'finance:release-expired-vouchers';

    protected $description = 'Release unspent promotion reserves for expired funded vouchers without deleting their history';

    public function handle(VoucherFundingService $funding): int
    {
        $count = 0;
        Voucher::where('expires_at', '<=', now())->whereHas('budget', fn ($q) => $q->where('status', 'RESERVED'))->chunkById(100, function ($vouchers) use ($funding, &$count): void {
            foreach ($vouchers as $voucher) {
                $funding->release($voucher, null);
                $count++;
            }
        });
        $this->info($count.' expired promotion budgets released.');

        return self::SUCCESS;
    }
}
