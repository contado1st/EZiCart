<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BuyerOrderGroupingService;
use App\Services\DeliveryCodeService;
use App\Services\OrderTransitionService;
use App\Services\QrCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BuyerController extends Controller
{
    public function dashboard(Request $request, BuyerOrderGroupingService $grouping)
    {
        $request->validate(['order_group' => ['nullable', Rule::in(array_keys(BuyerOrderGroupingService::LABELS))],
            'status' => ['nullable', Rule::in([...array_column(OrderStatus::cases(), 'value'), 'RETURNED'])]]);
        $orderGroup = $request->query('order_group', 'all') ?: 'all';
        $expression = $grouping->expression();
        $base = Order::query()->where('buyer_id', $this->authenticatedUser()->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')));
        $counts = $grouping->counts($base);
        $orders = $base->when($orderGroup !== 'all', fn ($q) => $q->whereRaw($expression.' = ?', [$orderGroup]))
            ->with(['seller', 'items.product', 'pendingCancellation', 'dispute', 'reviews'])
            ->latest('created_at')->latest('id')->paginate(25)->withQueryString();
        $groupLabels = BuyerOrderGroupingService::LABELS;

        return view('buyer.dashboard', compact('orders', 'counts', 'orderGroup', 'groupLabels'));
    }

    public function showOrder(Order $order, DeliveryCodeService $deliveryCodes, QrCodeService $qrCodes)
    {
        abort_if($order->buyer_id !== $this->authenticatedUser()->id, 403);

        $order->load(['seller', 'items.product', 'reviews', 'dispute', 'trackingEvents.actor', 'deliveryAttempts', 'purchase.orders.seller']);

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

    public function cancel(Request $request, Order $order, OrderTransitionService $transitions)
    {
        abort_if($order->buyer_id !== $this->authenticatedUser()->id, 403);
        $data = $request->validate(['reason' => ['nullable', 'string', 'min:5', 'max:500']]);
        $transitions->requestCancellation($order, $this->authenticatedUser(), $data['reason'] ?? 'Buyer requested cancellation before shipment.');

        return back()->with('success', $order->fresh()->status === 'CANCELLED' ? 'Unpaid reservation cancelled. No wallet funds were charged.' : 'Cancellation pending seller approval. Fulfillment is paused; unanswered requests are approved after 24 hours.');
    }
}
