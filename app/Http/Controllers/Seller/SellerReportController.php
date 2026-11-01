<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SellerReportController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['from_date' => ['nullable', 'date_format:Y-m-d'], 'to_date' => ['nullable', 'date_format:Y-m-d']]);
        $sellerId = $this->authenticatedUser()->id;

        $fromDate = $request->input('from_date')
            ? Carbon::parse($request->input('from_date'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $toDate = $request->input('to_date')
            ? Carbon::parse($request->input('to_date'))->endOfDay()
            : Carbon::now()->endOfDay();
        if ($fromDate->gt($toDate)) {
            throw ValidationException::withMessages(['to_date' => 'The end date must not precede the start date.']);
        }

        // Base query restricted to the authenticated merchant within the date range
        $ordersQuery = Order::where('seller_id', $sellerId)
            ->whereBetween('created_at', [$fromDate, $toDate]);

        $allOrders = (clone $ordersQuery)->latest()->get();

        // Financial figures are derived exclusively from completed orders
        $completedOrders = (clone $ordersQuery)->where('status', 'COMPLETED')->get();

        $grossSales = $completedOrders->sum('subtotal');
        $totalDiscounts = $completedOrders->sum('discount_amount');
        $platformCommissions = $completedOrders->sum('commission_fee');
        $sellerFundedDiscounts = $completedOrders->sum(fn ($order) => max(0, (float) $order->discount_amount - (float) $order->platform_discount_amount));
        $netProfit = max(0, $grossSales - $sellerFundedDiscounts - $platformCommissions);

        $stats = [
            'gross_sales' => $grossSales,
            'total_discounts' => $totalDiscounts,
            'platform_commission' => $platformCommissions,
            'net_profit' => $netProfit,
            'completed_count' => $completedOrders->count(),
            'total_orders_placed' => $allOrders->count(),
        ];

        return view('seller.reports.index', compact(
            'stats',
            'completedOrders',
            'allOrders',
            'fromDate',
            'toDate'
        ));
    }
}
