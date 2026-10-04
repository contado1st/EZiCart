<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use Illuminate\Http\Request;

class SellerOrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $query = $this->authenticatedUser()->sellerOrders()
            ->with(['buyer', 'items'])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(10)->withQueryString();

        $counts = [
            'all' => $this->authenticatedUser()->sellerOrders()->count(),
            'placed' => $this->authenticatedUser()->sellerOrders()->where('status', 'PLACED')->count(),
            'preparing' => $this->authenticatedUser()->sellerOrders()->whereIn('status', ['CONFIRMED', 'PREPARING'])->count(),
            'ready' => $this->authenticatedUser()->sellerOrders()->where('status', 'READY_FOR_PICKUP')->count(),
            'completed' => $this->authenticatedUser()->sellerOrders()->where('status', 'COMPLETED')->count(),
        ];

        return view('seller.orders.index', compact('orders', 'counts'));
    }

    public function show(Order $order)
    {
        abort_if($order->seller_id !== $this->authenticatedUser()->id, 403);

        $order->load(['buyer', 'items.product']);

        return view('seller.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        abort_if($order->seller_id !== $this->authenticatedUser()->id, 403);

        $validated = $request->validate([
            'status' => 'required|in:CONFIRMED,PREPARING,READY_FOR_PICKUP',
        ]);

        $allowedTransitions = [
            'PLACED' => ['PREPARING'],
            'CONFIRMED' => ['READY_FOR_PICKUP'],
            'PREPARING' => ['READY_FOR_PICKUP'],
        ];
        abort_unless(in_array($validated['status'], $allowedTransitions[$order->status] ?? [], true), 422, 'This order cannot move to that status.');

        $order->update(['status' => $validated['status']]);
        ParcelTrackingEvent::create([
            'order_id' => $order->id,
            'actor_id' => $this->authenticatedUser()->id,
            'event_type' => strtolower($validated['status']),
            'status' => $order->status,
            'location' => $this->authenticatedUser()->municipality,
            'notes' => 'Seller updated order fulfillment status.',
        ]);

        return back()->with('success', 'Order status updated to '.str_replace('_', ' ', $validated['status']).'.');
    }

    public function waybill(Order $order)
    {
        abort_if($order->seller_id !== $this->authenticatedUser()->id, 403);

        $order->load(['seller', 'buyer', 'items']);

        return view('seller.orders.waybill', compact('order'));
    }
}
