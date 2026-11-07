<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Services\DeliveryCodeService;
use App\Services\OrderTransitionService;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderApiController extends Controller
{
    /**
     * Get paginated orders list for authenticated buyer
     */
    public function index(Request $request): JsonResponse
    {
        $buyer = $request->user();
        $tab = $request->input('tab', 'all');
        $status = $request->input('status');

        $query = $buyer->buyerOrders()
            ->with(['seller:id,business_name', 'items.product:id,name,image_path'])
            ->latest('id');

        if ($status) {
            $query->where('status', $status);
        } elseif ($tab === 'to_ship') {
            $query->whereIn('status', ['PLACED', 'CONFIRMED', 'PREPARING']);
        } elseif ($tab === 'to_receive') {
            $query->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERED', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT']);
        } elseif ($tab === 'completed') {
            $query->where('status', 'COMPLETED');
        } elseif ($tab === 'cancelled') {
            $query->whereIn('status', ['CANCELLED', 'RETURNED', 'RETURNED_TO_SELLER']);
        }

        $orders = $query->paginate(15);

        $counts = [
            'all' => $buyer->buyerOrders()->count(),
            'to_ship' => $buyer->buyerOrders()->whereIn('status', ['PLACED', 'CONFIRMED', 'PREPARING'])->count(),
            'to_receive' => $buyer->buyerOrders()->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERED', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT'])->count(),
            'completed' => $buyer->buyerOrders()->where('status', 'COMPLETED')->count(),
            'cancelled' => $buyer->buyerOrders()->whereIn('status', ['CANCELLED', 'RETURNED', 'RETURNED_TO_SELLER'])->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'counts' => $counts,
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'total' => $orders->total(),
        ]);
    }

    /**
     * Get detailed order tracking view
     */
    public function show(int $id, Request $request, DeliveryCodeService $deliveryCodes, QrCodeService $qrCodes): JsonResponse
    {
        $buyer = $request->user();
        $order = Order::with([
            'seller:id,business_name,contact_no',
            'deliveryCourier:id,first_name,last_name,contact_no,plate_number',
            'pickupCourier:id,first_name,last_name,contact_no,plate_number',
            'items.product:id,name,image_path',
            'dispute',
            'trackingEvents' => fn ($q) => $q->latest('id'),
            'deliveryAttempts',
        ])->find($id);

        if (! $order || $order->buyer_id !== $buyer->id) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $deliveryCode = null;
        $deliveryQrString = null;
        if ($order->status === 'OUT_FOR_DELIVERY') {
            try {
                $deliveryCode = $deliveryCodes->reveal($order);
                if ($deliveryCode !== null) {
                    $deliveryQrString = 'EZD:'.$deliveryCode;
                }
            } catch (\Throwable $e) {
                // Ignore code reveal errors if not ready
            }
        }

        $courier = $order->deliveryCourier ?? $order->pickupCourier;

        $totalAmount = (float) ($order->total_amount ?? ($order->total_cents ? $order->total_cents / 100 : 0));
        $subtotal = (float) ($order->subtotal ?? ($order->subtotal_cents ? $order->subtotal_cents / 100 : $totalAmount));
        $shippingFee = (float) ($order->shipping_fee ?? ($order->shipping_cents ? $order->shipping_cents / 100 : 0));
        $discount = (float) ($order->discount_cents ? $order->discount_cents / 100 : 0);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'parcel_code' => $order->parcel_code,
                'status' => $order->status,
                'status_label' => str_replace('_', ' ', $order->status),
                'created_at' => $order->created_at->toIso8601String(),
                'total_amount' => $totalAmount,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'discount' => $discount,
                'payment_method' => $order->payment_method ?? 'COD',
                'delivery_code' => $deliveryCode,
                'delivery_qr_payload' => $deliveryQrString,
                'recipient_name' => $order->recipient_name,
                'recipient_contact' => $order->recipient_contact,
                'delivery_address' => trim("{$order->house_number} {$order->street_address}, {$order->barangay}, {$order->municipality}, {$order->province}"),
                'notes' => $order->notes,
                'seller' => [
                    'id' => $order->seller->id ?? null,
                    'name' => $order->seller->business_name ?? 'Merchant',
                    'contact' => $order->seller->contact_no ?? null,
                ],
                'courier' => $courier ? [
                    'name' => "{$courier->first_name} {$courier->last_name}",
                    'contact' => $courier->contact_no,
                    'plate_number' => $courier->plate_number,
                ] : null,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->product->name ?? 'Product',
                    'image_path' => $item->product->image_path ?? null,
                    'quantity' => (int) $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) ($item->price * $item->quantity),
                    'variation_info' => $item->variation_info,
                ]),
                'tracking_timeline' => $order->trackingEvents->map(fn ($e) => [
                    'status' => $e->status,
                    'description' => $e->description ?? $e->remarks ?? '',
                    'location' => $e->location,
                    'timestamp' => $e->created_at->toIso8601String(),
                ]),
                'dispute' => $order->dispute ? [
                    'id' => $order->dispute->id,
                    'reason' => $order->dispute->reason,
                    'status' => $order->dispute->status,
                    'resolution' => $order->dispute->resolution,
                    'created_at' => $order->dispute->created_at->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    /**
     * Buyer confirms order receipt
     */
    public function confirmReceived(int $id, Request $request, OrderTransitionService $transitions): JsonResponse
    {
        $buyer = $request->user();
        $order = Order::find($id);

        if (! $order || $order->buyer_id !== $buyer->id) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        try {
            $transitions->transition(
                $order,
                $buyer,
                OrderStatus::Completed,
                'order_completed',
                $order->municipality,
                'Buyer confirmed delivery receipt.'
            );
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order marked as completed! Thank you for confirming.',
        ]);
    }

    /**
     * Buyer requests cancellation before shipment
     */
    public function cancel(int $id, Request $request, OrderTransitionService $transitions): JsonResponse
    {
        $buyer = $request->user();
        $order = Order::find($id);

        if (! $order || $order->buyer_id !== $buyer->id) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $transitions->requestCancellation(
                $order,
                $buyer,
                $validated['reason'] ?? 'Buyer requested order cancellation before shipment.'
            );
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cancellation requested. Fulfillment is paused awaiting seller confirmation.',
        ]);
    }

    /**
     * Refresh delivery OTP code when out for delivery
     */
    public function refreshDeliveryCode(int $id, Request $request, DeliveryCodeService $deliveryCodes): JsonResponse
    {
        $buyer = $request->user();
        $order = Order::find($id);

        if (! $order || $order->buyer_id !== $buyer->id) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($order->status !== 'OUT_FOR_DELIVERY') {
            return response()->json(['success' => false, 'message' => 'Delivery code can only be refreshed while parcel is out for delivery.'], 422);
        }

        $code = null;
        DB::transaction(function () use ($order, $deliveryCodes, &$code): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $issued = $deliveryCodes->issue();
            $code = $issued['code'];
            unset($issued['code']);
            $lockedOrder->forceFill($issued)->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'New delivery code generated.',
            'delivery_code' => $code,
            'delivery_qr_payload' => 'EZD:'.$code,
        ]);
    }

    /**
     * Submit product review for delivered item
     */
    public function review(int $id, Request $request): JsonResponse
    {
        $buyer = $request->user();
        $order = Order::find($id);

        if (! $order || $order->buyer_id !== $buyer->id) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = Review::updateOrCreate(
            [
                'order_id' => $order->id,
                'user_id' => $buyer->id,
                'product_id' => $validated['product_id'],
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? '',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Thank you for submitting your review!',
            'review' => $review,
        ]);
    }

    /**
     * Raise a dispute or return request on delivered/completed order
     */
    public function createDispute(int $id, Request $request): JsonResponse
    {
        $buyer = $request->user();
        $order = Order::find($id);

        if (! $order || $order->buyer_id !== $buyer->id) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (! in_array($order->status, ['DELIVERED', 'COMPLETED'])) {
            return response()->json([
                'success' => false,
                'message' => 'Disputes can only be raised for delivered or completed orders.',
            ], 422);
        }

        if ($order->dispute()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'A dispute case is already open for this order.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:Damaged Item,Wrong Item Delivered,Missing Items,Non-Delivery,Defective Goods,Other'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $evidencePath = null;
        if ($request->hasFile('evidence_file')) {
            $evidencePath = $request->file('evidence_file')->store('disputes/evidence', 'public');
        }

        $dispute = \App\Models\Dispute::create([
            'order_id' => $order->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $order->seller_id,
            'reason' => $validated['reason'],
            'description' => $validated['description'],
            'evidence_path' => $evidencePath,
            'status' => 'PENDING',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dispute ticket submitted. Customer support and seller will review your request.',
            'data' => $dispute,
        ], 201);
    }

    /**
     * Get dispute details for an order
     */
    public function getDispute(int $id, Request $request): JsonResponse
    {
        $buyer = $request->user();
        $order = Order::find($id);

        if (! $order || $order->buyer_id !== $buyer->id) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $dispute = $order->dispute;
        if (! $dispute) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $dispute->id,
                'order_id' => $dispute->order_id,
                'reason' => $dispute->reason,
                'description' => $dispute->description,
                'status' => $dispute->status,
                'admin_notes' => $dispute->admin_notes,
                'created_at' => $dispute->created_at->toIso8601String(),
                'resolved_at' => $dispute->resolved_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * List all disputes for current buyer
     */
    public function disputesList(Request $request): JsonResponse
    {
        $buyer = $request->user();
        $disputes = \App\Models\Dispute::with(['order:id,parcel_code,status,total_cents,seller_id', 'order.seller:id,business_name'])
            ->where('buyer_id', $buyer->id)
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $disputes,
        ]);
    }
}

