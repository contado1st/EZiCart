<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SellerController extends Controller
{
    public function dashboard()
    {
        $seller = auth()->user();

        $stats = [
            'total_sales'    => 0.00, // Will link to Orders in the checkout phase
            'pending_orders' => 0,    // Will link to Orders table with status 'PLACED'
            'low_stock'      => $seller->products()->where('stock', '<', 10)->where('is_archived', false)->count(),
            'rating'         => '5.0',
        ];

        return view('seller.dashboard', compact('stats'));
    }
}