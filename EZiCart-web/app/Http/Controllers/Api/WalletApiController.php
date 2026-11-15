<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialAccount;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderFinance;
use App\Models\RefundExecution;
use App\Models\User;
use App\Models\WalletFundingIntent;
use App\Models\WalletWithdrawal;
use App\Services\CheckoutPricingService;
use App\Services\DemoWalletFundingService;
use App\Services\FinancialLedgerService;
use App\Services\WalletFundingService;
use App\Services\WalletOrderPaymentService;
use App\Services\WalletTransferService;
use App\Services\WalletWithdrawalService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class WalletApiController extends Controller
{
    /**
     * Get buyer wallet details: balances, transactions, funding requests, and withdrawals.
     */
    public function wallet(Request $request, FinancialLedgerService $ledger, WalletTransferService $transfers): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $available = $ledger->wallet($actor);
        $accounts = FinancialAccount::where('user_id', $actor->id)->pluck('id');

        $entriesPage = LedgerEntry::whereIn('account_id', $accounts)
            ->with(['account', 'transaction'])
            ->latest('id')
            ->paginate(25);

        $demoFunding = app(DemoWalletFundingService::class);
        $demoEnabled = app(WalletFundingService::class)->enabled();

        $intentsPage = $demoFunding->visible($actor)
            ->latest('id')
            ->paginate(25);

        $withdrawalsPage = WalletWithdrawal::where('user_id', $actor->id)
            ->latest('id')
            ->paginate(25);

        $pendingCents = $ledger->wallet($actor, 'pending')->balance_cents;
        $heldCents = $ledger->wallet($actor, 'held')->balance_cents;
        $reservedCents = (int) FinancialAccount::where('bucket', 'reserved')
            ->where('user_id', $actor->id)
            ->sum('balance_cents');

        $mappedEntries = collect($entriesPage->items())->map(fn ($entry) => [
            'id' => $entry->id,
            'created_at' => $this->formatIsoDate($entry->created_at),
            'reference' => $entry->transaction?->reference ?? 'TXN-'.$entry->id,
            'type' => $entry->transaction?->type ?? 'entry',
            'type_label' => str_replace('_', ' ', $entry->transaction?->type ?? 'entry'),
            'bucket' => $entry->account?->bucket,
            'account_type' => $entry->account?->type,
            'delta' => (float) ($entry->delta_cents / 100),
            'delta_cents' => (int) $entry->delta_cents,
            'balance_before' => (float) ($entry->balance_before_cents / 100),
            'balance_after' => (float) ($entry->balance_after_cents / 100),
        ]);

        $mappedIntents = collect($intentsPage->items())->map(function ($intent) {
            $isExpired = $intent->status === 'PENDING' && $intent->expires_at && $intent->expires_at->isPast();
            $statusLabel = $isExpired
                ? 'Expired'
                : ($intent->status === 'PAID' ? 'Approved — credited' : ($intent->status === 'REJECTED' ? 'Rejected' : 'Awaiting review'));

            return [
                'id' => $intent->id,
                'reference' => $intent->reference,
                'provider' => $intent->provider,
                'kind' => $intent->request_kind ?? 'demo_credit',
                'kind_label' => str_replace('_', ' ', $intent->request_kind ?? 'demo_credit'),
                'amount' => (float) ($intent->amount_cents / 100),
                'amount_cents' => (int) $intent->amount_cents,
                'status' => $intent->status,
                'status_label' => $statusLabel,
                'reason' => $intent->request_details['reason'] ?? '',
                'receipt_reference' => $intent->request_details['receipt_reference'] ?? null,
                'has_evidence' => ! empty($intent->request_details['evidence_path']),
                'evidence_url' => ! empty($intent->request_details['evidence_path']) ? url('/api/v1/wallet/demo-funding/'.$intent->id.'/evidence') : null,
                'created_at' => $this->formatIsoDate($intent->created_at),
                'expires_at' => $this->formatIsoDate($intent->expires_at),
                'is_expired' => $isExpired,
                'reviewed_at' => $this->formatIsoDate($intent->reviewed_at),
                'reviewed_by' => $intent->reviewed_by,
                'review_reason' => $intent->review_reason,
            ];
        });

        $mappedWithdrawals = collect($withdrawalsPage->items())->map(fn ($w) => [
            'id' => $w->id,
            'reference' => $w->reference,
            'amount' => (float) ($w->amount_cents / 100),
            'amount_cents' => (int) $w->amount_cents,
            'destination' => $w->destination_reference,
            'status' => $w->status,
            'status_label' => str_replace('_', ' ', $w->status),
            'created_at' => $this->formatIsoDate($w->created_at),
            'reviewed_at' => $this->formatIsoDate($w->reviewed_at),
            'review_reason' => $w->review_reason,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                // Keep the transfer-summary fields alongside the mobile wallet history contract.
                'mode' => 'demo',
                'enabled' => $demoEnabled,
                'available_cents' => (int) $available->balance_cents,
                'currency' => 'PHP',
                'frozen' => (bool) $available->frozen,
                'receive_reference' => $transfers->reference($actor),
                'balances' => [
                    'available' => (float) ($available->balance_cents / 100),
                    'available_cents' => (int) $available->balance_cents,
                    'pending' => (float) ($pendingCents / 100),
                    'pending_cents' => (int) $pendingCents,
                    'held' => (float) ($heldCents / 100),
                    'held_cents' => (int) $heldCents,
                    'reserved' => (float) ($reservedCents / 100),
                    'reserved_cents' => (int) $reservedCents,
                    'is_frozen' => (bool) $available->frozen,
                ],
                'demo_enabled' => $demoEnabled,
                'entries' => $mappedEntries,
                'funding_intents' => $mappedIntents,
                'withdrawals' => $mappedWithdrawals,
                'pagination' => [
                    'entries_page' => $entriesPage->currentPage(),
                    'entries_total' => $entriesPage->total(),
                    'intents_page' => $intentsPage->currentPage(),
                    'intents_total' => $intentsPage->total(),
                    'withdrawals_page' => $withdrawalsPage->currentPage(),
                    'withdrawals_total' => $withdrawalsPage->total(),
                ],
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Submit a demo funding request (demo credits or demo cash deposit).
     */
    public function demoFunding(Request $request, DemoWalletFundingService $funding): JsonResponse
    {
        $actor = $request->user();
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'request_key' => ['nullable', 'uuid'],
            'kind' => ['required', 'in:demo_credit,demo_cash_deposit'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'receipt_reference' => ['required_if:kind,demo_cash_deposit', 'nullable', 'string', 'min:3', 'max:120'],
            'evidence' => ['required_if:kind,demo_cash_deposit', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $requestKey = $data['request_key'] ?? (string) Str::uuid();
        $path = $request->file('evidence')?->store('wallet-demo-deposits', 'private');

        try {
            $intent = $funding->request(
                $actor,
                CheckoutPricingService::cents($data['amount']),
                $requestKey,
                $data['kind'],
                [
                    'reason' => $data['reason'],
                    'receipt_reference' => $data['receipt_reference'] ?? null,
                    'evidence_path' => $path,
                    'evidence_sha256' => $path ? hash_file('sha256', $request->file('evidence')->getRealPath()) : null,
                ]
            );

            if ($path && ($intent->request_details['evidence_path'] ?? null) !== $path) {
                Storage::disk('private')->delete($path);
            }
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('private')->delete($path);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'This funding request or demo receipt was already recorded. Review your original history.',
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'Unable to submit demo funding request.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Demo funding request recorded. Funds stay unchanged until an independent administrator approves.',
            'data' => [
                'id' => $intent->id,
                'reference' => $intent->reference,
                'amount' => (float) ($intent->amount_cents / 100),
                'status' => $intent->status,
                'kind' => $intent->request_kind,
            ],
        ], 201);
    }

    /**
     * Submit a sandbox withdrawal request.
     */
    public function withdraw(Request $request, WalletWithdrawalService $withdrawals): JsonResponse
    {
        $actor = $request->user();
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'destination' => ['required', 'string', 'min:5', 'max:500'],
            'request_key' => ['nullable', 'uuid'],
        ]);

        $requestKey = $data['request_key'] ?? (string) Str::uuid();

        try {
            $withdrawal = $withdrawals->request(
                $actor,
                CheckoutPricingService::cents($data['amount']),
                $data['destination'],
                $requestKey
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Invalid withdrawal request.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Unable to process withdrawal request.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal requested. Funds are held pending authorized review; execution is sandbox-only.',
            'data' => [
                'id' => $withdrawal->id,
                'reference' => $withdrawal->reference,
                'amount' => (float) ($withdrawal->amount_cents / 100),
                'destination' => $withdrawal->destination_reference,
                'status' => $withdrawal->status,
            ],
        ], 201);
    }

    /**
     * Download or view private demo receipt evidence.
     */
    public function evidence(Request $request, int $intentId, DemoWalletFundingService $funding)
    {
        $actor = $request->user();
        $intent = WalletFundingIntent::findOrFail($intentId);

        abort_unless(
            $intent->provider === 'internal_demo' && ($intent->user_id === $actor->id || $funding->canReview($actor, $intent)),
            404
        );
        abort_unless($funding->validEvidence($intent->request_details ?? []), 404);

        $path = $intent->request_details['evidence_path'];

        return Storage::disk('private')->download(
            $path,
            'demo-deposit-evidence.'.pathinfo($path, PATHINFO_EXTENSION),
            [
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Financial records (order payments, remittances, and refunds for buyer).
     */
    public function records(Request $request): JsonResponse
    {
        $actor = $request->user();

        $finances = OrderFinance::query()
            ->with(['order.seller:id,business_name'])
            ->whereHas('order', fn ($order) => $order->where('buyer_id', $actor->id))
            ->latest('id')
            ->paginate(20);

        $refunds = RefundExecution::query()
            ->with(['order'])
            ->whereHas('order', fn ($order) => $order->where('buyer_id', $actor->id))
            ->latest('id')
            ->paginate(20);

        $mappedFinances = collect($finances->items())->map(function ($finance) {
            $order = $finance->order;
            $paymentMethod = $order->payment_method ?? 'COD';
            $isAwaiting = $order->isAwaitingWalletPayment();
            $amount = $paymentMethod === 'WALLET'
                ? ($isAwaiting ? (float) $order->total_amount : (float) ($order->wallet_paid_cents / 100))
                : (float) ($finance->due_cents / 100);

            return [
                'id' => $finance->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'parcel_code' => $order->parcel_code,
                'seller_name' => $order->seller->business_name ?? 'Merchant',
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'is_awaiting_wallet_payment' => $isAwaiting,
                'status_note' => $paymentMethod === 'WALLET'
                    ? ($isAwaiting ? 'Awaiting demo wallet payment' : 'Wallet payment recorded')
                    : 'COD due',
                'remittance_status' => $finance->remittance_status,
                'remittance_status_label' => str_replace('_', ' ', $finance->remittance_status),
                'created_at' => $this->formatIsoDate($finance->created_at),
            ];
        });

        $mappedRefunds = collect($refunds->items())->map(fn ($refund) => [
            'id' => $refund->id,
            'order_id' => $refund->order_id,
            'order_number' => $refund->order?->order_number,
            'reference' => $refund->reference,
            'amount' => (float) ($refund->amount_cents / 100),
            'method' => $refund->payment_method ?? 'WALLET',
            'status' => $refund->status ?? 'COMPLETED',
            'reason' => $refund->reason,
            'created_at' => $this->formatIsoDate($refund->created_at),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'order_payments' => $mappedFinances,
                'refunds' => $mappedRefunds,
            ],
        ]);
    }

    /**
     * Pay for an unpaid deferred wallet order.
     */
    public function payOrder(int $orderId, Request $request, WalletOrderPaymentService $payments): JsonResponse
    {
        $buyer = $request->user();
        $order = Order::find($orderId);

        if (! $order || $order->buyer_id !== $buyer->id) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->password, $buyer->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect password. Please verify your account password to authorize wallet payment.',
            ], 422);
        }

        try {
            $payments->pay($order, $buyer);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Unable to process wallet payment.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Unable to process wallet payment.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Demo wallet payment recorded. The seller has been notified. No COD is due.',
            'data' => [
                'order_id' => $order->id,
                'status' => $order->fresh()->status,
                'is_awaiting_wallet_payment' => false,
                'wallet_paid_cents' => (int) $order->fresh()->wallet_paid_cents,
            ],
        ]);
    }

    /**
     * Cancel an unpaid deferred wallet order reservation.
     */
    public function cancelOrder(int $orderId, Request $request, WalletOrderPaymentService $payments): JsonResponse
    {
        $buyer = $request->user();
        $order = Order::find($orderId);

        if (! $order || $order->buyer_id !== $buyer->id) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        try {
            $payments->cancel($order, $buyer);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Unable to cancel reservation.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Unable to cancel reservation.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Unpaid reservation cancelled. Stock and eligible voucher use were restored; no money was charged.',
        ]);
    }

    private function formatIsoDate(mixed $date): ?string
    {
        if ($date instanceof CarbonInterface) {
            return $date->toIso8601String();
        }
        if ($date instanceof \DateTimeInterface) {
            return $date->format(\DateTimeInterface::ATOM);
        }
        if (is_string($date) && filled($date)) {
            try {
                return Carbon::parse($date)->toIso8601String();
            } catch (Throwable) {
                return $date;
            }
        }

        return null;
    }
}
