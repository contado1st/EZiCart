<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDate = $request->input('from_date') 
            ? Carbon::parse($request->input('from_date'))->startOfDay() 
            : Carbon::now()->startOfMonth();

        $toDate = $request->input('to_date') 
            ? Carbon::parse($request->input('to_date'))->endOfDay() 
            : Carbon::now()->endOfDay();

        $baseQuery = Order::whereBetween('created_at', [$fromDate, $toDate]);

        $allOrders = (clone $baseQuery)->with(['seller', 'buyer'])->latest()->get();
        $completedOrders = (clone $baseQuery)->where('status', 'COMPLETED')->with(['seller', 'buyer'])->latest()->get();

        $totalGmv = $completedOrders->sum('total_amount');
        $totalProductSales = $completedOrders->sum('subtotal');
        $totalCommission = $completedOrders->sum('commission_fee');
        $totalDiscounts = $completedOrders->sum('discount_amount');

        $stats = [
            'total_gmv'         => $totalGmv,
            'product_sales'     => $totalProductSales,
            'total_commission'  => $totalCommission,
            'total_discounts'   => $totalDiscounts,
            'completed_count'   => $completedOrders->count(),
            'total_orders'      => $allOrders->count(),
        ];

        return view('admin.reports.index', compact(
            'stats',
            'completedOrders',
            'allOrders',
            'fromDate',
            'toDate'
        ));
    }
}