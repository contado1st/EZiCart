<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Order;
use App\Services\BuyerOrderGroupingService;
use App\Services\DeliveryCodeService;
use App\Services\OrderProgressService;
use App\Services\OrderTransitionService;
use App\Services\QrCodeService;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OrderApiController extends Controller
{
    /**
     * Get paginated orders list for authenticated buyer
     */
    public function index(Request $request, BuyerOrderGroupingService $grouping): JsonResponse
    {
        $buyer = $request->user();
        $tab = $request->input('tab', 'all') ?: 'all';
        $status = $request->input('status');

        $base = Order::query()->where('buyer_id', $buyer->id)
            ->when($status, fn ($q) => $q->where('status', $status));

        $counts = $grouping->counts($base);
        $expression = $grouping->expression();

        $query = (clone $base)->when($tab !== 'all', fn ($q) => $q->whereRaw($expression.' = ?', [$tab]))
            ->with([
                'seller:id,business_name',
                'items.product:id,name,image_path,price',
                'dispute.sellerResponse',
                'pendingCancellation',
                'activeIncident',
                'returnPickupAssignment.rider:id,first_name,last_name',
            ])
            ->latest('created_at')
            ->latest('id');

        $orders = $query->paginate(15);

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
    public function show(int $id, Request $request, DeliveryCodeService $deliveryCodes, QrCodeService $qrCodes, OrderProgressService $orderProgressService): JsonResponse
    {
        $buyer = $request->user();
        $order = Order::with([
            'seller:id,business_name,contact_no',
            'deliveryCourier:id,first_name,last_name,contact_no,plate_number',
            'pickupCourier:id,first_name,last_name,contact_no,plate_number',
            'items.product:id,name,image_path,price',
            'dispute',
            'activeIncident',
            'returnPickupAssignment.rider:id,first_name,last_name,contact_no',
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
            } catch (Throwable $e) {
                // Ignore code reveal errors if not ready
            }
        }

        $courier = $order->deliveryCourier ?? $order->pickupCourier;
        $progress = $orderProgressService->forViewer($order, $buyer);

        $totalAmount = (float) $order->total_amount;
        $subtotal = (float) $order->subtotal;
        $shippingFee = (float) $order->shipping_fee;
        $discount = (float) $order->discount_amount;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'parcel_code' => $order->parcel_code,
                'status' => $order->status, 'incident_status' => $order->activeIncident?->kind, 'fulfillment_on_hold' => $order->activeIncident !== null,
                'status_label' => str_replace('_', ' ', $order->status),
                'created_at' => $order->created_at->toIso8601String(),
                'total_amount' => $totalAmount,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'discount' => $discount,
                'payment_method' => $order->payment_method ?? 'COD',
                'is_awaiting_wallet_payment' => $order->isAwaitingWalletPayment(),
                'wallet_payment_due_at' => $order->wallet_payment_due_at?->toIso8601String(),
                'wallet_paid_cents' => (int) $order->wallet_paid_cents,
                'wallet_transaction_id' => $order->wallet_transaction_id,
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
                    'name' => $item->product_name ?: ($item->product->name ?? 'Product'),
                    'image_path' => $item->product->image_path ?? null,
                    'quantity' => (int) $item->quantity,
                    'price' => (float) $item->unit_price,
                    'total' => (float) $item->item_total,
                    'variation_info' => $item->variation_info,
                ]),
                'tracking_timeline' => $order->trackingEvents->map(fn ($e) => [
                    'status' => $e->status,
                    'description' => $e->notes ?? '',
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
                'order_progress' => $progress,
                'active_incident' => $order->activeIncident ? [
                    'id' => $order->activeIncident->id,
                    'kind' => $order->activeIncident->kind,
                    'status' => $order->activeIncident->status,
                    'claim_status' => $order->activeIncident->claim_status,
                ] : null,
                'return_pickup' => $order->returnPickupAssignment ? [
                    'id' => $order->returnPickupAssignment->id,
                    'is_accepted' => $order->returnPickupAssignment->accepted_at !== null,
                    'rider' => $order->returnPickupAssignment->rider ? [
                        'name' => trim("{$order->returnPickupAssignment->rider->first_name} {$order->returnPickupAssignment->rider->last_name}"),
                        'contact' => $order->returnPickupAssignment->rider->contact_no,
                    ] : null,
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $order->fresh()->status === 'CANCELLED' ? 'Unpaid reservation cancelled. No wallet funds were charged.' : 'Cancellation requested. Fulfillment is paused awaiting seller confirmation.',
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

        // The API shares review identity and eligibility with the web; repeat create
        // cannot silently overwrite a buyer's review or bypass revision history.
        $review = app(ReviewService::class)->save($buyer, $order, $validated, []);

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
        try {
            $dispute = DB::transaction(function () use ($order, $buyer, $validated, $request, &$evidencePath) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->buyer_id === $buyer->id, 404);
                abort_unless(in_array($locked->status, ['DELIVERED', 'COMPLETED'], true), 422, 'Disputes require delivered or completed orders.');
                abort_if($locked->dispute()->exists(), 422, 'A dispute case is already open for this order.');
                if ($request->hasFile('evidence_file')) {
                    $evidencePath = $request->file('evidence_file')->store('disputes/evidence', 'private');
                }

                return Dispute::create(['order_id' => $locked->id, 'buyer_id' => $buyer->id, 'seller_id' => $locked->seller_id,
                    'reason' => $validated['reason'], 'description' => $validated['description'], 'evidence_path' => $evidencePath, 'status' => 'PENDING']);
            });
        } catch (Throwable $error) {
            if ($evidencePath !== null) {
                Storage::disk('private')->delete($evidencePath);
            }
            throw $error;
        }

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
        $disputes = Dispute::with(['order:id,parcel_code,status,total_cents,seller_id', 'order.seller:id,business_name'])
            ->where('buyer_id', $buyer->id)
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $disputes,
        ]);
    }
}
