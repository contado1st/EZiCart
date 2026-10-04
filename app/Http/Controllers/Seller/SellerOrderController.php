<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Services\OrderTransitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function updateStatus(Request $request, Order $order, OrderTransitionService $transitions)
    {
        abort_if($order->seller_id !== $this->authenticatedUser()->id, 403);

        $validated = $request->validate(['status' => 'required|in:CONFIRMED,PREPARING,READY_FOR_PICKUP']);
        $actor = $this->authenticatedUser();

        $transitions->transition(
            $order,
            $actor,
            OrderStatus::from($validated['status']),
            strtolower($validated['status']),
            $actor->municipality,
            'Seller updated order fulfillment status.',
        );

        return back()->with('success', 'Order status updated to '.str_replace('_', ' ', $validated['status']).'.');
    }

    public function confirmReturn(Order $order, OrderTransitionService $transitions)
    {
        abort_if($order->seller_id !== $this->authenticatedUser()->id, 403);
        $actor = $this->authenticatedUser();
        $transitions->transition($order, $actor, OrderStatus::ReturnedToSeller, 'returned_to_seller', $actor->municipality, 'Seller confirmed receipt of the returned parcel.');

        return back()->with('success', 'Return receipt confirmed and inventory restored.');
    }

    public function schedulePickup(Request $request, Order $order)
    {
        $seller = $this->authenticatedUser();
        abort_if($order->seller_id !== $seller->id, 403);

        $validated = $request->validate([
            'pickup_scheduled_for' => ['required', 'date', 'after:now'],
            'pickup_window' => ['required', 'string', 'max:80'],
            'pickup_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($order, $seller, $validated) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->status === 'READY_FOR_PICKUP' && $lockedOrder->pickup_requested_at === null && $lockedOrder->pickup_courier_id === null, 422, 'This order is not eligible for pickup scheduling.');

            $lockedOrder->update([
                'pickup_requested_at' => now(),
                'pickup_scheduled_for' => $validated['pickup_scheduled_for'],
                'pickup_window' => $validated['pickup_window'],
                'pickup_notes' => $validated['pickup_notes'] ?? null,
            ]);
            ParcelTrackingEvent::create([
                'order_id' => $lockedOrder->id,
                'actor_id' => $seller->id,
                'event_type' => 'pickup_requested',
                'status' => $lockedOrder->status,
                'location' => implode(', ', array_filter([$seller->street_address, $seller->municipality, $seller->province])),
                'notes' => 'Seller requested pickup for '.$lockedOrder->pickup_scheduled_for->format('M j, Y g:i A').' ('.$lockedOrder->pickup_window.').',
            ]);

            return back()->with('success', 'Pickup request sent to Logistics.');
        });
    }

    public function confirmHandover(Order $order)
    {
        $seller = $this->authenticatedUser();
        abort_if($order->seller_id !== $seller->id, 403);

        return DB::transaction(function () use ($order, $seller) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->status === 'READY_FOR_PICKUP' && $lockedOrder->pickup_courier_id !== null && $lockedOrder->pickup_arrived_at !== null && $lockedOrder->seller_handover_at === null, 422, 'Seller handover is not ready to confirm.');
            $lockedOrder->update(['seller_handover_at' => now()]);
            ParcelTrackingEvent::create([
                'order_id' => $lockedOrder->id,
                'actor_id' => $seller->id,
                'event_type' => 'seller_handover_confirmed',
                'status' => $lockedOrder->status,
                'location' => $seller->municipality,
                'notes' => 'Seller confirmed releasing the parcel to the assigned rider.',
            ]);

            return back()->with('success', 'Handover recorded. The rider must confirm possession to complete pickup.');
        });
    }

    public function waybill(Order $order)
    {
        abort_if($order->seller_id !== $this->authenticatedUser()->id, 403);

        $order->load(['seller', 'buyer', 'items']);

        return view('seller.orders.waybill', compact('order'));
    }
}
