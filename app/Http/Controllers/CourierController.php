<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    public function dashboard(Request $request)
    {
        $courier = auth()->user();

        // Available packages ready for pickup anywhere in the network
        $availablePickups = Order::where('status', 'READY_FOR_PICKUP')
            ->whereNull('courier_id')
            ->with(['seller', 'buyer', 'items'])
            ->latest()
            ->get();

        // Active parcels assigned to this courier
        $activeDeliveries = $courier->courierDeliveries()
            ->whereIn('status', ['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])
            ->with(['seller', 'buyer', 'items'])
            ->latest()
            ->get();

        // Historical completed or failed parcels
        $completedDeliveries = $courier->courierDeliveries()
            ->whereIn('status', ['DELIVERED', 'COMPLETED', 'DELIVERY_FAILED', 'RETURNED'])
            ->with(['seller', 'buyer', 'items'])
            ->latest()
            ->take(10)
            ->get();

        $stats = [
            'available' => $availablePickups->count(),
            'active'    => $activeDeliveries->count(),
            'delivered' => $courier->courierDeliveries()->whereIn('status', ['DELIVERED', 'COMPLETED'])->count(),
            'failed'    => $courier->courierDeliveries()->whereIn('status', ['DELIVERY_FAILED', 'RETURNED'])->count(),
        ];

        return view('courier.dashboard', compact('stats', 'availablePickups', 'activeDeliveries', 'completedDeliveries'));
    }

    public function claimPickup(Order $order)
    {
        if ($order->status !== 'READY_FOR_PICKUP' || $order->courier_id !== null) {
            return back()->with('error', 'This package has already been claimed or is not ready for pickup.');
        }

        $order->update([
            'courier_id' => auth()->id(),
            'status'     => 'PICKED_UP',
        ]);

        return back()->with('success', "Order #{$order->order_number} claimed and marked as Picked Up.");
    }

    public function updateStatus(Request $request, Order $order)
    {
        abort_if($order->courier_id !== auth()->id(), 403);

        $validated = $request->validate([
            'status' => 'required|in:OUT_FOR_DELIVERY,DELIVERED,DELIVERY_FAILED',
        ]);

        $order->update(['status' => $validated['status']]);

        return back()->with('success', "Order #{$order->order_number} status updated to " . str_replace('_', ' ', $validated['status']) . ".");
    }
}