<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DeliveryCodeService;
use App\Services\OrderTransitionService;
use App\Services\QrCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function showOrder(Order $order, DeliveryCodeService $deliveryCodes, QrCodeService $qrCodes)
    {
        abort_if($order->buyer_id !== $this->authenticatedUser()->id, 403);

        $order->load(['seller', 'items.product', 'dispute', 'trackingEvents.actor', 'deliveryAttempts']);

        $deliveryCode = $deliveryCodes->reveal($order);
        $deliveryCodeQr = $deliveryCode === null ? null : $qrCodes->svg('EZD:'.$deliveryCode, 180);

        return view('buyer.orders.show', compact('order', 'deliveryCode', 'deliveryCodeQr'));
    }

    public function refreshDeliveryCode(Order $order, DeliveryCodeService $deliveryCodes): RedirectResponse
    {
        $buyer = $this->authenticatedUser();
        abort_unless($order->buyer_id === $buyer->id, 403);

        DB::transaction(function () use ($order, $buyer, $deliveryCodes): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->buyer_id === $buyer->id, 403);
            abort_unless($lockedOrder->status === 'OUT_FOR_DELIVERY' && $lockedOrder->delivery_code_used_at === null, 422, 'A delivery code cannot be refreshed for this order.');

            $issuedCode = $deliveryCodes->issue();
            unset($issuedCode['code']);
            $lockedOrder->forceFill($issuedCode)->save();
        });

        return back()->with('success', 'A new delivery code is ready for your courier.');
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
