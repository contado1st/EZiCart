<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderFinance;
use App\Models\RefundExecution;
use App\Models\SellerPayout;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use Illuminate\Support\Facades\DB;

class OrderFinancialService
{
    public function __construct(private AdministrativeAuditService $audits) {}

    /** Call while holding the order lock. Historical snapshots never follow later product prices. */
    public function initialize(Order $order): OrderFinance
    {
        $void = $order->status === 'CANCELLED';

        return OrderFinance::query()->firstOrCreate(['order_id' => $order->id], [
            'due_cents' => $void ? 0 : CheckoutPricingService::cents($order->total_amount),
            'commission_cents' => $void ? 0 : CheckoutPricingService::cents($order->commission_fee),
            'seller_payable_cents' => $void ? 0 : max(0, CheckoutPricingService::cents($order->subtotal) - CheckoutPricingService::cents($order->discount_amount ?? 0) + CheckoutPricingService::cents($order->platform_discount_amount ?? 0) + (int) $order->seller_promo_funded_cents - CheckoutPricingService::cents($order->commission_fee)),
            'platform_subsidy_cents' => CheckoutPricingService::cents($order->platform_discount_amount ?? 0) + (int) $order->platform_shipping_funded_cents,
            'remittance_status' => $void ? 'VOID' : 'NOT_COLLECTED',
        ])->refresh();
    }

    public function voidUncollected(Order $order, ?User $actor): void
    {
        abort_unless($order->status === 'CANCELLED', 422);
        $finance = $this->initialize($order);
        abort_if($finance->collected_cents !== null, 422, 'A collected order must use the refund workflow.');
        if ($finance->remittance_status === 'VOID') {
            return;
        }
        $previous = $finance->only(['due_cents', 'commission_cents', 'seller_payable_cents', 'remittance_status']);
        app(OrderWalletService::class)->cancel($order, $finance, $actor);
        $finance->forceFill(['due_cents' => 0, 'commission_cents' => 0, 'seller_payable_cents' => 0, 'remittance_status' => 'VOID'])->save();
        $this->audits->record($actor, $finance, 'uncollected_order_voided', $previous, ['due_cents' => 0, 'remittance_status' => 'VOID'], 'Cancellation before collection; historical order prices retained.');
    }

    public function recordCollection(Order $order, User $rider): void
    {
        if ($order->payment_method === 'WALLET') {
            abort_unless($rider->role === 'courier' && $order->delivery_courier_id === $rider->id, 403);
            abort_unless(app(OrderWalletService::class)->paymentReady($order) && $order->delivery_code_used_at !== null
                && $order->deliveryAttempts()->where('rider_id', $rider->id)->where('outcome', 'delivered')->whereNotNull('proof_path')->exists(), 422, 'Verified wallet payment, delivery code and delivery proof are required.');

            return;
        }
        DB::transaction(function () use ($order, $rider): void {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($rider->role === 'courier' && $order->delivery_courier_id === $rider->id, 403);
            abort_unless(in_array($order->status, ['DELIVERED', 'COMPLETED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'], true)
                && $order->delivery_code_used_at !== null && $order->cod_collected_amount !== null
                && CheckoutPricingService::cents($order->cod_collected_amount) === CheckoutPricingService::cents($order->total_amount)
                && $order->deliveryAttempts()->where('rider_id', $rider->id)->where('outcome', 'delivered')->whereNotNull('proof_path')->exists(), 422, 'Verified delivery proof and an exact COD collection are required.');
            $finance = $this->initialize($order);
            if ($finance->collected_cents !== null) {
                return;
            }
            $finance->forceFill(['collector_id' => $rider->id, 'collected_cents' => $finance->due_cents, 'collected_at' => $order->delivered_at, 'remittance_status' => 'COLLECTED'])->save();
            $this->audits->record($rider, $finance, 'cod_collected', [], ['collected_cents' => $finance->collected_cents], 'Verified delivery code, proof and COD amount.');
        });
    }

    public function submitRemittance(Order $order, User $rider, int $amount, string $reference, string $receipt, string $reason): void
    {
        DB::transaction(function () use ($order, $rider, $amount, $reference, $receipt, $reason): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $finance = $this->initialize($locked);
            abort_unless($rider->role === 'courier' && $finance->collector_id === $rider->id, 403);
            abort_unless(in_array($finance->remittance_status, ['COLLECTED', 'DISCREPANCY'], true), 422, 'This collection is not awaiting a remittance submission.');
            abort_if(DB::table('remittance_batch_items')->where('order_finance_id', $finance->id)->exists(), 422, 'This collection belongs to a remittance batch. Use its batch reconciliation workflow.');
            $previous = $finance->only(['remittance_status', 'remitted_cents', 'remittance_reference']);
            $finance->forceFill([
                'remitted_cents' => $amount, 'remittance_reference' => $reference, 'receipt_path' => $receipt,
                'submitted_by' => $rider->id, 'submitted_at' => now(),
                'remittance_status' => $amount === (int) $finance->collected_cents ? 'SUBMITTED' : 'DISCREPANCY',
            ])->save();
            $this->audits->record($rider, $finance, 'remittance_submitted', $previous, $finance->only(['remittance_status', 'remitted_cents', 'remittance_reference']), $reason);
        });
    }

    public function verifyRemittance(Order $order, User $actor, string $reason): void
    {
        abort_unless($actor->isLogisticsAdmin(), 403);
        DB::transaction(function () use ($order, $actor, $reason): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $finance = $this->initialize($locked);
            abort_unless($actor->id !== $finance->submitted_by, 403, 'You cannot verify your own remittance.');
            abort_if(DB::table('remittance_batch_items')->where('order_finance_id', $finance->id)->exists(), 422, 'Reconcile this collection through its batch.');
            abort_unless($finance->remittance_status === 'SUBMITTED' && (int) $finance->remitted_cents === (int) $finance->collected_cents && $finance->receipt_path !== null, 422, 'Only an exact remittance with a receipt can be verified. Investigate discrepancies first.');
            $finance->forceFill(['remittance_status' => 'VERIFIED', 'verified_by' => $actor->id, 'verified_at' => now()])->save();
            $this->audits->record($actor, $finance, 'remittance_verified', ['status' => 'SUBMITTED'], ['status' => 'VERIFIED'], $reason);
            app(OrderWalletService::class)->stageFunds($locked, $finance);
        });
    }

    public function authorizeRefund(Dispute $dispute, User $actor): RefundExecution
    {
        abort_unless($actor->isCommerceAdmin(), 403);

        return DB::transaction(function () use ($dispute, $actor): RefundExecution {
            $order = Order::query()->whereKey($dispute->order_id)->lockForUpdate()->firstOrFail();
            ParcelIncidentService::assertClear($order);
            $dispute = Dispute::query()->whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            abort_unless($dispute->status === 'REFUND_APPROVED', 422, 'A marketplace refund decision is required before financial authorization.');
            abort_if(RefundExecution::where('order_id', $order->id)->whereNotNull('rts_order_id')->exists(), 422, 'A dedicated RTS refund authorization already exists. Review that record instead of creating a second refund.');
            $payout = SellerPayout::query()->where('order_id', $order->id)->lockForUpdate()->first();
            abort_if($payout?->status === 'SETTLED', 422, 'Seller payout is already settled. Recover funds before authorizing a refund.');
            if ($payout) {
                $previousPayoutStatus = $payout->status;
                app(OrderWalletService::class)->releaseSellerPayoutReservation($payout, $actor, 'Marketplace refund authorization.');
                $payout->forceFill(['status' => 'ON_HOLD'])->save();
                $this->audits->record($actor, $payout, 'payout_held_for_refund', ['status' => $previousPayoutStatus], ['status' => 'ON_HOLD'], 'Refund authorization blocks outstanding seller settlement.');
            }
            $finance = $this->initialize($order);
            $refund = RefundExecution::query()->firstOrCreate(['dispute_id' => $dispute->id], [
                'order_id' => $order->id, 'amount_cents' => $finance->due_cents, 'status' => 'AUTHORIZED', 'authorized_by' => $actor->id,
            ]);
            if ($refund->wasRecentlyCreated) {
                $this->audits->record($actor, $refund, 'refund_authorized', [], ['status' => 'AUTHORIZED', 'amount_cents' => $refund->amount_cents], $dispute->admin_notes ?? 'Full COD refund authorized; money has not been sent.');
            }

            return $refund;
        });
    }

    public function authorizeRtsWalletRefund(Order $order, User $actor, string $reason): RefundExecution
    {
        $reason = trim($reason);
        validator(['reason' => $reason], ['reason' => ['required', 'string', 'min:5', 'max:500']])->validate();

        return DB::transaction(function () use ($order, $actor, $reason): RefundExecution {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->status === 'approved' && $actor->isCommerceAdmin(), 403);
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            ParcelIncidentService::assertClear($locked);
            abort_unless(app(ReturnWorkflowService::class)->receiptConfirmed($locked), 422, 'Recorded courier handover and seller receipt evidence are required before RTS refund authorization.');
            abort_unless($locked->payment_method === 'WALLET' && $locked->financial_version === 1 && $locked->delivered_at === null
                && ! $locked->deliveryAttempts()->where('outcome', 'delivered')->exists(), 422, 'This action is for an undelivered ledger-wallet RTS. Delivered returns use marketplace dispute authorization; external providers are not connected.');
            $finance = OrderFinance::where('order_id', $locked->id)->lockForUpdate()->first();
            $amount = CheckoutPricingService::cents($locked->total_amount);
            abort_unless($finance && $finance->ledger_funded_at !== null && $finance->remittance_status === 'VERIFIED' && $finance->due_cents === $amount && $amount > 0, 422, 'Verified original financial snapshots and staged funds are required. Reconcile missing or mismatched funding first.');
            $payout = SellerPayout::where('order_id', $locked->id)->lockForUpdate()->first();
            abort_if($payout?->status === 'SETTLED', 422, 'Seller payout was settled. Recover and reconcile funds before authorizing a refund.');
            $existing = RefundExecution::where('order_id', $locked->id)->lockForUpdate()->get();
            if ($existing->isNotEmpty()) {
                abort_unless($existing->count() === 1 && in_array($existing->first()->status, ['AUTHORIZED', 'EXECUTED'], true) && (int) $existing->first()->amount_cents === $amount,
                    422, 'Existing refund records need reconciliation; no additional refund will be created.');

                return $existing->first();
            }
            abort_unless(app(OrderWalletService::class)->paymentReady($locked), 422, 'The original wallet payment or buyer account is not currently verified for this action.');
            if ($payout && $payout->status !== 'ON_HOLD') {
                $previous = $payout->status;
                app(OrderWalletService::class)->releaseSellerPayoutReservation($payout, $actor, 'RTS wallet refund authorization.');
                $payout->update(['status' => 'ON_HOLD']);
                $this->audits->record($actor, $payout, 'payout_held_for_refund', ['status' => $previous], ['status' => 'ON_HOLD'], 'RTS refund authorization blocks seller settlement.');
            }
            $refund = RefundExecution::create(['order_id' => $locked->id, 'rts_order_id' => $locked->id, 'dispute_id' => null,
                'amount_cents' => $amount, 'status' => 'AUTHORIZED', 'authorized_by' => $actor->id, 'authorization_reason' => $reason]);
            $this->audits->record($actor, $refund, 'rts_wallet_refund_authorized', [], ['status' => 'AUTHORIZED', 'amount_cents' => $amount, 'rts_order_id' => $locked->id], $reason);
            app(TransactionAwareNotificationSender::class)->send(collect([$locked->buyer, $locked->seller, $locked->sortingCenter])->filter()->unique('id'),
                new OrderWorkflowNotification($locked, 'rts_wallet_refund_authorized', 'Commerce administration authorized an RTS wallet refund. Payment has not yet been sent; inventory inspection and logistics costs remain separate.'));

            return $refund;
        }, 3);
    }

    public function executeRefund(RefundExecution $refund, User $actor, string $reference, string $evidence, string $reason): void
    {
        abort_unless($actor->isCommerceAdmin(), 403);
        DB::transaction(function () use ($refund, $actor, $reference, $evidence, $reason): void {
            $order = Order::query()->whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            ParcelIncidentService::assertClear($order);
            abort_if($order->payment_method === 'WALLET', 422, 'Refund this wallet purchase through its wallet credit action.');
            $locked = RefundExecution::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'AUTHORIZED', 422, 'This refund is not awaiting execution.');
            $finance = $this->initialize($order);
            abort_unless($finance->remittance_status === 'VERIFIED' && (int) $finance->collected_cents >= (int) $locked->amount_cents && $order->status === 'RETURNED_TO_SELLER', 422, 'Verify collected funds and seller return receipt before recording the refund.');
            app(OrderWalletService::class)->externalRefund($order, $finance, $locked, $actor);
            $locked->forceFill(['status' => 'EXECUTED', 'executed_by' => $actor->id, 'reference' => $reference, 'evidence_path' => $evidence, 'executed_at' => now()])->save();
            $this->audits->record($actor, $locked, 'external_refund_recorded', ['status' => 'AUTHORIZED'], ['status' => 'EXECUTED', 'reference' => $reference], $reason);
        });
    }

    private function assertPayable(Order $order, OrderFinance $finance): void
    {
        ParcelIncidentService::assertClear($order);
        $completed = $order->status === 'COMPLETED' || ($order->status === 'RETURNED_TO_SELLER' && Order::query()->where('replacement_for_order_id', $order->id)->where('status', 'COMPLETED')->exists());
        abort_unless($completed && $order->delivered_at !== null && $order->delivered_at->lte(now()->subDays(7))
            && ! ($order->status !== 'RETURNED_TO_SELLER' && $order->dispute()->where('status', 'REPLACEMENT_APPROVED')->exists())
            && $finance->remittance_status === 'VERIFIED' && $finance->seller_payable_cents > 0 && $finance->funded_cents >= $finance->platform_subsidy_cents
            && ! $order->dispute()->whereIn('status', ['PENDING', 'UNDER_REVIEW', 'REFUND_APPROVED'])->exists()
            && ! RefundExecution::query()->where('order_id', $order->id)->exists(), 422, 'Payout requires a completed purchase, a seven-day delivery hold, verified remittance, funded platform subsidies, and no open refund or dispute.');
    }

    public function fundPlatformSubsidy(Order $order, User $actor, string $reference, string $evidence, string $reason): void
    {
        abort_unless($actor->isCommerceAdmin(), 403);
        DB::transaction(function () use ($order, $actor, $reference, $evidence, $reason): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $finance = $this->initialize($locked);
            abort_unless($locked->status === 'COMPLETED' || ($locked->status === 'RETURNED_TO_SELLER' && Order::query()->where('replacement_for_order_id', $locked->id)->where('status', 'COMPLETED')->exists()), 422, 'Record promotion funding for completed fulfillment only.');
            abort_unless($finance->platform_subsidy_cents > 0 && $finance->funded_cents === 0, 422, 'This promotion does not require funding or funding is already recorded.');
            $finance->forceFill(['funded_cents' => $finance->platform_subsidy_cents, 'funding_reference' => $reference, 'funding_evidence_path' => $evidence, 'funded_by' => $actor->id, 'funded_at' => now()])->save();
            if ($locked->financial_version === 1) {
                $ledger = app(FinancialLedgerService::class);
                $ledger->transfer('order:'.$locked->id.':external-subsidy', 'external_platform_subsidy', $ledger->account('platform:cod-cash-control', 'cash_control', null, 'available', true), $ledger->escrow($locked->id), $finance->platform_subsidy_cents, $actor, ['order_id' => $locked->id, 'reference' => $reference]);
                if ($finance->remittance_status === 'VERIFIED') {
                    app(OrderWalletService::class)->stageFunds($locked, $finance);
                }
            }
            $this->audits->record($actor, $finance, 'platform_subsidy_funded', ['funded_cents' => 0], ['funded_cents' => $finance->funded_cents, 'reference' => $reference], $reason);
        });
    }

    public function requestPayout(Order $order, User $seller): SellerPayout
    {
        abort_unless($seller->role === 'seller' && $order->seller_id === $seller->id, 403);

        return DB::transaction(function () use ($order, $seller): SellerPayout {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            ParcelIncidentService::assertClear($locked);
            $finance = $this->initialize($locked);
            $this->assertPayable($locked, $finance);
            $payout = SellerPayout::query()->firstOrCreate(['order_id' => $locked->id], ['seller_id' => $seller->id, 'amount_cents' => $finance->seller_payable_cents, 'status' => 'REQUESTED']);
            abort_unless($payout->seller_id === $seller->id && $payout->amount_cents === $finance->seller_payable_cents, 422, 'The existing payout no longer matches this order’s payable amount. Ask an administrator to reconcile it.');
            if ($locked->financial_version === 1 && $payout->status === 'REQUESTED') {
                app(OrderWalletService::class)->reserveSellerPayout($payout, $seller);
            }
            if ($payout->wasRecentlyCreated) {
                $this->audits->record($seller, $payout, 'payout_requested', [], ['status' => 'REQUESTED', 'amount_cents' => $payout->amount_cents], 'Seller requested settlement.');
            }

            return $payout;
        });
    }

    public function approvePayout(SellerPayout $payout, User $actor, string $reason): void
    {
        abort_unless($actor->isCommerceAdmin() && $actor->id !== $payout->seller_id, 403);
        DB::transaction(function () use ($payout, $actor, $reason): void {
            $order = Order::query()->whereKey($payout->order_id)->lockForUpdate()->firstOrFail();
            ParcelIncidentService::assertClear($order);
            $locked = SellerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->assertPayable($order, $this->initialize($order));
            abort_unless($locked->status === 'REQUESTED', 422, 'Only requested payouts may be approved.');
            if ($order->financial_version === 1) {
                app(OrderWalletService::class)->reserveSellerPayout($locked, $order->seller);
            }
            $locked->forceFill(['status' => 'APPROVED', 'approved_by' => $actor->id])->save();
            $this->audits->record($actor, $locked, 'payout_approved', ['status' => 'REQUESTED'], ['status' => 'APPROVED'], $reason);
        });
    }

    public function settlePayout(SellerPayout $payout, User $actor, string $reference, string $evidence, string $reason): void
    {
        abort_unless($actor->isCommerceAdmin(), 403);
        DB::transaction(function () use ($payout, $actor, $reference, $evidence, $reason): void {
            $order = Order::query()->whereKey($payout->order_id)->lockForUpdate()->firstOrFail();
            ParcelIncidentService::assertClear($order);
            $locked = SellerPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->assertPayable($order, $this->initialize($order));
            abort_unless($locked->status === 'APPROVED', 422, 'Approve the payout before recording settlement.');
            if ($order->financial_version === 1) {
                app(OrderWalletService::class)->settleSeller($locked, $actor, false, $reason);
            }
            $locked->forceFill(['status' => 'SETTLED', 'executed_by' => $actor->id, 'reference' => $reference, 'evidence_path' => $evidence, 'executed_at' => now()])->save();
            $this->audits->record($actor, $locked, 'external_payout_recorded', ['status' => 'APPROVED'], ['status' => 'SETTLED', 'reference' => $reference], $reason);
        });
    }

    public function settleToWallet(SellerPayout $payout, User $actor, string $reason): void
    {
        abort_unless($actor->status === 'approved' && $actor->isCommerceAdmin() && strlen(trim($reason)) >= 5, 403);
        DB::transaction(function () use ($payout, $actor, $reason): void {
            $order = Order::whereKey($payout->order_id)->lockForUpdate()->firstOrFail();
            ParcelIncidentService::assertClear($order);
            $locked = SellerPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->assertPayable($order, $this->initialize($order));
            abort_unless($locked->status === 'APPROVED', 422, 'Approve this payout before crediting available seller balance.');
            app(OrderWalletService::class)->settleSeller($locked, $actor, true, $reason);
            $locked->update(['status' => 'SETTLED', 'executed_by' => $actor->id, 'reference' => 'wallet:'.$locked->transaction_id, 'executed_at' => now()]);
        });
    }
}
