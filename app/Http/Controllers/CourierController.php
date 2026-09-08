<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    public function dashboard()
    {
        $courier = auth()->user();

        // 1. Stage 1: Available Seller Pickups across the network
        $availablePickups = Order::where('status', 'READY_FOR_PICKUP')
            ->whereNull('pickup_courier_id')
            ->with(['seller', 'buyer', 'items'])
            ->latest()
            ->get();

        // 2. Stage 1: Active Pickups in transit to Sorting Center
        $myActivePickups = Order::where('pickup_courier_id', $courier->id)
            ->where('status', 'PICKED_UP')
            ->with(['seller', 'items'])
            ->latest()
            ->get();

        // 3. Stage 2: Doorstep Delivery Assignments given by Sorting Center
        $myDeliveryAssignments = Order::where('delivery_courier_id', $courier->id)
            ->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])
            ->with(['buyer', 'seller', 'items'])
            ->latest()
            ->get();

        // 4. Completed / History
        $completedDeliveries = Order::where('delivery_courier_id', $courier->id)
            ->whereIn('status', ['DELIVERED', 'COMPLETED', 'DELIVERY_FAILED'])
            ->latest()
            ->take(10)
            ->get();

        $stats = [
            'available_pickups' => $availablePickups->count(),
            'in_transit_hub'    => $myActivePickups->count(),
            'assigned_delivery' => $myDeliveryAssignments->count(),
            'completed'         => $completedDeliveries->whereIn('status', ['DELIVERED', 'COMPLETED'])->count(),
        ];

        return view('courier.dashboard', compact(
            'stats',
            'availablePickups',
            'myActivePickups',
            'myDeliveryAssignments',
            'completedDeliveries'
        ));
    }

    public function claimPickup(Order $order)
    {
        if ($order->status !== 'READY_FOR_PICKUP' || $order->pickup_courier_id !== null) {
            return back()->with('error', 'Package is no longer available for pickup.');
        }

        $order->update([
            'pickup_courier_id' => auth()->id(),
            'status'            => 'PICKED_UP',
        ]);

        return back()->with('success', "Order #{$order->order_number} claimed. Deliver package to Sorting Center.");
    }

    public function startDelivery(Order $order)
    {
        abort_if($order->delivery_courier_id !== auth()->id(), 403);
        abort_if($order->status !== 'ASSIGNED_TO_RIDER', 400);

        $order->update(['status' => 'OUT_FOR_DELIVERY']);

        return back()->with('success', "Order #{$order->order_number} is now Out for Delivery.");
    }

    public function completeDelivery(Request $request, Order $order)
    {
        abort_if($order->delivery_courier_id !== auth()->id(), 403);

        $validated = $request->validate([
            'status' => 'required|in:DELIVERED,DELIVERY_FAILED',
        ]);

        $order->update(['status' => $validated['status']]);

        return back()->with('success', "Order #{$order->order_number} marked as " . str_replace('_', ' ', $validated['status']) . ".");
    }
}