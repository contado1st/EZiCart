<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class LogisticsController extends Controller
{
    public function dashboard(Request $request)
    {
        // 1. Parcels in transit from seller to sorting center
        $inboundParcels = Order::where('status', 'PICKED_UP')
            ->with(['seller', 'pickupCourier', 'items'])
            ->latest()
            ->get();

        // 2. Parcels arrived at sorting center awaiting sorting
        $atCenterParcels = Order::where('status', 'AT_SORTING_CENTER')
            ->with(['seller', 'buyer', 'items'])
            ->latest()
            ->get();

        // 3. Sorted parcels awaiting rider assignment
        $sortedParcels = Order::where('status', 'SORTED')
            ->with(['buyer'])
            ->latest()
            ->get();

        // 4. Parcels assigned or out for delivery
        $dispatchedParcels = Order::whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])
            ->with(['deliveryCourier', 'buyer'])
            ->latest()
            ->get();

        // Approved active delivery riders available for assignment
        $riders = User::where('role', 'courier')
            ->where('status', 'approved')
            ->orderBy('first_name')
            ->get();

        $stats = [
            'inbound'    => $inboundParcels->count(),
            'at_center'  => $atCenterParcels->count(),
            'sorted'     => $sortedParcels->count(),
            'dispatched' => $dispatchedParcels->count(),
        ];

        return view('logistics.dashboard', compact(
            'stats',
            'inboundParcels',
            'atCenterParcels',
            'sortedParcels',
            'dispatchedParcels',
            'riders'
        ));
    }

    public function receiveParcel(Order $order)
    {
        if ($order->status !== 'PICKED_UP') {
            return back()->with('error', 'Only parcels in transit from sellers can be received.');
        }

        $order->update([
            'status'            => 'AT_SORTING_CENTER',
            'sorting_center_id' => auth()->id(),
        ]);

        return back()->with('success', "Parcel {$order->order_number} scanned and recorded at Sorting Center.");
    }

    public function sortParcel(Request $request, Order $order)
    {
        $validated = $request->validate([
            'delivery_area' => 'required|string|max:100',
        ]);

        $order->update([
            'delivery_area' => $validated['delivery_area'],
            'status'        => 'SORTED',
        ]);

        return back()->with('success', "Parcel {$order->order_number} sorted to {$validated['delivery_area']}.");
    }

    public function assignRider(Request $request, Order $order)
    {
        $validated = $request->validate([
            'delivery_courier_id' => 'required|exists:users,id',
        ]);

        $order->update([
            'delivery_courier_id' => $validated['delivery_courier_id'],
            'status'              => 'ASSIGNED_TO_RIDER',
        ]);

        $rider = User::find($validated['delivery_courier_id']);

        return back()->with('success', "Parcel {$order->order_number} assigned to rider {$rider->first_name} {$rider->last_name}.");
    }

    public function riders()
    {
        $pendingRiders = User::where('role', 'courier')->where('status', 'pending')->latest()->get();
        $approvedRiders = User::where('role', 'courier')->where('status', 'approved')->latest()->get();

        return view('logistics.riders', compact('pendingRiders', 'approvedRiders'));
    }

    public function approveRider(User $user)
    {
        abort_if($user->role !== 'courier', 400);

        $user->update(['status' => 'approved']);

        return back()->with('success', "Courier {$user->first_name} {$user->last_name} approved for dispatch.");
    }

    public function rejectRider(User $user)
    {
        abort_if($user->role !== 'courier', 400);

        $user->update(['status' => 'rejected']);

        return back()->with('success', "Courier application rejected.");
    }
}