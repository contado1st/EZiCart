<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SellerController extends Controller
{
    public function dashboard()
    {
        $seller = auth()->user();

        // Calculate Net Earnings (Subtotal minus platform commission)
        $netSales = $seller->sellerOrders()
            ->whereIn('status', ['DELIVERED', 'COMPLETED'])
            ->selectRaw('SUM(subtotal - commission_fee) as total')
            ->value('total') ?? 0.00;

        $stats = [
            'total_sales'    => $netSales,
            'pending_orders' => $seller->sellerOrders()->where('status', 'PLACED')->count(),
            'low_stock'      => $seller->products()->where('stock', '<', 10)->where('is_archived', false)->count(),
            'rating'         => '5.0',
        ];

        $recentOrders = $seller->sellerOrders()
            ->with(['buyer', 'items'])
            ->latest()
            ->take(5)
            ->get();

        return view('seller.dashboard', compact('stats', 'recentOrders'));
    }
}