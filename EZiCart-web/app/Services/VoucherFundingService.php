<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBudget;
use App\Models\VoucherRedemption;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherFundingService
{
    public function __construct(private FinancialLedgerService $ledger, private AdministrativeAuditService $audits) {}

    public function reserve(Voucher $voucher, User $actor): VoucherBudget
    {
        return DB::transaction(function () use ($voucher, $actor): VoucherBudget {
            $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->status === 'approved' && ($locked->seller_id === null ? $actor->isCommerceAdmin() : $actor->role === 'seller' && $locked->seller_id === $actor->id), 403);
            if (! $locked->usage_limit || $locked->usage_limit > 1000000 || ($locked->type === 'percent' && ! $locked->max_discount)) {
                throw ValidationException::withMessages(['usage_limit' => 'Funded vouchers require a redemption limit; percentage vouchers also require a maximum discount per redemption.']);
            }
            $cap = CheckoutPricingService::cents($locked->type === 'percent' ? $locked->max_discount : min($locked->value, $locked->max_discount ?? $locked->value));
            abort_unless($locked->used_count < $locked->usage_limit, 422, 'This voucher has exhausted its funded redemption limit.');
            $maximum = $cap * $locked->usage_limit;
            abort_unless($cap > 0 && $maximum <= config('financial.maximum_transaction_cents'), 422, 'The promotion budget exceeds the supported limit.');
            $source = $locked->seller_id === null ? $this->ledger->treasury() : $this->ledger->wallet($actor);
            $reserve = $this->ledger->account('voucher:'.$locked->id.':reserve', $locked->seller_id === null ? 'platform_promotion' : 'seller_promotion', $locked->seller_id, 'reserved');
            $budget = VoucherBudget::firstOrCreate(['voucher_id' => $locked->id], ['source_account_id' => $source->id, 'reserve_account_id' => $reserve->id, 'maximum_cents' => $maximum, 'cap_cents' => $cap]);
            $budget = VoucherBudget::whereKey($budget->id)->lockForUpdate()->firstOrFail();
            $needed = $budget->maximum_cents - $budget->consumed_cents - $reserve->refresh()->balance_cents;
            abort_unless($needed >= 0 && ($locked->expires_at === null || $locked->expires_at->isFuture()), 422, 'This promotion has expired or its funding needs review.');
            if ($needed > 0) {
                $this->ledger->transfer('voucher:'.$locked->id.':reserve:'.$budget->released_cents, 'voucher_funding', $source, $reserve, $needed, $actor, ['voucher_id' => $locked->id]);
            }
            $budget->update(['status' => 'RESERVED']);

            return $budget;
        }, 3);
    }

    public function release(Voucher $voucher, ?User $actor): void
    {
        DB::transaction(function () use ($voucher, $actor): void {
            $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            if ($actor) {
                abort_unless($actor->status === 'approved' && ($locked->seller_id === null ? $actor->isCommerceAdmin() : $locked->seller_id === $actor->id && $actor->role === 'seller'), 403);
            } else {
                abort_unless($locked->expires_at?->isPast() || ! $locked->is_active || ($locked->usage_limit && $locked->used_count >= $locked->usage_limit), 422);
            }
            $budget = VoucherBudget::where('voucher_id', $locked->id)->lockForUpdate()->first();
            if (! $budget) {
                return;
            }
            $reserve = FinancialAccount::findOrFail($budget->reserve_account_id);
            if ($reserve->balance_cents > 0) {
                $amount = $reserve->balance_cents;
                $this->ledger->transfer('voucher:'.$locked->id.':release:'.$budget->released_cents, 'voucher_release', $reserve, FinancialAccount::findOrFail($budget->source_account_id), $amount, $actor, ['voucher_id' => $locked->id]);
                $budget->increment('released_cents', $amount);
            }
            $budget->update(['status' => 'RELEASED']);
            $locked->update(['is_active' => false]);
            $this->audits->record($actor, $locked, 'unused_voucher_budget_released', [], ['budget_id' => $budget->id], 'Unused promotion funds returned to their original owner. Consumed redemptions remain recorded.');
        });
    }

    public function consume(Voucher $voucher, Order $order, int $amount, User $buyer): void
    {
        $budget = VoucherBudget::where('voucher_id', $voucher->id)->lockForUpdate()->first();
        if (! $budget || $amount === 0) {
            return;
        } // Explicit historical/unbudgeted voucher compatibility.
        abort_unless($budget->status === 'RESERVED' && $amount > 0 && $amount <= $budget->cap_cents, 422, 'Voucher budget is unavailable.');
        $redemption = VoucherRedemption::firstOrCreate(['order_id' => $order->id], ['voucher_id' => $voucher->id, 'amount_cents' => $amount]);
        if (! $redemption->wasRecentlyCreated) {
            abort_unless($redemption->voucher_id === $voucher->id && $redemption->amount_cents === $amount, 409);

            return;
        }
        $this->ledger->transfer('voucher:'.$voucher->id.':order:'.$order->id, 'voucher_redemption', FinancialAccount::findOrFail($budget->reserve_account_id), $this->ledger->escrow($order->id), $amount, $buyer, ['order_id' => $order->id, 'voucher_id' => $voucher->id]);
        $budget->increment('consumed_cents', $amount);
    }

    /** Called once after reversing pending earnings, before returning the buyer's payment. */
    public function restore(Order $order, ?User $actor, bool $restoreUsage = true): void
    {
        $redemption = VoucherRedemption::where('order_id', $order->id)->lockForUpdate()->first();
        if (! $redemption || $redemption->status !== 'COMMITTED') {
            return;
        }
        $voucher = Voucher::whereKey($redemption->voucher_id)->lockForUpdate()->firstOrFail();
        $budget = VoucherBudget::where('voucher_id', $voucher->id)->lockForUpdate()->firstOrFail();
        $reactivate = $budget->status === 'RESERVED' && $voucher->is_active && ($voucher->expires_at === null || $voucher->expires_at->isFuture());
        $destination = FinancialAccount::findOrFail($reactivate ? $budget->reserve_account_id : $budget->source_account_id);
        $this->ledger->transfer('voucher:'.$voucher->id.':restore:'.$order->id, 'voucher_reversal', $this->ledger->escrow($order->id), $destination, $redemption->amount_cents, $actor, ['order_id' => $order->id]);
        $budget->decrement('consumed_cents', $redemption->amount_cents);
        if (! $reactivate) {
            $budget->increment('released_cents', $redemption->amount_cents);
        }
        $redemption->update(['status' => 'RESTORED']);
        if ($restoreUsage) {
            $voucher->decrement('used_count');
            DB::table('voucher_claims')->where('voucher_id', $voucher->id)->where('buyer_id', $order->buyer_id)->where('redemption_count', '>', 0)->decrement('redemption_count');
        }
    }
}
