<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SellerReportController extends Controller
{
    public function index(Request $request)
    {
        $sellerId = auth()->id();

        $fromDate = $request->input('from_date') 
            ? Carbon::parse($request->input('from_date'))->startOfDay() 
            : Carbon::now()->startOfMonth();

        $toDate = $request->input('to_date') 
            ? Carbon::parse($request->input('to_date'))->endOfDay() 
            : Carbon::now()->endOfDay();

        // Base query restricted to the authenticated merchant within the date range
        $ordersQuery = Order::where('seller_id', $sellerId)
            ->whereBetween('created_at', [$fromDate, $toDate]);

        $allOrders = (clone $ordersQuery)->latest()->get();

        // Financial figures are derived exclusively from completed orders
        $completedOrders = (clone $ordersQuery)->where('status', 'COMPLETED')->get();

        $grossSales = $completedOrders->sum('subtotal');
        $totalDiscounts = $completedOrders->sum('discount_amount');
        $platformCommissions = $completedOrders->sum('commission_fee');
        $netProfit = max(0, $grossSales - $platformCommissions);

        $stats = [
            'gross_sales'          => $grossSales,
            'total_discounts'      => $totalDiscounts,
            'platform_commission'  => $platformCommissions,
            'net_profit'           => $netProfit,
            'completed_count'      => $completedOrders->count(),
            'total_orders_placed'  => $allOrders->count(),
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