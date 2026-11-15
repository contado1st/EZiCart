<?php

namespace App\Http\Controllers;

use App\Models\DeliveryRateVersion;
use App\Models\FinancialAccount;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderFinance;
use App\Models\RefundExecution;
use App\Models\RemittanceBatch;
use App\Models\SellerPayout;
use App\Models\ShipmentFinancial;
use App\Models\ShipmentPriceAdjustment;
use App\Models\User;
use App\Models\WalletFundingIntent;
use App\Models\WalletWithdrawal;
use App\Services\AnalyticsExportService;
use App\Services\CheckoutPricingService;
use App\Services\DeliveryPricingService;
use App\Services\DeliveryRateManagementService;
use App\Services\DemoFundingCodeService;
use App\Services\DemoWalletFundingService;
use App\Services\FinancialLedgerService;
use App\Services\LogisticsSettlementService;
use App\Services\OrderFinancialService;
use App\Services\OrderWalletService;
use App\Services\QrCodeService;
use App\Services\RemittanceBatchService;
use App\Services\RemittanceReportService;
use App\Services\ShipmentAdjustmentService;
use App\Services\TabularExportService;
use App\Services\WalletFundingService;
use App\Services\WalletTransferService;
use App\Services\WalletWithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialOperationsController extends Controller
{
    public function wallet(Request $request, FinancialLedgerService $ledger)
    {
        $actor = $request->user();
        $available = $actor->isCommerceAdmin() ? $ledger->treasury() : $ledger->wallet($actor);
        $accounts = FinancialAccount::where('user_id', $actor->id)->pluck('id');
        if ($actor->isCommerceAdmin()) {
            $accounts->push($available->id);
        }
        $entries = LedgerEntry::whereIn('account_id', $accounts)->with(['account', 'transaction'])->latest('id')->paginate(25, ['*'], 'entries_page')->withQueryString();
        $demoFunding = app(DemoWalletFundingService::class);
        $demoEnabled = app(WalletFundingService::class)->enabled();
        $intents = $demoFunding->visible($actor)->with('user')->latest('id')->paginate(25, ['*'], 'intents_page')->withQueryString();
        $withdrawals = $this->withdrawalQuery($actor)->latest('id')->paginate(25, ['*'], 'withdrawals_page')->withQueryString();
        $pending = $actor->isCommerceAdmin() ? 0 : $ledger->wallet($actor, 'pending')->balance_cents;
        $held = $actor->isCommerceAdmin() ? 0 : $ledger->wallet($actor, 'held')->balance_cents;
        $payoutReserved = (int) FinancialAccount::where('user_id', $actor->id)->where('bucket', 'payout_hold')->value('balance_cents');
        $reserved = FinancialAccount::where('bucket', 'reserved')->where(fn ($q) => $actor->isCommerceAdmin() ? $q->where('type', 'platform_promotion') : $q->where('user_id', $actor->id))->sum('balance_cents');
        $logisticsPools = $actor->isLogisticsAdmin()
            ? FinancialAccount::whereIn('key', ['platform:logistics-operations', 'platform:logistics-margin', 'platform:unallocated-logistics'])->get()
            : collect();

        $walletTransfers = app(WalletTransferService::class);
        $canReceive = in_array($actor->role, WalletTransferService::USER_ROLES, true);
        $receiveReference = $canReceive ? $walletTransfers->reference($actor) : null;
        $receiveQr = $receiveReference ? app(QrCodeService::class)->svg($receiveReference) : null;
        $fundingCodesReady = Schema::hasTable('demo_funding_codes');
        $fundingCodes = $fundingCodesReady ? app(DemoFundingCodeService::class)->visible($actor)->with(['issuer', 'recipient'])->latest('id')->paginate(25, ['*'], 'codes_page')->withQueryString() : null;

        return view('finance.wallet', compact('actor', 'available', 'pending', 'held', 'payoutReserved', 'reserved', 'entries', 'intents', 'withdrawals', 'logisticsPools', 'demoFunding', 'demoEnabled', 'walletTransfers', 'canReceive', 'receiveReference', 'receiveQr', 'fundingCodesReady', 'fundingCodes'));
    }

    public function topup(Request $request, WalletFundingService $funding)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'], 'request_key' => ['required', 'uuid']]);
        $intent = $funding->create($request->user(), CheckoutPricingService::cents($data['amount']), $data['request_key'], $request->user()->isCommerceAdmin());

        return back()->with('success', 'Sandbox funding intent '.$intent->reference.' created. Balance changes only after a signed mock-provider event.');
    }

    public function simulate(Request $request, WalletFundingIntent $intent, WalletFundingService $funding)
    {
        $data = $request->validate(['result' => ['required', 'in:paid,failed,reversed']]);
        $funding->simulate($intent, $request->user(), $data['result']);

        return back()->with('success', 'Mock-provider event processed. No real money was transferred.');
    }

    public function webhook(Request $request, WalletFundingService $funding)
    {
        $intent = $funding->callback($request->getContent(), (string) $request->header('X-EZiCart-Signature'), (int) $request->header('X-EZiCart-Timestamp'));

        return response()->json(['reference' => $intent->reference, 'status' => $intent->status]);
    }

    public function withdraw(Request $request, WalletWithdrawalService $withdrawals)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'], 'destination' => ['required', 'string', 'min:5', 'max:500'], 'request_key' => ['required', 'uuid']]);
        $withdrawals->request($request->user(), CheckoutPricingService::cents($data['amount']), $data['destination'], $data['request_key']);

        return back()->with('success', 'Withdrawal requested. Funds are held pending authorized review; execution is sandbox-only.');
    }

    public function reviewWithdrawal(Request $request, WalletWithdrawal $withdrawal, WalletWithdrawalService $service)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject,settle'], 'reason' => ['required', 'string', 'min:5', 'max:500'], 'reference' => ['required_if:decision,settle', 'nullable', 'string', 'min:5', 'max:120']]);
        if ($data['decision'] === 'settle') {
            $service->settle($withdrawal, $request->user(), $data['reference'], $data['reason']);
        } else {
            $service->review($withdrawal, $request->user(), $data['decision'], $data['reason']);
        }

        return back()->with('success', 'Withdrawal decision recorded.');
    }

    public function batches(Request $request)
    {
        $actor = $request->user();
        abort_unless(in_array($actor->role, ['courier', 'sorting_center', 'logistics_admin'], true), 403);
        $reports = app(RemittanceReportService::class);
        $filters = $reports->filters($request->only(['from', 'to', 'status', 'rider_id', 'hub_id']));
        $batchQuery = $reports->query($actor, $filters);
        $batches = (clone $batchQuery)->latest('id')->paginate(25)->withQueryString();
        $collections = OrderFinance::where('remittance_status', 'COLLECTED')->when($actor->role === 'courier', fn ($q) => $q->where('collector_id', $actor->id))->when($actor->role === 'sorting_center', fn ($q) => $q->whereHas('order', fn ($o) => $o->where('sorting_center_id', $actor->id)))->with('order')->oldest('id')->paginate(25, ['*'], 'collections_page')->withQueryString();
        $hubs = User::where('role', 'sorting_center')->where('status', 'approved')->orderBy('business_name')->get();
        $totals = (clone $batchQuery)->selectRaw('SUM(expected_cents) as expected, SUM(submitted_cents) as submitted, SUM(received_cents) as received, SUM(discrepancy_cents) as discrepancy')->first();
        $items = DB::table('remittance_batch_items')->join('order_finances', 'order_finances.id', '=', 'remittance_batch_items.order_finance_id')->join('orders', 'orders.id', '=', 'order_finances.order_id')->whereIn('batch_id', $batches->pluck('id'))->select('remittance_batch_items.*', 'orders.order_number')->get()->groupBy('batch_id');
        $events = DB::table('remittance_batch_events')->whereIn('batch_id', $batches->pluck('id'))->orderBy('id')->get()->groupBy('batch_id');

        return view('finance.remittances', compact('actor', 'batches', 'collections', 'hubs', 'totals', 'items', 'events', 'filters'));
    }

    public function submitBatch(Request $request, RemittanceBatchService $service)
    {
        $data = $request->validate(['hub_id' => ['required', 'integer', 'exists:users,id'], 'finance_ids' => ['required', 'array', 'min:1', 'max:100'], 'finance_ids.*' => ['integer', 'distinct'], 'amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'], ...$this->evidenceRules()]);
        $this->withEvidence($request, fn ($path) => $service->submit($request->user(), User::findOrFail($data['hub_id']), $data['finance_ids'], CheckoutPricingService::cents($data['amount']), $path, $data['reason']));

        return back()->with('success', 'Batch submitted. Receiving personnel must count and verify the physical cash.');
    }

    public function reviewBatch(Request $request, RemittanceBatch $batch, RemittanceBatchService $service)
    {
        $data = $request->validate(['decision' => ['required', 'in:receive,investigate,reconcile'], 'amount' => ['required_unless:decision,investigate', 'nullable', 'numeric', 'min:0', 'decimal:0,2'], 'reason' => ['required', 'string', 'min:5', 'max:500'], 'evidence' => ['required_unless:decision,investigate', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']]);
        if ($data['decision'] === 'investigate') {
            $service->investigate($batch, $request->user(), $data['reason']);
        } else {
            $this->withEvidence($request, fn ($path) => $data['decision'] === 'receive' ? $service->receive($batch, $request->user(), CheckoutPricingService::cents($data['amount']), $path, $data['reason']) : $service->reconcile($batch, $request->user(), CheckoutPricingService::cents($data['amount']), $path, $data['reason']));
        }

        return back()->with('success', 'Cash receipt/reconciliation event recorded.');
    }

    public function batchEvidence(Request $request, RemittanceBatch $batch, string $kind)
    {
        abort_unless($this->batchQuery($request->user())->whereKey($batch->id)->exists(), 403);
        abort_unless(in_array($kind, ['submission', 'receipt'], true), 404);
        $path = $kind === 'receipt' ? $batch->receipt_path : $batch->evidence_path;
        abort_unless($path && Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->download($path);
    }

    public function rates(Request $request)
    {
        abort_unless($request->user()->isLogisticsAdmin(), 403);

        return view('finance.rates', ['rates' => DeliveryRateVersion::latest('id')->paginate(25), 'components' => DeliveryPricingService::COMPONENTS]);
    }

    public function storeRate(Request $request, DeliveryRateManagementService $service)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'route_class' => ['required', 'string'], 'volumetric_divisor' => ['required', 'integer'], 'max_kg' => ['required', 'numeric', 'min:0.001', 'max:1000'], 'effective_at' => ['required', 'date'], 'components' => ['required', 'array'], 'components.*' => ['numeric', 'min:0', 'decimal:0,2'], 'sandbox' => ['nullable', 'boolean'], 'rules_json' => ['nullable', 'json']]);
        $rules = ! empty($data['rules_json']) ? json_decode($data['rules_json'], true, 32, JSON_THROW_ON_ERROR) : ['weight_brackets' => [['max_grams' => (int) round($data['max_kg'] * 1000), 'components' => array_map(fn ($amount) => CheckoutPricingService::cents($amount), $data['components'])]]];
        $service->create($request->user(), [...$data, 'sandbox' => $request->boolean('sandbox'), 'rules' => $rules]);

        return back()->with('success', 'Tariff draft created. An independent logistics administrator must approve it.');
    }

    public function approveRate(Request $request, DeliveryRateVersion $rate, DeliveryRateManagementService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->approve($rate, $request->user(), $data['reason']);

        return back()->with('success', 'Tariff version approved. Only future quotes use effective rates after activation.');
    }

    public function activateRates(Request $request, DeliveryRateManagementService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->activate($request->user(), $data['reason']);

        return back()->with('success', 'Approved delivery tariffs activated for future orders.');
    }

    public function shipments(Request $request)
    {
        $actor = $request->user();
        $orders = $this->shipmentQuery($actor)->with('seller')->latest('id')->paginate(25)->withQueryString();
        $snapshots = ShipmentFinancial::whereIn('order_id', $orders->pluck('id'))->get()->keyBy('order_id');
        $adjustments = ShipmentPriceAdjustment::whereIn('order_id', $orders->pluck('id'))->latest('id')->get()->groupBy('order_id');

        return view('finance.shipments', compact('actor', 'orders', 'snapshots', 'adjustments'));
    }

    public function proposeAdjustment(Request $request, Order $order, ShipmentAdjustmentService $service)
    {
        $data = $request->validate(['weight_kg' => ['required', 'numeric', 'min:0.001', 'max:1000'], 'length_mm' => ['required', 'integer', 'min:1', 'max:5000'], 'width_mm' => ['required', 'integer', 'min:1', 'max:5000'], 'height_mm' => ['required', 'integer', 'min:1', 'max:5000'], ...$this->evidenceRules()]);
        $this->withEvidence($request, fn ($path) => $service->propose($order, $request->user(), (int) round($data['weight_kg'] * 1000), [(int) $data['length_mm'], (int) $data['width_mm'], (int) $data['height_mm']], $path, $data['reason']));

        return back()->with('success', 'Measured parcel price adjustment proposed for treasury review. Buyer totals have not changed.');
    }

    public function approveAdjustment(Request $request, ShipmentPriceAdjustment $adjustment, ShipmentAdjustmentService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->approve($adjustment, $request->user(), $data['reason']);

        return back()->with('success', 'Measurement adjustment approved and any extra platform funding recorded.');
    }

    public function adjustmentEvidence(Request $request, ShipmentPriceAdjustment $adjustment)
    {
        $actor = $request->user();
        abort_unless($actor->isCommerceAdmin() || $actor->isLogisticsAdmin() || $actor->id === $adjustment->proposed_by, 403);
        abort_unless(Storage::disk('private')->exists($adjustment->evidence_path), 404);

        return Storage::disk('private')->download($adjustment->evidence_path);
    }

    public function proposeOperatingFunds(Request $request, Order $order, ShipmentAdjustmentService $service)
    {
        $data = $request->validate(['return_cost' => ['nullable', 'boolean'], 'amount' => ['required_unless:return_cost,1', 'nullable', 'numeric', 'min:0.01', 'decimal:0,2'], ...$this->evidenceRules()]);
        $this->withEvidence($request, fn ($path) => $service->proposeOperatingFunds($order, $request->user(), CheckoutPricingService::cents($data['amount'] ?? 0), $path, $data['reason'], $request->boolean('return_cost')));

        return back()->with('success', 'Operational funding request submitted for treasury review. No additional buyer charge was made.');
    }

    public function settleReturnCosts(Request $request, Order $order, LogisticsSettlementService $service)
    {
        $service->settleReturned($order, $request->user());

        return back()->with('success', 'Treasury-funded return and evidenced delivery costs allocated.');
    }

    public function walletRefund(Request $request, RefundExecution $refund, OrderWalletService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->refund($refund, $request->user(), $data['reason']);

        return back()->with('success', 'Approved refund credited to the buyer wallet.');
    }

    public function settleSellerWallet(Request $request, SellerPayout $payout, OrderFinancialService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->settleToWallet($payout, $request->user(), $data['reason']);

        return back()->with('success', 'Eligible seller proceeds and logistics allocations posted to their financial accounts.');
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $actor = $request->user();
        abort_unless(in_array($type, ['wallet', 'remittances', 'shipping'], true), 404);
        if ($type === 'remittances') {
            abort_unless(in_array($actor->role, ['courier', 'sorting_center', 'logistics_admin'], true), 403);

            return $this->exportRemittances($request);
        }
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        return response()->streamDownload(function () use ($actor, $type, $request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['EZiCart Financial Statement', 'PHP', 'Generated '.now()->toIso8601String(), 'Sandbox wallet values are not live e-money'], escape: '');
            if ($type === 'wallet') {
                $accountIds = FinancialAccount::where('user_id', $actor->id)->pluck('id');
                if ($actor->isCommerceAdmin()) {
                    $accountIds = $accountIds->merge(FinancialAccount::where('key', 'platform:treasury')->pluck('id'));
                }
                fputcsv($handle, ['Entry ID', 'Transaction reference', 'Type', 'Account ID', 'Delta PHP', 'Before PHP', 'After PHP', 'Created at'], escape: '');
                foreach (LedgerEntry::whereIn('account_id', $accountIds)->with('transaction')->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))->orderBy('id')->lazyById() as $entry) {
                    fputcsv($handle, [$entry->id, $entry->transaction->reference, $entry->transaction->type, $entry->account_id, $entry->delta_cents / 100, $entry->balance_before_cents / 100, $entry->balance_after_cents / 100, $entry->created_at], escape: '');
                }
            } else {
                fputcsv($handle, ['Order', 'Buyer shipping PHP', 'Seller shipping PHP', 'Platform shipping PHP', 'Additional funding PHP', 'Logistics cost PHP', 'Unallocated PHP', 'Net margin PHP'], escape: '');
                foreach ($this->shipmentQuery($actor)->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))->orderBy('id')->cursor() as $order) {
                    $snapshot = ShipmentFinancial::where('order_id', $order->id)->first();
                    if (! $snapshot) {
                        continue;
                    }
                    fputcsv($handle, [$order->order_number, $snapshot->buyer_shipping_cents / 100, $snapshot->seller_shipping_cents / 100, $snapshot->platform_shipping_cents / 100, $snapshot->additional_funding_cents / 100, ($snapshot->settlement_snapshot['logistics_cost_cents'] ?? 0) / 100, ($snapshot->settlement_snapshot['unallocated_cents'] ?? 0) / 100, ($snapshot->settlement_snapshot['net_margin_cents'] ?? 0) / 100], escape: '');
                }
            }
            fclose($handle);
        }, 'ezicart-'.$type.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportRemittances(Request $request): StreamedResponse
    {
        $data = $request->validate(['format' => ['nullable', 'in:csv,xlsx']]);
        $format = $data['format'] ?? 'csv';
        $reports = app(RemittanceReportService::class);
        $report = $reports->report($request->user(), $reports->filters($request->only(['from', 'to', 'status', 'rider_id', 'hub_id'])));
        $content = null;
        if ($format === 'xlsx') {
            $content = app(AnalyticsExportService::class)->xlsx(['filters' => ['from' => $report['filters']['from'] ?? 'All dates', 'to' => $report['filters']['to'] ?? 'All dates'], 'timezone' => $report['timezone']], $reports->sheets($report));
        }

        return response()->streamDownload(function () use ($format, $report, $reports, $content): void {
            if ($format === 'xlsx') {
                echo $content;
            } else {
                app(TabularExportService::class)->csv($reports->csvRows($report), true);
            }
        }, 'ezicart-remittances-'.now()->format('Ymd-His').'.'.$format, ['Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function withdrawalQuery(User $actor)
    {
        return WalletWithdrawal::query()->when($actor->isCommerceAdmin() || $actor->isLogisticsAdmin(), fn ($q) => $q->whereIn('user_id', User::whereIn('role', $actor->managedAccountRoles())->select('id')), fn ($q) => $q->where('user_id', $actor->id));
    }

    private function shipmentQuery(User $actor)
    {
        return Order::query()->where('financial_version', 1)->when(! $actor->isCommerceAdmin() && ! $actor->isLogisticsAdmin(), function ($q) use ($actor): void {
            $q->where(function ($owned) use ($actor): void {
                match ($actor->role) {
                    'buyer' => $owned->where('buyer_id', $actor->id), 'seller' => $owned->where('seller_id', $actor->id),
                    'sorting_center' => $owned->where('sorting_center_id', $actor->id)->orWhereHas('transfers', fn ($t) => $t->where('source_hub_id', $actor->id)->orWhere('destination_hub_id', $actor->id)),
                    'courier' => $owned->where('pickup_courier_id', $actor->id)->orWhere('delivery_courier_id', $actor->id)->orWhereHas('transfers', fn ($t) => $t->where('carrier_rider_id', $actor->id)),
                    default => $owned->whereRaw('1 = 0'),
                };
            });
        });
    }

    private function evidenceRules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:500'], 'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']];
    }

    private function withEvidence(Request $request, callable $action): void
    {
        $path = $request->file('evidence')->store('finance-evidence', 'private');
        try {
            $action($path);
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw $exception;
        }
    }
}
