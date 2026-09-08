<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class BuyerController extends Controller
{
    public function dashboard(Request $request)
    {
        $status = $request->query('status');

        $query = auth()->user()->buyerOrders()
            ->with(['seller', 'items.product'])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(10)->withQueryString();

        $counts = [
            'all'        => auth()->user()->buyerOrders()->count(),
            'to_ship'    => auth()->user()->buyerOrders()->whereIn('status', ['PLACED', 'CONFIRMED', 'PREPARING'])->count(),
            'to_receive' => auth()->user()->buyerOrders()->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])->count(),
            'completed'  => auth()->user()->buyerOrders()->where('status', 'COMPLETED')->count(),
        ];

        return view('buyer.dashboard', compact('orders', 'counts'));
    }

    public function showOrder(Order $order)
    {
        abort_if($order->buyer_id !== auth()->id(), 403);

        $order->load(['seller', 'items.product']);

        return view('buyer.orders.show', compact('order'));
    }

    public function confirmReceived(Order $order)
    {
        abort_if($order->buyer_id !== auth()->id(), 403);
        abort_if($order->status !== 'DELIVERED', 400);

        $order->update(['status' => 'COMPLETED']);

        return back()->with('success', 'Order marked as completed! Thank you for confirming.');
    }
}