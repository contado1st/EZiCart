<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderTransitionService;
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
            'to_receive' => $this->authenticatedUser()->buyerOrders()->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERED', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT'])->count(),
            'completed' => $this->authenticatedUser()->buyerOrders()->whereIn('status', ['COMPLETED', 'CANCELLED', 'RETURNED', 'RETURNED_TO_SELLER'])->count(),
        ];

        return view('buyer.dashboard', compact('orders', 'counts'));
    }

    public function showOrder(Order $order)
    {
        abort_if($order->buyer_id !== $this->authenticatedUser()->id, 403);

        $order->load(['seller', 'items.product', 'dispute', 'trackingEvents.actor', 'deliveryAttempts']);

        return view('buyer.orders.show', compact('order'));
    }

    public function confirmReceived(Order $order, OrderTransitionService $transitions)
    {
        abort_if($order->buyer_id !== $this->authenticatedUser()->id, 403);
        $transitions->transition($order, $this->authenticatedUser(), OrderStatus::Completed, 'order_completed', $order->municipality, 'Buyer confirmed receipt.');

        return back()->with('success', 'Order marked as completed! Thank you for confirming.');
    }

    public function cancel(Order $order, OrderTransitionService $transitions)
    {
        abort_if($order->buyer_id !== $this->authenticatedUser()->id, 403);
        $transitions->transition($order, $this->authenticatedUser(), OrderStatus::Cancelled, 'cancelled', $order->municipality, 'Buyer cancelled before shipment.');

        return back()->with('success', 'Order cancelled and reserved inventory restored.');
    }
}
