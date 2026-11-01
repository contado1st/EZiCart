<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderFinance;
use App\Models\RefundExecution;
use App\Models\SellerPayout;
use App\Services\CheckoutPricingService;
use App\Services\OrderFinancialService;
use App\Services\ReplacementOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FinancialController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        $finances = OrderFinance::query()->with('order')
            ->when($actor->role === 'buyer', fn ($q) => $q->whereHas('order', fn ($order) => $order->where('buyer_id', $actor->id)))
            ->when($actor->role === 'seller', fn ($q) => $q->whereHas('order', fn ($order) => $order->where('seller_id', $actor->id)))
            ->when($actor->role === 'courier', fn ($q) => $q->where('collector_id', $actor->id))
            ->latest('id')->paginate(20);
        $refunds = RefundExecution::query()->with('order')
            ->when($actor->role === 'buyer', fn ($q) => $q->whereHas('order', fn ($order) => $order->where('buyer_id', $actor->id)))
            ->when($actor->role === 'seller', fn ($q) => $q->whereHas('order', fn ($order) => $order->where('seller_id', $actor->id)))
            ->when(! $actor->isCommerceAdmin() && ! in_array($actor->role, ['buyer', 'seller'], true), fn ($q) => $q->whereRaw('1 = 0'))
            ->latest('id')->paginate(20, ['*'], 'refunds_page');
        $payouts = SellerPayout::query()->with('order')
            ->when($actor->role === 'seller', fn ($q) => $q->where('seller_id', $actor->id))
            ->when(! $actor->isCommerceAdmin() && $actor->role !== 'seller', fn ($q) => $q->whereRaw('1 = 0'))
            ->latest('id')->paginate(20, ['*'], 'payouts_page');

        return view('finance.index', compact('finances', 'refunds', 'payouts', 'actor'));
    }

    public function remittance(Request $request, Order $order, OrderFinancialService $service)
    {
        abort_unless($request->user()->role === 'courier' && $order->delivery_courier_id === $request->user()->id, 403);
        $data = $request->validate([
            ...$this->evidenceRules(), 'amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'reference' => ['required', 'string', 'max:120', Rule::unique('order_finances', 'remittance_reference')->ignore(OrderFinance::where('order_id', $order->id)->value('id'))],
        ]);
        $this->withEvidence($request, fn ($path) => $service->submitRemittance($order, $request->user(), CheckoutPricingService::cents($data['amount']), $data['reference'], $path, $data['reason']));

        return back()->with('success', 'Remittance submitted. Logistics must verify the cash receipt; discrepancies remain open.');
    }

    public function verifyRemittance(Request $request, Order $order, OrderFinancialService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->verifyRemittance($order, $request->user(), $data['reason']);

        return back()->with('success', 'Remittance receipt verified.');
    }

    public function refund(Request $request, RefundExecution $refund, OrderFinancialService $service)
    {
        abort_unless($request->user()->isCommerceAdmin(), 403);
        $data = $request->validate([...$this->evidenceRules(), 'reference' => ['required', 'string', 'max:120', 'unique:refund_executions,reference']]);
        $this->withEvidence($request, fn ($path) => $service->executeRefund($refund, $request->user(), $data['reference'], $path, $data['reason']));

        return back()->with('success', 'External refund execution recorded with evidence and reference.');
    }

    public function requestPayout(Request $request, Order $order, OrderFinancialService $service)
    {
        $service->requestPayout($order, $request->user());

        return back()->with('success', 'Payout request recorded. Approval and external settlement are pending.');
    }

    public function fundSubsidy(Request $request, Order $order, OrderFinancialService $service)
    {
        abort_unless($request->user()->isCommerceAdmin(), 403);
        $data = $request->validate([...$this->evidenceRules(), 'reference' => ['required', 'string', 'max:120', 'unique:order_finances,funding_reference']]);
        $this->withEvidence($request, fn ($path) => $service->fundPlatformSubsidy($order, $request->user(), $data['reference'], $path, $data['reason']));

        return back()->with('success', 'External platform promotion funding recorded. Seller proceeds are preserved.');
    }

    public function approvePayout(Request $request, SellerPayout $payout, OrderFinancialService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->approvePayout($payout, $request->user(), $data['reason']);

        return back()->with('success', 'Payout approved. External settlement is pending.');
    }

    public function settlePayout(Request $request, SellerPayout $payout, OrderFinancialService $service)
    {
        abort_unless($request->user()->isCommerceAdmin(), 403);
        $data = $request->validate([...$this->evidenceRules(), 'reference' => ['required', 'string', 'max:120', 'unique:seller_payouts,reference']]);
        $this->withEvidence($request, fn ($path) => $service->settlePayout($payout, $request->user(), $data['reference'], $path, $data['reason']));

        return back()->with('success', 'External payout settlement recorded with evidence and reference.');
    }

    public function replacement(Request $request, Dispute $dispute, ReplacementOrderService $service)
    {
        $order = $service->create($dispute, $request->user());

        return redirect()->route('seller.orders.show', $order)->with('success', 'No-charge replacement shipment is ready for seller confirmation.');
    }

    public function evidence(Request $request, string $type, int $record)
    {
        $actor = $request->user();
        $entry = match ($type) {
            'remittance', 'funding' => OrderFinance::findOrFail($record),
            'refund' => RefundExecution::findOrFail($record),
            'payout' => SellerPayout::findOrFail($record),
            default => abort(404),
        };
        $allowed = $actor->isCommerceAdmin() || match ($type) {
            'remittance' => $actor->isLogisticsAdmin() || ($actor->role === 'courier' && $entry->collector_id === $actor->id),
            'refund' => $actor->role === 'buyer' && $entry->order->buyer_id === $actor->id,
            'payout' => $actor->role === 'seller' && $entry->seller_id === $actor->id,
            'funding' => false,
        };
        abort_unless($allowed, 403);
        $path = match ($type) {
            'remittance' => $entry->receipt_path, 'funding' => $entry->funding_evidence_path, default => $entry->evidence_path
        };
        abort_unless($path && Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->download($path, basename($path));
    }

    private function evidenceRules(): array
    {
        return ['reference' => ['required', 'string', 'max:120'], 'reason' => ['required', 'string', 'min:5', 'max:500'], 'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']];
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
