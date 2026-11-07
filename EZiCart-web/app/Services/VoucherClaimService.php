<?php

namespace App\Services;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherClaimService
{
    public function claim(User $buyer, Voucher $voucher): object
    {
        abort_unless($buyer->role === 'buyer' && $buyer->status === 'approved', 403);

        return DB::transaction(function () use ($buyer, $voucher) {
            $voucher = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            $this->synchronizeHistory($voucher);
            if (! $voucher->is_active || ($voucher->expires_at && $voucher->expires_at->isPast()) || ($voucher->seller_id && $voucher->seller?->status !== 'approved')) {
                throw ValidationException::withMessages(['voucher_code' => 'This voucher is inactive or expired.']);
            }
            $claim = DB::table('voucher_claims')->where('voucher_id', $voucher->id)->where('buyer_id', $buyer->id)->first();
            if ($claim) {
                return $claim;
            }
            if ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit) {
                throw ValidationException::withMessages(['voucher_code' => 'This voucher has reached its total redemption limit.']);
            }
            if ($voucher->recipient_limit !== null && $voucher->claimed_count >= $voucher->recipient_limit) {
                throw ValidationException::withMessages(['voucher_code' => 'This voucher has been fully claimed.']);
            }
            $id = DB::table('voucher_claims')->insertGetId(['voucher_id' => $voucher->id, 'buyer_id' => $buyer->id, 'redemption_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $voucher->increment('claimed_count');

            return DB::table('voucher_claims')->where('id', $id)->first();
        }, 3);
    }

    public function assertUsable(User $buyer, Voucher $voucher): void
    {
        $claim = DB::table('voucher_claims')->where('voucher_id', $voucher->id)->where('buyer_id', $buyer->id)->first();
        if (($voucher->recipient_limit !== null && ! $claim)
            || ($claim && $voucher->per_buyer_limit !== null && $claim->redemption_count >= $voucher->per_buyer_limit)) {
            throw ValidationException::withMessages(['voucher_code' => $claim ? 'You have reached this voucher’s per-buyer usage limit.' : 'Claim this voucher before using it.']);
        }
    }

    /** Called while holding the voucher lock, before first use of its new ledger. */
    public function synchronizeHistory(Voucher $voucher): void
    {
        if ($voucher->claimed_count > 0 || $voucher->used_count < 1) {
            return;
        }
        $history = DB::table('orders')->where('voucher_code', $voucher->code)->whereNotNull('buyer_id')
            ->where('created_at', '>=', $voucher->created_at)
            ->when($voucher->seller_id, fn ($q) => $q->where('seller_id', $voucher->seller_id))
            ->select('buyer_id')->selectRaw('COUNT(DISTINCT COALESCE(purchase_id, -id)) as redemptions')
            ->selectRaw('MIN(created_at) as first_used')->selectRaw('MAX(created_at) as last_used')->groupBy('buyer_id')->get();
        foreach ($history as $row) {
            DB::table('voucher_claims')->insertOrIgnore(['voucher_id' => $voucher->id, 'buyer_id' => $row->buyer_id, 'redemption_count' => $row->redemptions, 'created_at' => $row->first_used, 'updated_at' => $row->last_used]);
        }
        $voucher->update(['claimed_count' => DB::table('voucher_claims')->where('voucher_id', $voucher->id)->count()]);
    }
}
