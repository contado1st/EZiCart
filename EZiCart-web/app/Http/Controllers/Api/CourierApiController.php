<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAttempt;
use App\Models\Order;
use App\Models\OrderFinance;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use App\Services\AdministrativeAuditService;
use App\Services\CheckoutPricingService;
use App\Services\DeliveryCodeService;
use App\Services\DirectMessagingService;
use App\Services\FinancialLedgerService;
use App\Services\LogisticsHubNotificationService;
use App\Services\OrderFinancialService;
use App\Services\OrderTransitionService;
use App\Services\OrderWalletService;
use App\Services\ParcelLoadService;
use App\Services\RiderBadgeService;
use App\Services\TransactionAwareNotificationSender;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CourierApiController extends Controller
{
    /**
     * Courier Dashboard Data
     */
    public function dashboard(Request $request, RiderBadgeService $badges): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $badgeCode = $badges->ensure($courier);

        $todayStart = today()->startOfDay();
        $tomorrowStart = today()->addDay()->startOfDay();

        $availablePickups = Order::where('status', 'READY_FOR_PICKUP')
            ->where('pickup_courier_id', $courier->id)
            ->whereNull('pickup_claimed_at')
            ->with(['seller', 'items'])
            ->latest('id')
            ->get()
            ->map(fn (Order $o) => $this->formatOrder($o));

        $claimedPickups = Order::where('pickup_courier_id', $courier->id)
            ->where('status', 'READY_FOR_PICKUP')
            ->whereNotNull('pickup_claimed_at')
            ->with(['seller', 'items'])
            ->latest('pickup_claimed_at')
            ->get()
            ->map(fn (Order $o) => $this->formatOrder($o));

        $myActivePickups = Order::where('pickup_courier_id', $courier->id)
            ->where('status', 'PICKED_UP')
            ->with(['seller', 'items'])
            ->latest('picked_up_at')
            ->get()
            ->map(fn (Order $o) => $this->formatOrder($o));

        $myDeliveryAssignments = Order::where('delivery_courier_id', $courier->id)
            ->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])
            ->with(['buyer', 'items', 'deliveryAssignments'])
            ->orderBy('delivery_area')
            ->orderBy('assigned_at')
            ->get()
            ->map(fn (Order $o) => $this->formatOrder($o));

        $myReturns = Order::query()
            ->where('delivery_courier_id', $courier->id)
            ->where('status', OrderStatus::ReturnInTransit->value)
            ->whereNull('return_handed_to_seller_at')
            ->with(['seller', 'items'])
            ->latest('failed_at')
            ->get()
            ->map(fn (Order $o) => $this->formatOrder($o));

        $myFailedDeliveries = Order::where('delivery_courier_id', $courier->id)
            ->where('status', 'DELIVERY_FAILED')
            ->with(['deliveryAttempts', 'seller', 'buyer', 'items'])
            ->latest('failed_at')
            ->get()
            ->map(fn (Order $o) => $this->formatOrder($o));

        $stats = [
            'claimed_pickups' => $claimedPickups->count(),
            'in_transit_hub' => $myActivePickups->count(),
            'assigned_delivery' => $myDeliveryAssignments->count(),
            'returning_to_seller' => $myReturns->count(),
            'completed_today' => Order::where('delivery_courier_id', $courier->id)
                ->whereIn('status', ['DELIVERED', 'COMPLETED'])
                ->whereBetween('delivered_at', [$todayStart, $tomorrowStart])
                ->count(),
            'failed_today' => DeliveryAttempt::query()
                ->where('rider_id', $courier->id)
                ->where('outcome', 'failed')
                ->whereBetween('attempted_at', [$todayStart, $tomorrowStart])
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'badge_code' => $badgeCode,
            'stats' => $stats,
            'available_pickups' => $availablePickups,
            'claimed_pickups' => $claimedPickups,
            'in_transit_hub' => $myActivePickups,
            'deliveries' => $myDeliveryAssignments,
            'returns' => $myReturns,
            'failed_deliveries' => $myFailedDeliveries,
        ]);
    }

    /**
     * Rotate Rider Badge
     */
    public function rotateBadge(Request $request, RiderBadgeService $badges): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $badges->rotate($courier);
        $newCode = $badges->ensure($courier);

        return response()->json([
            'success' => true,
            'message' => 'Your rider badge was rotated successfully. Previous codes are no longer valid.',
            'badge_code' => $newCode,
        ]);
    }

    /**
     * Show Order Details
     */
    public function showOrder(Request $request, int $id): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $order = Order::findOrFail($id);

        $hasPickupAccess = $order->pickup_courier_id === $courier->id;
        $hasDeliveryAccess = $order->delivery_courier_id === $courier->id;
        $hasHistoryAccess = $order->deliveryAssignments()->where('rider_id', $courier->id)->exists();

        if (! $hasPickupAccess && ! $hasDeliveryAccess && ! $hasHistoryAccess) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this order.',
            ], 403);
        }

        $order->load(['seller', 'buyer', 'items', 'trackingEvents.actor', 'deliveryAttempts', 'deliveryAssignments']);

        return response()->json([
            'success' => true,
            'order' => $this->formatOrder($order, true),
        ]);
    }

    /**
     * Claim / Accept Assigned Pickup
     */
    public function claimPickup(Request $request, int $id, TransactionAwareNotificationSender $notifications): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $order = Order::findOrFail($id);

        return DB::transaction(function () use ($order, $courier, $notifications): JsonResponse {
            app(ParcelLoadService::class)->lockVehicle($courier->id);
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($courier->status !== 'approved') {
                return response()->json(['success' => false, 'message' => 'Your rider account is not approved for pickups.'], 403);
            }
            if ($lockedOrder->pickup_courier_id !== $courier->id) {
                return response()->json(['success' => false, 'message' => 'This pickup is not assigned to you.'], 403);
            }
            if (! app(OrderWalletService::class)->paymentReady($lockedOrder)
                || $lockedOrder->status !== 'READY_FOR_PICKUP'
                || $lockedOrder->pickup_claimed_at !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'This pickup has already been accepted, is on payment verification hold, or is no longer available.',
                ], 422);
            }

            app(ParcelLoadService::class)->assertFits($lockedOrder, $courier);
            $lockedOrder->forceFill(['pickup_claimed_at' => now()])->save();

            $this->recordEvent($lockedOrder, 'pickup_accepted', $courier, $lockedOrder->seller?->municipality, 'Rider accepted the Logistics pickup assignment.');
            $notifications->send([$lockedOrder->seller, $lockedOrder->buyer], new OrderWorkflowNotification($lockedOrder, 'pickup_accepted', 'The assigned rider accepted the pickup request.'));

            return response()->json([
                'success' => true,
                'message' => "Pickup {$lockedOrder->order_number} added to your active route.",
                'order' => $this->formatOrder($lockedOrder->fresh(['seller', 'items'])),
            ]);
        });
    }

    /**
     * Decline Assigned Pickup
     */
    public function declinePickup(Request $request, int $id, TransactionAwareNotificationSender $notifications, LogisticsHubNotificationService $hubNotifications): JsonResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        /** @var User $courier */
        $courier = $request->user();
        $order = Order::findOrFail($id);

        return DB::transaction(function () use ($order, $courier, $validated, $notifications, $hubNotifications): JsonResponse {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($courier->status !== 'approved' || $lockedOrder->status !== 'READY_FOR_PICKUP') {
                return response()->json(['success' => false, 'message' => 'This pickup cannot be declined.'], 422);
            }
            if ($lockedOrder->pickup_courier_id !== $courier->id || $lockedOrder->pickup_claimed_at !== null) {
                return response()->json(['success' => false, 'message' => 'You cannot decline this pickup assignment.'], 403);
            }

            $lockedOrder->forceFill(['pickup_courier_id' => null])->save();
            $this->recordEvent(
                $lockedOrder,
                'pickup_declined',
                $courier,
                $lockedOrder->seller?->municipality,
                filled($validated['reason'] ?? null) ? $validated['reason'] : 'Rider declined the pickup assignment.',
            );
            $notifications->send([$lockedOrder->seller, $lockedOrder->buyer], new OrderWorkflowNotification($lockedOrder, 'pickup_declined', 'The assigned rider declined the pickup. Logistics will reassign it.'));
            $hubNotifications->sendForOrder($lockedOrder, $notifications, new OrderWorkflowNotification($lockedOrder, 'pickup_declined', 'A rider declined a pickup and it is back in the Logistics queue.'));

            return response()->json([
                'success' => true,
                'message' => "Pickup {$lockedOrder->order_number} returned to the Logistics queue.",
            ]);
        });
    }

    /**
     * Record Arrival at Seller for Pickup
     */
    public function arriveAtPickup(Request $request, int $id, TransactionAwareNotificationSender $notifications): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $order = Order::findOrFail($id);

        return DB::transaction(function () use ($order, $courier, $notifications): JsonResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->pickup_courier_id !== $courier->id) {
                return response()->json(['success' => false, 'message' => 'This pickup is not assigned to you.'], 403);
            }
            if ($courier->status !== 'approved' || $lockedOrder->status !== 'READY_FOR_PICKUP' || $lockedOrder->pickup_claimed_at === null) {
                return response()->json(['success' => false, 'message' => 'Cannot record arrival for this pickup.'], 422);
            }

            if ($lockedOrder->pickup_arrived_at === null) {
                $lockedOrder->forceFill(['pickup_arrived_at' => now()])->save();
                $this->recordEvent($lockedOrder, 'pickup_arrived', $courier, $lockedOrder->seller?->municipality, 'Rider arrived and requested seller handover confirmation.');
                $notifications->send([$lockedOrder->seller, $lockedOrder->buyer], new OrderWorkflowNotification($lockedOrder, 'pickup_arrived', 'The rider arrived at the seller. The seller must confirm parcel handover.'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Arrival recorded. Await seller handover confirmation.',
                'order' => $this->formatOrder($lockedOrder->fresh(['seller', 'items'])),
            ]);
        });
    }

    /**
     * Confirm Physical Pickup of Parcel
     */
    public function confirmPickup(Request $request, int $id, OrderTransitionService $transitions, TransactionAwareNotificationSender $notifications): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'parcel_reference' => ['required', 'string', 'max:100'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $scannedReference = $validated['parcel_reference'];
        $scannedCode = str_starts_with($scannedReference, 'EZP:') ? substr($scannedReference, 4) : $scannedReference;

        if (! hash_equals((string) $order->parcel_code, $scannedCode)) {
            DB::table('scan_events')->insert([
                'order_id' => $order->id,
                'actor_id' => $courier->id,
                'station' => 'seller_pickup',
                'result' => 'rejected',
                'failure_reason' => 'Scanned parcel code did not match the assigned pickup parcel.',
                'method' => $validated['method'] ?? 'manual',
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The scanned parcel code does not match this pickup assignment.',
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request, $order, $courier, $transitions, $validated, $scannedReference, $scannedCode): JsonResponse {
                app(ParcelLoadService::class)->lockVehicle($courier->id);
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($lockedOrder->pickup_courier_id !== $courier->id) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized pickup action.'], 403);
                }
                if ($courier->status !== 'approved'
                    || ! app(OrderWalletService::class)->paymentReady($lockedOrder)
                    || $lockedOrder->status !== 'READY_FOR_PICKUP'
                    || $lockedOrder->pickup_claimed_at === null) {
                    return response()->json(['success' => false, 'message' => 'This pickup cannot be confirmed.'], 422);
                }

                if ($lockedOrder->seller_handover_at === null) {
                    return response()->json(['success' => false, 'message' => 'The seller must confirm parcel handover before pickup can be completed.'], 422);
                }

                if (! hash_equals((string) $lockedOrder->parcel_code, $scannedCode)) {
                    return response()->json(['success' => false, 'message' => 'The assigned parcel changed. Scan the parcel label again.'], 422);
                }

                $transitions->transition(
                    $lockedOrder,
                    $courier,
                    OrderStatus::PickedUp,
                    'picked_up',
                    $lockedOrder->seller?->municipality,
                    'Parcel collected from seller; traveling to sorting center.',
                    ['picked_up_at' => now()]
                );

                DB::table('scan_events')->insert([
                    'order_id' => $lockedOrder->id,
                    'actor_id' => $courier->id,
                    'station' => 'seller_pickup',
                    'result' => 'accepted',
                    'method' => $validated['method'] ?? 'manual',
                    'ip' => $request->ip(),
                    'created_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Parcel {$lockedOrder->order_number} collected. Bring it to the sorting center for handoff.",
                    'order' => $this->formatOrder($lockedOrder->fresh(['seller', 'items'])),
                ]);
            });
        } catch (Throwable $e) {
            DB::table('scan_events')->insert([
                'order_id' => $order->id,
                'actor_id' => $courier->id,
                'station' => 'seller_pickup',
                'result' => 'rejected',
                'failure_reason' => $e->getMessage(),
                'method' => $validated['method'] ?? 'manual',
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Accept Delivery Assignment (Rider deadline confirmation)
     */
    public function acceptDeliveryAssignment(Request $request, int $id, AdministrativeAuditService $audits): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $order = Order::findOrFail($id);

        return DB::transaction(function () use ($request, $order, $courier, $audits): JsonResponse {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->delivery_courier_id !== $courier->id) {
                return response()->json(['success' => false, 'message' => 'This delivery is not assigned to you.'], 403);
            }
            if ($locked->status !== 'ASSIGNED_TO_RIDER' && $locked->status !== 'OUT_FOR_DELIVERY') {
                return response()->json(['success' => false, 'message' => 'Invalid order state for acceptance.'], 422);
            }

            $assignment = $locked->deliveryAssignments()
                ->where('status', 'active')
                ->where('rider_id', $courier->id)
                ->lockForUpdate()
                ->first();

            if ($assignment && $assignment->accepted_at === null) {
                $assignment->update(['accepted_at' => now()]);
                $audits->record($courier, $locked, 'rider_assignment_accepted', [], ['accepted_at' => now()->toIso8601String()], 'Rider accepted delivery assignment.');
            }

            return response()->json([
                'success' => true,
                'message' => 'Delivery assignment accepted.',
                'order' => $this->formatOrder($locked->fresh(['buyer', 'items', 'deliveryAssignments'])),
            ]);
        });
    }

    /**
     * Start Delivery (Out For Delivery)
     */
    public function startDelivery(Request $request, int $id, OrderTransitionService $transitions, DeliveryCodeService $deliveryCodes): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'parcel_reference' => ['nullable', 'string', 'max:100'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $scannedReference = $validated['parcel_reference'] ?? null;

        if (filled($scannedReference)) {
            $scannedCode = str_starts_with($scannedReference, 'EZP:') ? substr($scannedReference, 4) : $scannedReference;
            if (! hash_equals((string) $order->parcel_code, $scannedCode) && ! hash_equals($order->order_number, $scannedCode)) {
                DB::table('scan_events')->insert([
                    'order_id' => $order->id,
                    'actor_id' => $courier->id,
                    'station' => 'delivery_start',
                    'result' => 'rejected',
                    'failure_reason' => 'Scanned parcel code did not match the assigned parcel.',
                    'method' => $validated['method'] ?? 'manual',
                    'ip' => $request->ip(),
                    'created_at' => now(),
                ]);

                return response()->json(['success' => false, 'message' => 'The scanned parcel does not match this delivery assignment.'], 422);
            }
        }

        try {
            return DB::transaction(function () use ($request, $order, $courier, $transitions, $deliveryCodes, $validated, $scannedReference): JsonResponse {
                app(ParcelLoadService::class)->lockVehicle($courier->id);
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($lockedOrder->delivery_courier_id !== $courier->id) {
                    return response()->json(['success' => false, 'message' => 'This order is not assigned to you.'], 403);
                }
                if ($courier->status !== 'approved' || $lockedOrder->status !== 'ASSIGNED_TO_RIDER') {
                    return response()->json(['success' => false, 'message' => 'This parcel cannot be started for delivery.'], 422);
                }
                if (! app(OrderWalletService::class)->paymentReady($lockedOrder)) {
                    return response()->json(['success' => false, 'message' => 'Delivery is on hold until payment verification is configured.'], 422);
                }
                if ($lockedOrder->hub_released_at === null) {
                    return response()->json(['success' => false, 'message' => 'Logistics must confirm the hub handoff before delivery can start.'], 422);
                }

                $maxAttempts = max(1, (int) config('logistics.maximum_delivery_attempts', 3));
                $failedCount = DeliveryAttempt::query()->where('order_id', $lockedOrder->id)->where('outcome', 'failed')->count();
                if ($failedCount >= $maxAttempts) {
                    return response()->json(['success' => false, 'message' => 'The maximum delivery attempts have been reached. Logistics must return the parcel to the seller.'], 422);
                }

                $deliveryCode = $deliveryCodes->issue();
                unset($deliveryCode['code']);

                $transitions->transition(
                    $lockedOrder,
                    $courier,
                    OrderStatus::OutForDelivery,
                    'out_for_delivery',
                    $lockedOrder->delivery_area,
                    'Rider collected parcel from the hub and is out for delivery.',
                    [
                        'out_for_delivery_at' => now(),
                        ...$deliveryCode,
                    ]
                );

                if (filled($scannedReference)) {
                    DB::table('scan_events')->insert([
                        'order_id' => $lockedOrder->id,
                        'actor_id' => $courier->id,
                        'station' => 'delivery_start',
                        'result' => 'accepted',
                        'method' => $validated['method'] ?? 'manual',
                        'ip' => $request->ip(),
                        'created_at' => now(),
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => "Order {$lockedOrder->order_number} is now out for delivery.",
                    'order' => $this->formatOrder($lockedOrder->fresh(['buyer', 'items', 'deliveryAttempts'])),
                ]);
            });
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Decline Delivery Assignment
     */
    public function declineDeliveryAssignment(Request $request, int $id, OrderTransitionService $transitions): JsonResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        /** @var User $courier */
        $courier = $request->user();
        $order = Order::findOrFail($id);

        try {
            $transitions->declineDeliveryAssignment($order, $courier, $validated['reason'] ?? null);

            return response()->json([
                'success' => true,
                'message' => "Delivery assignment for {$order->order_number} declined. Returned to dispatch queue.",
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Complete Delivery
     */
    public function completeDelivery(Request $request, int $id, OrderTransitionService $transitions, DeliveryCodeService $deliveryCodes): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $order = Order::findOrFail($id);

        if ($order->delivery_courier_id !== $courier->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized delivery action.'], 403);
        }

        $validated = $request->validate([
            'recipient_confirmation' => ['required', 'string', 'max:120'],
            'delivery_code' => ['required', 'string', 'max:32'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'cod_collected_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $deliveryCode = str_starts_with($validated['delivery_code'], 'EZD:')
            ? substr($validated['delivery_code'], 4)
            : $validated['delivery_code'];

        $codeCheck = DB::transaction(function () use ($order, $courier, $deliveryCode, $deliveryCodes): array {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->delivery_courier_id !== $courier->id) {
                return ['valid' => false, 'message' => 'Unauthorized delivery.'];
            }
            if ($courier->status !== 'approved' || $lockedOrder->status !== 'OUT_FOR_DELIVERY') {
                return ['valid' => false, 'message' => 'This parcel is not out for delivery.'];
            }
            if (! app(OrderWalletService::class)->paymentReady($lockedOrder)) {
                return ['valid' => false, 'message' => 'Payment verification hold.'];
            }

            if ($deliveryCodes->matches($lockedOrder, $deliveryCode)) {
                return ['valid' => true, 'message' => null];
            }

            $attemptLimit = max(1, (int) config('logistics.delivery_code_max_attempts', 5));
            if (filled($lockedOrder->delivery_code_hash)
                && $lockedOrder->delivery_code_used_at === null
                && $lockedOrder->delivery_code_expires_at?->isFuture() === true
                && $lockedOrder->delivery_code_attempts < $attemptLimit
                && ! Hash::check($deliveryCode, $lockedOrder->delivery_code_hash)) {
                $lockedOrder->increment('delivery_code_attempts');

                return ['valid' => false, 'message' => 'The delivery code is incorrect.'];
            }

            return ['valid' => false, 'message' => 'The delivery code is expired, already used, or locked. Ask the buyer to refresh it.'];
        });

        if (! $codeCheck['valid']) {
            return response()->json(['success' => false, 'message' => $codeCheck['message']], 422);
        }

        $proofPath = null;
        try {
            return DB::transaction(function () use ($request, $order, $courier, $validated, $transitions, $deliveryCodes, $deliveryCode, &$proofPath): JsonResponse {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($lockedOrder->payment_method === 'COD') {
                    if (! isset($validated['cod_collected_amount']) || $this->amountInCentavos($validated['cod_collected_amount']) !== $this->amountInCentavos((string) $lockedOrder->total_amount)) {
                        return response()->json(['success' => false, 'message' => 'Enter the exact full order amount collected for cash on delivery.'], 422);
                    }
                }

                $notes = trim('Recipient: '.$validated['recipient_confirmation'].'. '.($validated['delivery_notes'] ?? ''));
                $proofPath = $request->file('proof_file')->store('delivery-proofs', 'private');

                if (! is_string($proofPath)) {
                    throw new RuntimeException('Delivery proof could not be stored.');
                }

                $this->recordDeliveryAttempt($lockedOrder, $courier, 'delivered', [
                    'notes' => $notes,
                    'proof_path' => $proofPath,
                ]);

                $transitions->transition(
                    $lockedOrder,
                    $courier,
                    OrderStatus::Delivered,
                    'delivered',
                    $lockedOrder->delivery_area,
                    $notes,
                    [
                        'delivered_at' => now(),
                        'delivery_notes' => $notes,
                        'cod_collected_amount' => $lockedOrder->payment_method === 'COD' ? $validated['cod_collected_amount'] : null,
                        'delivery_code_used_at' => now(),
                    ]
                );

                return response()->json([
                    'success' => true,
                    'message' => "Order {$lockedOrder->order_number} marked delivered successfully.",
                    'order' => $this->formatOrder($lockedOrder->fresh(['buyer', 'items', 'deliveryAttempts'])),
                ]);
            });
        } catch (Throwable $e) {
            if (is_string($proofPath)) {
                Storage::disk('private')->delete($proofPath);
            }

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Record Failed Delivery Attempt
     */
    public function failDelivery(Request $request, int $id, OrderTransitionService $transitions): JsonResponse
    {
        $validated = $request->validate([
            'failure_reason' => ['required', 'in:recipient_unavailable,incorrect_address,recipient_refused,unreachable_contact,access_issue,damaged_parcel,other'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        /** @var User $courier */
        $courier = $request->user();
        $order = Order::findOrFail($id);

        return DB::transaction(function () use ($order, $courier, $validated, $transitions): JsonResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->delivery_courier_id !== $courier->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized delivery action.'], 403);
            }
            if ($courier->status !== 'approved' || $lockedOrder->status !== 'OUT_FOR_DELIVERY') {
                return response()->json(['success' => false, 'message' => 'Only a delivery in progress can be marked failed.'], 422);
            }

            $notes = $validated['failure_reason'].(filled($validated['delivery_notes'] ?? null) ? ': '.$validated['delivery_notes'] : '');
            $attempt = $this->recordDeliveryAttempt($lockedOrder, $courier, 'failed', [
                'reason' => $validated['failure_reason'],
                'notes' => $validated['delivery_notes'] ?? null,
            ]);

            $transitions->transition(
                $lockedOrder,
                $courier,
                OrderStatus::DeliveryFailed,
                'delivery_failed',
                $lockedOrder->delivery_area,
                $notes,
                [
                    'failed_at' => now(),
                    'delivery_failure_reason' => $validated['failure_reason'],
                    'delivery_notes' => $validated['delivery_notes'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => "Failure recorded for {$lockedOrder->order_number}. Logistics will review next step.",
                'order' => $this->formatOrder($lockedOrder->fresh(['buyer', 'items', 'deliveryAttempts'])),
            ]);
        });
    }

    /**
     * Confirm Return Handover to Seller
     */
    public function confirmReturnDelivery(Request $request, int $id, TransactionAwareNotificationSender $notifications): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'parcel_reference' => ['required', 'string', 'max:100'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $scannedReference = $validated['parcel_reference'];
        $scannedCode = str_starts_with($scannedReference, 'EZP:') ? substr($scannedReference, 4) : $scannedReference;

        if (! hash_equals((string) $order->parcel_code, $scannedCode)) {
            $this->recordParcelScan($request, $order, $courier, 'return_to_seller', 'rejected', $validated['method'] ?? 'manual', 'Scanned parcel code did not match the assigned return.');

            return response()->json(['success' => false, 'message' => 'The scanned parcel does not match this return assignment.'], 422);
        }

        try {
            DB::transaction(function () use ($request, $order, $courier, $notifications, $validated, $scannedReference): void {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($lockedOrder->delivery_courier_id !== $courier->id) {
                    abort(403);
                }
                if ($courier->status !== 'approved' || $lockedOrder->status !== OrderStatus::ReturnInTransit->value) {
                    abort(422, 'This parcel is not on an active return to seller.');
                }
                if ($lockedOrder->return_handed_to_seller_at !== null) {
                    abort(422, 'The seller handoff has already been recorded.');
                }

                $lockedOrder->forceFill(['return_handed_to_seller_at' => now()])->save();
                $this->recordParcelScan($request, $lockedOrder, $courier, 'return_to_seller', 'accepted', $validated['method'] ?? 'manual');

                ParcelTrackingEvent::query()->create([
                    'order_id' => $lockedOrder->id,
                    'actor_id' => $courier->id,
                    'event_type' => 'return_handed_to_seller',
                    'status' => $lockedOrder->status,
                    'location' => $lockedOrder->seller?->municipality,
                    'notes' => 'Courier recorded handing the return parcel to the seller; seller receipt confirmation is pending.',
                ]);
                $notifications->send($lockedOrder->seller, new OrderWorkflowNotification($lockedOrder, 'return_handed_to_seller', 'The courier recorded a return handoff. Confirm receipt only after you physically receive the parcel.'));

                $assignment = DeliveryAssignment::query()
                    ->where('order_id', $lockedOrder->id)
                    ->where('rider_id', $courier->id)
                    ->whereIn('status', ['active', 'returned'])
                    ->lockForUpdate()
                    ->first();
                $assignment?->update([
                    'status' => 'returned',
                    'active_order_id' => null,
                    'released_at' => now(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => "Return handoff for {$order->order_number} recorded. The seller must confirm receipt.",
                'order' => $this->formatOrder($order->fresh(['seller', 'items'])),
            ]);
        } catch (Throwable $e) {
            $this->recordParcelScan($request, $order, $courier, 'return_to_seller', 'rejected', $validated['method'] ?? 'manual', $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Past Work & Delivery History
     */
    public function history(Request $request): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:DELIVERED,COMPLETED,DELIVERY_FAILED,RETURN_IN_TRANSIT,RETURNED_TO_SELLER,PICKED_UP'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $orders = Order::query()
            ->with(['seller', 'buyer', 'items', 'deliveryAttempts'])
            ->where(function (Builder $query) use ($courier): void {
                $query->where('delivery_courier_id', $courier->id)
                    ->orWhere('pickup_courier_id', $courier->id)
                    ->orWhereHas('deliveryAssignments', fn (Builder $assignments) => $assignments->where('rider_id', $courier->id));
            })
            ->when(filled($validated['search'] ?? null), fn (Builder $query) => $query->where('order_number', 'LIKE', '%'.$validated['search'].'%'))
            ->when(filled($validated['status'] ?? null), fn (Builder $query) => $query->where('status', $validated['status']))
            ->latest('updated_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'orders' => collect($orders->items())->map(fn (Order $o) => $this->formatOrder($o)),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Active Parcels Tracking
     */
    public function tracking(Request $request): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();

        $orders = Order::where(fn (Builder $query) => $query->where('pickup_courier_id', $courier->id)->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP'])
            ->orWhere(fn (Builder $query) => $query->where('delivery_courier_id', $courier->id)->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT'])))
            ->with(['trackingEvents.actor', 'seller', 'buyer'])
            ->latest('updated_at')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'orders' => collect($orders->items())->map(fn (Order $o) => $this->formatOrder($o, true)),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Courier Earnings & Allocations
     */
    public function earnings(Request $request): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $ledger = app(FinancialLedgerService::class);
        $availableCents = $ledger->wallet($courier)->balance_cents;

        $allocations = DB::table('logistics_allocations')
            ->join('orders', 'orders.id', '=', 'logistics_allocations.order_id')
            ->where('beneficiary_id', $courier->id)
            ->select('logistics_allocations.*', 'orders.order_number')
            ->orderByDesc('logistics_allocations.id')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'available_balance_cents' => $availableCents,
            'available_balance_pesos' => number_format($availableCents / 100, 2, '.', ''),
            'allocations' => $allocations->items(),
            'pagination' => [
                'current_page' => $allocations->currentPage(),
                'last_page' => $allocations->lastPage(),
                'total' => $allocations->total(),
            ],
        ]);
    }

    /**
     * Submit COD Remittance
     */
    public function submitRemittance(Request $request, int $id, OrderFinancialService $service): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $order = Order::findOrFail($id);

        if ($courier->role !== 'courier' || $order->delivery_courier_id !== $courier->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized remittance submission.'], 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'reference' => ['required', 'string', 'max:120', Rule::unique('order_finances', 'remittance_reference')->ignore(OrderFinance::where('order_id', $order->id)->value('id'))],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('evidence')->store('finance-evidence', 'private');
        try {
            $service->submitRemittance(
                $order,
                $courier,
                CheckoutPricingService::cents($validated['amount']),
                $validated['reference'],
                $path,
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Remittance submitted. Logistics hub will verify the cash receipt.',
            ]);
        } catch (Throwable $e) {
            Storage::disk('private')->delete($path);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Order Message Contacts & Threads
     */
    public function orderMessages(Request $request, int $id, DirectMessagingService $messaging): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $order = Order::findOrFail($id);

        try {
            $messaging->authorizeOrder($order, $actor);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized order messaging.'], 403);
        }

        $participantIds = $order->messageParticipants()->pluck('id');
        $contacts = $messaging->contacts($actor)->where(function ($q) use ($participantIds) {
            $q->whereIn('id', $participantIds)->orWhereIn('role', ['logistics_admin']);
        })->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => trim("{$u->first_name} {$u->last_name}"),
            'role' => $u->role,
            'shop_name' => $u->shop_name ?? $u->business_name,
        ]);

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ],
            'contacts' => $contacts,
        ]);
    }

    /**
     * Send Message Regarding Order
     */
    public function sendOrderMessage(Request $request, int $id, DirectMessagingService $messaging): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $order = Order::findOrFail($id);

        try {
            $messaging->authorizeOrder($order, $actor);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized order messaging.'], 403);
        }

        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $recipient = User::findOrFail($validated['recipient_id']);
        $messaging->authorizeOrder($order, $recipient);

        $conversation = $messaging->start($actor, $recipient);
        $message = $messaging->send($conversation, $actor, $validated['body'], $order);

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully.',
            'conversation_id' => $conversation->id,
            'chat_message' => [
                'id' => $message->id,
                'body' => $message->body,
                'sender_id' => $message->sender_id,
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Format Order Helper for JSON Responses
     */
    private function formatOrder(Order $order, bool $withTimeline = false): array
    {
        $seller = $order->seller;
        $buyer = $order->buyer;

        $sellerAddress = trim(
            ($seller?->house_number ? $seller->house_number.' ' : '').
            ($seller?->street_address ?? '').', '.
            ($seller?->barangay ? $seller->barangay.', ' : '').
            ($seller?->municipality ?? '')
        );

        $buyerAddress = trim(
            ($order->house_number ? $order->house_number.' ' : '').
            ($order->street_address ?? '').', '.
            ($order->barangay ? $order->barangay.', ' : '').
            ($order->delivery_area ?? $order->municipality ?? '')
        );

        $data = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => str_replace('_', ' ', $order->status),
            'payment_method' => $order->payment_method,
            'total_amount' => (float) $order->total_amount,
            'total_amount_formatted' => number_format((float) $order->total_amount, 2),
            'notes' => $order->notes,
            'parcel_code' => $order->parcel_code,
            'weight_grams' => $order->weight_grams,
            'recipient_name' => $order->recipient_name ?? ($buyer ? trim("{$buyer->first_name} {$buyer->last_name}") : 'Recipient'),
            'recipient_contact' => $order->recipient_contact ?? $buyer?->contact_no,
            'delivery_area' => $order->delivery_area,
            'delivery_address' => $buyerAddress,
            'house_number' => $order->house_number,
            'street_address' => $order->street_address,
            'barangay' => $order->barangay,
            'municipality' => $order->municipality,
            'province' => $order->province,
            'postal_code' => $order->postal_code,
            'pickup_claimed_at' => $order->pickup_claimed_at?->toIso8601String(),
            'pickup_arrived_at' => $order->pickup_arrived_at?->toIso8601String(),
            'seller_handover_at' => $order->seller_handover_at?->toIso8601String(),
            'picked_up_at' => $order->picked_up_at?->toIso8601String(),
            'hub_released_at' => $order->hub_released_at?->toIso8601String(),
            'assigned_at' => $order->assigned_at?->toIso8601String(),
            'out_for_delivery_at' => $order->out_for_delivery_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'failed_at' => $order->failed_at?->toIso8601String(),
            'delivery_failure_reason' => $order->delivery_failure_reason,
            'delivery_notes' => $order->delivery_notes,
            'cod_collected_amount' => $order->cod_collected_amount ? (float) $order->cod_collected_amount : null,
            'return_handed_to_seller_at' => $order->return_handed_to_seller_at?->toIso8601String(),
            'seller' => [
                'id' => $seller?->id,
                'name' => $seller?->business_name ?? ($seller ? trim("{$seller->first_name} {$seller->last_name}") : 'Seller'),
                'contact_no' => $seller?->contact_no,
                'address' => $sellerAddress,
                'municipality' => $seller?->municipality,
                'province' => $seller?->province,
            ],
            'buyer' => [
                'id' => $buyer?->id,
                'name' => $buyer ? trim("{$buyer->first_name} {$buyer->last_name}") : 'Buyer',
                'contact_no' => $buyer?->contact_no,
            ],
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ])->values()->all(),
            'items_count' => $order->items->count(),
        ];

        if ($withTimeline) {
            $data['tracking_events'] = $order->trackingEvents->map(fn (ParcelTrackingEvent $e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'event_label' => str_replace('_', ' ', $e->event_type),
                'status' => $e->status,
                'location' => $e->location,
                'notes' => $e->notes,
                'created_at' => $e->created_at->toIso8601String(),
                'actor_name' => $e->actor ? trim("{$e->actor->first_name} {$e->actor->last_name}") : 'System',
            ])->values()->all();

            $data['delivery_attempts'] = $order->deliveryAttempts->map(fn (DeliveryAttempt $a) => [
                'id' => $a->id,
                'attempt_no' => $a->attempt_no,
                'outcome' => $a->outcome,
                'reason' => $a->reason,
                'notes' => $a->notes,
                'attempted_at' => $a->attempted_at?->toIso8601String(),
            ])->values()->all();
        }

        return $data;
    }

    private function recordEvent(Order $order, string $eventType, User $actor, ?string $location = null, ?string $notes = null): void
    {
        ParcelTrackingEvent::create([
            'order_id' => $order->id,
            'actor_id' => $actor->id,
            'event_type' => $eventType,
            'status' => $order->status,
            'location' => $location,
            'notes' => $notes,
        ]);
    }

    private function recordParcelScan(Request $request, Order $order, User $actor, string $station, string $result, string $method, ?string $failureReason = null): void
    {
        DB::table('scan_events')->insert([
            'order_id' => $order->id,
            'actor_id' => $actor->id,
            'station' => $station,
            'result' => $result,
            'failure_reason' => $failureReason,
            'method' => $method,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    private function amountInCentavos(string|float $amount): int
    {
        [$pesos, $centavos] = array_pad(explode('.', (string) $amount, 2), 2, '');

        return ((int) $pesos * 100) + (int) str_pad(substr($centavos, 0, 2), 2, '0');
    }

    /** @param array<string, mixed> $attributes */
    private function recordDeliveryAttempt(Order $order, User $courier, string $outcome, array $attributes): DeliveryAttempt
    {
        $attempt = DeliveryAttempt::query()
            ->where('order_id', $order->id)
            ->where('rider_id', $courier->id)
            ->where('outcome', 'scheduled')
            ->lockForUpdate()
            ->first();

        if ($attempt !== null) {
            $attempt->update([...$attributes, 'outcome' => $outcome, 'attempted_at' => now()]);

            return $attempt->refresh();
        }

        return DeliveryAttempt::query()->create([
            ...$attributes,
            'order_id' => $order->id,
            'rider_id' => $courier->id,
            'attempt_no' => ((int) DeliveryAttempt::query()->where('order_id', $order->id)->max('attempt_no')) + 1,
            'outcome' => $outcome,
            'attempted_at' => now(),
        ]);
    }
}
