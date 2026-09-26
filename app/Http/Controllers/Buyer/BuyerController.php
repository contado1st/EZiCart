<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use Illuminate\Http\Request;

class BuyerController extends Controller
{
    public function dashboard(Request $request)
    {
        $status = $request->query('status');

        $query = $this->authenticatedUser()->buyerOrders()
            ->with(['seller', 'items.product'])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(10)->withQueryString();

        $counts = [
            'all' => $this->authenticatedUser()->buyerOrders()->count(),
            'to_ship' => $this->authenticatedUser()->buyerOrders()->whereIn('status', ['PLACED', 'CONFIRMED', 'PREPARING'])->count(),
            'to_receive' => $this->authenticatedUser()->buyerOrders()->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])->count(),
            'completed' => $this->authenticatedUser()->buyerOrders()->where('status', 'COMPLETED')->count(),
        ];

        return view('buyer.dashboard', compact('orders', 'counts'));
    }

    public function showOrder(Order $order)
    {
        abort_if($order->buyer_id !== $this->authenticatedUser()->id, 403);

        $order->load(['seller', 'items.product', 'dispute', 'trackingEvents.actor']);

        return view('buyer.orders.show', compact('order'));
    }

    public function confirmReceived(Order $order)
    {
        abort_if($order->buyer_id !== $this->authenticatedUser()->id, 403);
        abort_if($order->status !== 'DELIVERED', 400);

        $order->update(['status' => 'COMPLETED']);
        ParcelTrackingEvent::create([
            'order_id' => $order->id,
            'actor_id' => $this->authenticatedUser()->id,
            'event_type' => 'order_completed',
            'status' => 'COMPLETED',
            'location' => $order->municipality,
            'notes' => 'Buyer confirmed receipt.',
        ]);

        return back()->with('success', 'Order marked as completed! Thank you for confirming.');
    }
}
