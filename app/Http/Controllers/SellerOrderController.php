<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class SellerOrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $query = auth()->user()->sellerOrders()
            ->with(['buyer', 'items'])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(10)->withQueryString();

        $counts = [
            'all'       => auth()->user()->sellerOrders()->count(),
            'placed'    => auth()->user()->sellerOrders()->where('status', 'PLACED')->count(),
            'preparing' => auth()->user()->sellerOrders()->whereIn('status', ['CONFIRMED', 'PREPARING'])->count(),
            'ready'     => auth()->user()->sellerOrders()->where('status', 'READY_FOR_PICKUP')->count(),
            'completed' => auth()->user()->sellerOrders()->where('status', 'COMPLETED')->count(),
        ];

        return view('seller.orders.index', compact('orders', 'counts'));
    }

    public function show(Order $order)
    {
        abort_if($order->seller_id !== auth()->id(), 403);

        $order->load(['buyer', 'items.product']);

        return view('seller.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        abort_if($order->seller_id !== auth()->id(), 403);

        $validated = $request->validate([
            'status' => 'required|in:CONFIRMED,PREPARING,READY_FOR_PICKUP',
        ]);

        $order->update(['status' => $validated['status']]);

        return back()->with('success', 'Order status updated to ' . str_replace('_', ' ', $validated['status']) . '.');
    }

    public function waybill(Order $order)
    {
        abort_if($order->seller_id !== auth()->id(), 403);

        $order->load(['seller', 'buyer', 'items']);

        return view('seller.orders.waybill', compact('order'));
    }
}