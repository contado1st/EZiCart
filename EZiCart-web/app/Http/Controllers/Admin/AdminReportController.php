<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderFinance;
use App\Models\RefundExecution;
use App\Models\SellerPayout;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['from_date' => ['nullable', 'date'], 'to_date' => ['nullable', 'date']]);
        $fromDate = $request->input('from_date')
            ? Carbon::parse($request->input('from_date'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $toDate = $request->input('to_date')
            ? Carbon::parse($request->input('to_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        $baseQuery = Order::whereBetween('created_at', [$fromDate, $toDate]);
        if ($fromDate->gt($toDate)) {
            throw ValidationException::withMessages(['to_date' => 'The end date must not precede the start date.']);
        }

        $completedQuery = (clone $baseQuery)->where('status', 'COMPLETED');
        $completedOrders = (clone $completedQuery)->with(['seller', 'buyer'])->latest()->paginate(30)->withQueryString();

        $totalGmv = (clone $completedQuery)->sum('total_amount');
        $totalProductSales = (clone $completedQuery)->sum('subtotal');
        $totalCommission = OrderFinance::query()->where('remittance_status', 'VERIFIED')->whereBetween('verified_at', [$fromDate, $toDate])
            ->whereHas('order', fn ($q) => $q->where('status', 'COMPLETED')->whereDoesntHave('refundExecutions'))->sum('commission_cents') / 100;
        $totalDiscounts = (clone $completedQuery)->sum('discount_amount');

        $stats = [
            'total_gmv' => $totalGmv,
            'product_sales' => $totalProductSales,
            'total_commission' => $totalCommission,
            'total_discounts' => $totalDiscounts,
            'completed_count' => (clone $completedQuery)->count(),
            'total_orders' => (clone $baseQuery)->count(),
            'verified_remittances' => OrderFinance::query()->where('remittance_status', 'VERIFIED')->whereBetween('verified_at', [$fromDate, $toDate])->sum('remitted_cents') / 100,
            'executed_refunds' => RefundExecution::query()->where('status', 'EXECUTED')->whereBetween('executed_at', [$fromDate, $toDate])->sum('amount_cents') / 100,
            'settled_payouts' => SellerPayout::query()->where('status', 'SETTLED')->whereBetween('executed_at', [$fromDate, $toDate])->sum('amount_cents') / 100,
            'funded_promotions' => OrderFinance::query()->whereBetween('funded_at', [$fromDate, $toDate])->sum('funded_cents') / 100,
        ];

        return view('admin.reports.index', compact(
            'stats',
            'completedOrders',
            'fromDate',
            'toDate'
        ));
    }
}
