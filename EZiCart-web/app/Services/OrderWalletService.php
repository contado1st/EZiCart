<?php

namespace App\Services;

use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\OrderFinance;
use App\Models\RefundExecution;
use App\Models\SellerPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderWalletService
{
    public function __construct(private FinancialLedgerService $ledger, private AdministrativeAuditService $audits) {}

    public function paymentReady(Order $order): bool
    {
        if ($order->payment_method === 'COD') {
            return true;
        }
        if ($order->payment_method !== 'WALLET' || $order->financial_version !== 1 || ! $order->wallet_transaction_id || $order->wallet_paid_cents !== CheckoutPricingService::cents($order->total_amount)) {
            return false;
        }
        $transaction = LedgerTransaction::find($order->wallet_transaction_id);

        return $transaction?->type === 'order_payment' && (int) ($transaction->metadata['order_id'] ?? 0) === $order->id
            && ! $this->ledger->wallet($order->buyer)->frozen;
    }

    /** Runs in PlacePurchaseService's transaction; stock, wallet, promotion and order roll back together. */
    public function pay(Order $order, User $buyer): void
    {
        app(WalletFundingService::class)->assertSandbox();
        abort_unless($buyer->role === 'buyer' && $buyer->status === 'approved' && $order->buyer_id === $buyer->id && $order->payment_method === 'WALLET', 403);
        $amount = CheckoutPricingService::cents($order->total_amount);
        abort_unless($amount > 0, 422, 'Zero-total wallet orders require a zero-tender workflow. Use COD for a zero-total checkout.');
        $transaction = $this->ledger->transfer('order:'.$order->id.':payment', 'order_payment', $this->ledger->wallet($buyer), $this->ledger->escrow($order->id), $amount, $buyer, ['order_id' => $order->id, 'buyer_id' => $buyer->id]);
        $order->forceFill(['wallet_paid_cents' => $amount, 'wallet_transaction_id' => $transaction->id])->save();
        $finance = app(OrderFinancialService::class)->initialize($order);
        // VERIFIED denotes verified funds. collector_id/collected_cents remain NULL: no cash was collected.
        $finance->forceFill(['remittance_status' => 'VERIFIED', 'verified_at' => now()])->save();
        $this->stageFunds($order, $finance);
    }

    public function stageFunds(Order $order, OrderFinance $finance): void
    {
        if ($order->financial_version !== 1 || $finance->ledger_funded_at !== null) {
            return;
        }
        abort_unless($finance->remittance_status === 'VERIFIED', 422, 'Verify funds before staging proceeds.');
        if ($finance->funded_cents < $finance->platform_subsidy_cents) {
            return;
        }
        if ($order->payment_method === 'COD' && $finance->due_cents > 0) {
            $this->ledger->transfer('order:'.$order->id.':cash-received', 'cod_cash_verified', $this->ledger->account('platform:cod-cash-control', 'cash_control', null, 'available', true), $this->ledger->escrow($order->id), $finance->due_cents, null, ['order_id' => $order->id]);
        }
        if ($finance->seller_payable_cents > 0) {
            $this->ledger->transfer('order:'.$order->id.':seller-pending', 'seller_earnings_pending', $this->ledger->escrow($order->id), $this->ledger->wallet($order->seller, 'pending'), $finance->seller_payable_cents, null, ['order_id' => $order->id]);
        }
        $finance->update(['ledger_funded_at' => now()]);
    }

    private function unstage(Order $order, OrderFinance $finance, ?User $actor): void
    {
        if ($finance->ledger_funded_at && $finance->seller_payable_cents > 0) {
            abort_if(SellerPayout::where('order_id', $order->id)->where('status', 'SETTLED')->exists(), 422, 'Recover settled funds before reversing this payment.');
            $this->ledger->transfer('order:'.$order->id.':seller-pending-reversal', 'seller_earnings_reversal', $this->ledger->wallet($order->seller, 'pending'), $this->ledger->escrow($order->id), $finance->seller_payable_cents, $actor, ['order_id' => $order->id]);
        }
    }

    public function cancel(Order $order, OrderFinance $finance, ?User $actor): void
    {
        if ($order->financial_version !== 1) {
            return;
        }
        $this->unstage($order, $finance, $actor);
        app(VoucherFundingService::class)->restore($order, $actor);
        if ($order->payment_method === 'WALLET' && $order->wallet_paid_cents > 0) {
            $this->ledger->transfer('order:'.$order->id.':cancel-refund', 'cancelled_order_refund', $this->ledger->escrow($order->id), $this->ledger->wallet($order->buyer), $order->wallet_paid_cents, $actor, ['order_id' => $order->id]);
        }
    }

    public function refund(RefundExecution $refund, User $actor, string $reason): void
    {
        abort_unless($actor->status === 'approved' && $actor->isCommerceAdmin() && strlen(trim($reason)) >= 5, 403);
        DB::transaction(function () use ($refund, $actor, $reason): void {
            $order = Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            $locked = RefundExecution::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $finance = OrderFinance::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->financial_version === 1 && $order->payment_method === 'WALLET' && $locked->status === 'AUTHORIZED' && $order->status === 'RETURNED_TO_SELLER' && $finance->ledger_funded_at !== null, 422, 'An authorized funded wallet return is required.');
            $this->unstage($order, $finance, $actor);
            app(VoucherFundingService::class)->restore($order, $actor, false);
            $transaction = $this->ledger->transfer('refund:'.$locked->id, 'refund_credit', $this->ledger->escrow($order->id), $this->ledger->wallet($order->buyer), $locked->amount_cents, $actor, ['order_id' => $order->id, 'refund_id' => $locked->id]);
            $locked->update(['status' => 'EXECUTED', 'executed_by' => $actor->id, 'reference' => 'wallet:'.$transaction->reference, 'executed_at' => now()]);
            $this->audits->record($actor, $locked, 'wallet_refund_credited', [], ['transaction_id' => $transaction->id], $reason);
        });
    }

    public function externalRefund(Order $order, OrderFinance $finance, RefundExecution $refund, User $actor): void
    {
        if ($order->financial_version !== 1) {
            return;
        }
        $this->unstage($order, $finance, $actor);
        app(VoucherFundingService::class)->restore($order, $actor, false);
        $this->ledger->transfer('refund:'.$refund->id, 'external_refund', $this->ledger->escrow($order->id), $this->ledger->account('platform:cod-cash-control', 'cash_control', null, 'available', true), $refund->amount_cents, $actor, ['order_id' => $order->id]);
    }

    public function settleSeller(SellerPayout $payout, User $actor, bool $toWallet, string $reason): void
    {
        $order = Order::whereKey($payout->order_id)->lockForUpdate()->firstOrFail();
        $finance = OrderFinance::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
        abort_unless($order->financial_version === 1 && $finance->ledger_funded_at !== null, 422, 'This order has no staged ledger proceeds.');
        $destination = $toWallet ? $this->ledger->wallet($order->seller) : $this->ledger->account('platform:cod-cash-control', 'cash_control', null, 'available', true);
        $transaction = $this->ledger->transfer('payout:'.$payout->id, $toWallet ? 'seller_earnings_credit' : 'external_seller_settlement', $this->ledger->wallet($order->seller, 'pending'), $destination, $payout->amount_cents, $actor, ['order_id' => $order->id]);
        if ($finance->commission_cents > 0) {
            $this->ledger->transfer('order:'.$order->id.':commission', 'platform_commission', $this->ledger->escrow($order->id), $this->ledger->treasury(), $finance->commission_cents, $actor, ['order_id' => $order->id]);
        }
        app(LogisticsSettlementService::class)->settle($order, $actor);
        $payout->forceFill(['destination' => $toWallet ? 'wallet' : 'external', 'transaction_id' => $transaction->id])->save();
        $this->audits->record($actor, $payout, 'seller_ledger_settlement', [], ['transaction_id' => $transaction->id, 'destination' => $payout->destination], $reason);
    }
}
