<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAttempt;
use App\Models\DispatchLocation;
use App\Models\DispatchOffer;
use App\Models\Order;
use App\Models\OrderFinance;
use App\Models\ParcelTrackingEvent;
use App\Models\ParcelTransfer;
use App\Models\RemittanceBatch;
use App\Models\StaffProfileRevision;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use App\Services\AdministrativeAuditService;
use App\Services\CheckoutPricingService;
use App\Services\DeliveryCodeService;
use App\Services\DeliveryRetryService;
use App\Services\DirectMessagingService;
use App\Services\DispatchLocationService;
use App\Services\DispatchOfferService;
use App\Services\FinancialLedgerService;
use App\Services\LogisticsHubNotificationService;
use App\Services\OrderFinancialService;
use App\Services\OrderTransitionService;
use App\Services\OrderWalletService;
use App\Services\ParcelLoadService;
use App\Services\RemittanceBatchService;
use App\Services\ReturnPickupService;
use App\Services\RiderBadgeService;
use App\Services\RiderWorkAvailabilityService;
use App\Services\TransactionAwareNotificationSender;
use App\Services\UserDocumentService;
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
     * Direct Parcel Scan Intake & Confirmation (Auto-matches assigned order by parcel code/order number)
     */
    public function scanPickup(Request $request, OrderTransitionService $transitions, TransactionAwareNotificationSender $notifications): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();

        $validated = $request->validate([
            'parcel_reference' => ['required', 'string', 'max:100'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $scannedReference = trim($validated['parcel_reference']);
        $scannedCode = str_starts_with($scannedReference, 'EZP:') ? substr($scannedReference, 4) : $scannedReference;

        // Auto-match claimed pickup or assigned order for this courier
        $order = Order::query()
            ->where(function ($q) use ($courier) {
                $q->where('pickup_courier_id', $courier->id)
                  ->orWhere('delivery_courier_id', $courier->id);
            })
            ->where(function ($q) use ($scannedCode, $scannedReference) {
                $q->where('parcel_code', $scannedCode)
                  ->orWhere('parcel_code', $scannedReference)
                  ->orWhere('order_number', $scannedCode)
                  ->orWhere('order_number', $scannedReference);
            })
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => "No assigned pickup found matching parcel reference '{$scannedReference}'.",
            ], 404);
        }

        return $this->confirmPickup($request, $order->id, $transitions, $notifications);
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
     * Submit Delivery Retry Proposal for a failed delivery
     */
    public function storeRetryProposal(Request $request, int $id, DeliveryRetryService $retries): JsonResponse
    {
        $validated = $request->validate([
            'failed_attempt_id' => ['required', 'integer', 'min:1'],
            'proposed_for' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        /** @var User $courier */
        $courier = $request->user();
        $order = Order::findOrFail($id);

        if ($order->delivery_courier_id !== $courier->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action for this order.'], 403);
        }

        try {
            $proposal = $retries->request(
                $order,
                $courier,
                (int) $validated['failed_attempt_id'],
                $validated['proposed_for'],
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Retry proposal submitted successfully. Logistics will review it.',
                'order' => $this->formatOrder($order->fresh(['buyer', 'items', 'deliveryAttempts', 'deliveryRetryRequests'])),
                'proposal' => [
                    'id' => $proposal->id,
                    'proposed_for' => $proposal->proposed_for?->format('Y-m-d H:i:s'),
                    'reason' => $proposal->reason,
                    'status' => $proposal->status ?? 'pending',
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Failed to submit retry proposal.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'An error occurred while submitting the retry proposal.',
            ], 422);
        }
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
     * Get Courier Work Availability
     */
    public function workAvailability(Request $request): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $service = app(RiderWorkAvailabilityService::class);

        return response()->json([
            'success' => true,
            'work_availability' => $courier->work_availability ?? 'available',
            'version' => (int) ($courier->work_availability_version ?? 0),
            'enforced' => $service->enforced(),
            'changed_at' => $courier->work_availability_changed_at?->toIso8601String(),
        ]);
    }

    /**
     * Update Courier Work Availability
     */
    public function updateWorkAvailability(Request $request, RiderWorkAvailabilityService $availability): JsonResponse
    {
        $data = $request->validate([
            'state' => ['required', 'in:available,unavailable'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        /** @var User $courier */
        $courier = $request->user();

        try {
            $released = $availability->choose($courier, $data['state'], (int) $data['version']);
            $courier->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Work availability updated to ' . $data['state'] . '.',
                'work_availability' => $courier->work_availability,
                'version' => (int) $courier->work_availability_version,
                'released' => $released,
                'enforced' => $availability->enforced(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get Weight & Load Planning for Courier
     */
    public function loadPlanning(Request $request, ParcelLoadService $loads): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $summary = $loads->summary($courier);

        $orders = $loads->activeLoads($courier->id)
            ->whereNotIn('status', ['CANCELLED', 'COMPLETED', 'DELIVERED', 'RETURNED_TO_SELLER'])
            ->with(['seller', 'buyer', 'items'])
            ->latest('id')
            ->get()
            ->map(fn (Order $o) => $this->formatOrder($o));

        return response()->json([
            'success' => true,
            'summary' => [
                'count' => $summary['count'],
                'grams' => $summary['grams'],
                'kg_formatted' => number_format($summary['grams'] / 1000, 2) . ' kg',
                'capacity_grams' => $summary['capacity'],
                'capacity_kg_formatted' => $summary['capacity'] ? number_format($summary['capacity'] / 1000, 2) . ' kg' : 'Unset',
                'remaining_grams' => $summary['remaining'],
                'remaining_kg_formatted' => $summary['remaining'] !== null ? number_format($summary['remaining'] / 1000, 2) . ' kg' : null,
                'overweight' => (bool) $summary['overweight'],
                'unknown_weight_count' => $summary['unknown'],
                'offer_count' => $summary['offer_count'] ?? 0,
                'offer_grams' => $summary['offer_grams'] ?? 0,
                'hub_transfer_only' => (bool) $courier->hub_transfer_only,
                'vehicle_type' => $courier->vehicle_type,
                'plate_number' => $courier->plate_number,
            ],
            'orders' => $orders,
        ]);
    }

    /**
     * Get Automatic Dispatch Offers for Courier
     */
    public function dispatchOffers(Request $request): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();

        $offers = DispatchOffer::where('rider_id', $courier->id)
            ->with(['order.seller', 'order.buyer', 'cycle.hub'])
            ->latest('id')
            ->paginate(25);

        $point = DispatchLocation::where('user_id', $courier->id)->first();

        $formattedOffers = collect($offers->items())->map(function (DispatchOffer $offer) {
            $isExpired = $offer->expires_at && $offer->expires_at->isPast();
            return [
                'id' => $offer->id,
                'cycle_id' => $offer->dispatch_cycle_id,
                'order_id' => $offer->order_id,
                'order_number' => $offer->order?->order_number,
                'order_status' => $offer->order?->status,
                'status' => $offer->status,
                'is_active' => $offer->status === 'PENDING' && ! $isExpired,
                'is_expired' => $isExpired,
                'weight_grams' => $offer->weight_grams,
                'weight_kg' => number_format($offer->weight_grams / 1000, 2) . ' kg',
                'payout_cents' => $offer->payout_cents,
                'payout_pesos' => number_format($offer->payout_cents / 100, 2),
                'expires_at' => $offer->expires_at?->toIso8601String(),
                'response_reason' => $offer->response_reason,
                'hub_name' => $offer->cycle?->hub?->business_name ?? $offer->cycle?->hub?->name,
                'seller_name' => $offer->order?->seller?->business_name ?? $offer->order?->seller?->name,
                'seller_address' => $offer->order?->seller?->municipality,
                'delivery_area' => $offer->order?->delivery_area ?? $offer->order?->municipality,
                'created_at' => $offer->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'offers' => $formattedOffers,
            'location_point' => $point ? [
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
                'accuracy_meters' => $point->accuracy_meters,
                'captured_at' => $point->captured_at?->toIso8601String(),
            ] : null,
            'pagination' => [
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
                'total' => $offers->total(),
            ],
        ]);
    }

    /**
     * Update Courier Device Position for Automatic Dispatch
     */
    public function updateDispatchPosition(Request $request, DispatchLocationService $locations): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'accuracy_meters' => ['required', 'numeric'],
            'captured_at' => ['required', 'date'],
        ]);

        $locations->rider($request->user(), $data);

        return response()->json([
            'success' => true,
            'message' => 'Device-reported position updated for automatic dispatch offers.',
        ]);
    }

    /**
     * Accept or Decline Dispatch Offer
     */
    public function respondDispatchOffer(Request $request, int $offerId, DispatchOfferService $offers): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:accept,decline'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $offer = DispatchOffer::findOrFail($offerId);
        $result = $offers->respond($offer, $request->user(), $data['decision'] === 'accept', $data['reason'] ?? null);

        return response()->json([
            'success' => true,
            'decision' => $data['decision'],
            'result' => $result,
            'message' => "Offer {$result}. Check your workboard for updated assignments.",
        ]);
    }

    /**
     * List Approved Return Pickups for Courier
     */
    public function returnPickups(Request $request): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();

        $orders = Order::whereHas('dispute', fn ($query) => $query->whereIn('status', ['REFUND_APPROVED', 'REPLACEMENT_APPROVED']))
            ->whereIn('status', ['DELIVERED', 'COMPLETED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'])
            ->where(fn ($q) => $q->whereHas('returnPickupAssignment', fn ($assignment) => $assignment->where('rider_id', $courier->id))
                ->orWhere(fn ($return) => $return->where('status', 'RETURN_IN_TRANSIT')->where('delivery_courier_id', $courier->id)))
            ->with(['buyer', 'seller', 'dispute', 'returnPickupAssignment.rider', 'trackingEvents'])
            ->latest('id')
            ->paginate(20);

        $data = collect($orders->items())->map(function (Order $order) use ($courier) {
            $assignment = $order->returnPickupAssignment;
            $handedOver = $assignment && $order->trackingEvents->where('event_type', 'return_buyer_handover')->where('actor_id', $order->buyer_id)->where('notes', 'Return pickup assignment #'.$assignment->id)->isNotEmpty();
            $canAccept = $assignment && $assignment->rider_id === $courier->id && $assignment->accepted_at === null;
            $canCollect = $assignment && $assignment->rider_id === $courier->id && $assignment->accepted_at !== null && $handedOver && in_array($order->status, ['DELIVERED', 'COMPLETED'], true);
            $canReturnHandover = $order->status === 'RETURN_IN_TRANSIT' && $order->delivery_courier_id === $courier->id && ! $order->return_handed_to_seller_at;

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'parcel_code' => $order->parcel_code,
                'status' => $order->status,
                'operational_status' => $order->operational_status,
                'total_amount' => (float) $order->total_amount,
                'seller' => [
                    'id' => $order->seller?->id,
                    'name' => $order->seller?->business_name ?? $order->seller?->first_name,
                    'contact_no' => $order->seller?->contact_no,
                    'address' => trim(($order->seller?->house_number ? $order->seller->house_number . ' ' : '') . ($order->seller?->street_address ?? '')),
                    'municipality' => $order->seller?->municipality,
                    'barangay' => $order->seller?->barangay,
                ],
                'buyer' => [
                    'id' => $order->buyer?->id,
                    'name' => trim("{$order->buyer?->first_name} {$order->buyer?->last_name}"),
                    'contact_no' => $order->buyer?->contact_no,
                    'address' => trim(($order->house_number ? $order->house_number . ' ' : '') . ($order->street_address ?? '')),
                    'municipality' => $order->municipality,
                    'barangay' => $order->barangay,
                ],
                'dispute_status' => $order->dispute?->status,
                'dispute_reason' => $order->dispute?->reason,
                'dispute_resolution' => $order->dispute?->resolution,
                'is_accepted' => $assignment?->accepted_at !== null,
                'accepted_at' => $assignment?->accepted_at?->toIso8601String(),
                'is_handed_over' => $handedOver,
                'can_accept' => $canAccept,
                'can_collect' => $canCollect,
                'can_return_handover' => $canReturnHandover,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'total' => $orders->total(),
        ]);
    }

    /**
     * Accept Return Pickup Assignment
     */
    public function acceptReturnPickup(Request $request, int $orderId, ReturnPickupService $service): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        $service->accept($order, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Return pickup accepted. Wait for buyer handover confirmation before collection.',
        ]);
    }

    /**
     * Collect Return Parcel by Scanning Reference
     */
    public function collectReturnPickup(Request $request, int $orderId, ReturnPickupService $service): JsonResponse
    {
        $data = $request->validate([
            'parcel_reference' => ['required', 'string', 'max:100'],
        ]);

        $order = Order::findOrFail($orderId);
        $ref = trim($data['parcel_reference']);
        if (! str_starts_with($ref, 'EZP:')) {
            $ref = 'EZP:' . $ref;
        }

        $service->collect($order, $request->user(), $ref);

        return response()->json([
            'success' => true,
            'message' => 'Return parcel collected. It is now in transit to the merchant.',
        ]);
    }

    /**
     * Get Remittance Batches and Pending Collections
     */
    public function remittanceBatches(Request $request): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();

        $batches = RemittanceBatch::where('rider_id', $courier->id)
            ->with(['hub', 'items.orderFinance.order'])
            ->latest('id')
            ->paginate(20);

        $pendingCollections = OrderFinance::where('remittance_status', 'COLLECTED')
            ->where('collector_id', $courier->id)
            ->with('order')
            ->oldest('id')
            ->get()
            ->map(fn (OrderFinance $f) => [
                'id' => $f->id,
                'order_id' => $f->order_id,
                'order_number' => $f->order?->order_number,
                'collected_cents' => $f->collected_cents,
                'collected_pesos' => number_format($f->collected_cents / 100, 2),
                'collected_at' => $f->collected_at?->toIso8601String(),
                'hub_id' => $f->order?->sorting_center_id,
            ]);

        $hubs = User::where('role', 'sorting_center')
            ->where('status', 'approved')
            ->select('id', 'first_name', 'last_name', 'business_name', 'municipality')
            ->get()
            ->map(fn (User $h) => [
                'id' => $h->id,
                'name' => $h->business_name ?: trim("{$h->first_name} {$h->last_name}"),
                'municipality' => $h->municipality,
            ]);

        $formattedBatches = collect($batches->items())->map(fn (RemittanceBatch $b) => [
            'id' => $b->id,
            'batch_reference' => $b->batch_reference ?? ('BATCH-' . $b->id),
            'hub_name' => $b->hub?->business_name ?? $b->hub?->name,
            'expected_cents' => $b->expected_cents,
            'expected_pesos' => number_format($b->expected_cents / 100, 2),
            'submitted_cents' => $b->submitted_cents,
            'submitted_pesos' => number_format($b->submitted_cents / 100, 2),
            'received_cents' => $b->received_cents,
            'received_pesos' => $b->received_cents ? number_format($b->received_cents / 100, 2) : null,
            'status' => $b->status,
            'has_evidence' => ! empty($b->evidence_path),
            'has_receipt' => ! empty($b->receipt_path),
            'notes' => $b->submission_notes,
            'items_count' => $b->items->count(),
            'created_at' => $b->created_at->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'batches' => $formattedBatches,
            'pending_collections' => $pendingCollections,
            'hubs' => $hubs,
            'pagination' => [
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
                'total' => $batches->total(),
            ],
        ]);
    }

    /**
     * Submit Remittance Batch
     */
    public function submitRemittanceBatch(Request $request, RemittanceBatchService $service): JsonResponse
    {
        $data = $request->validate([
            'hub_id' => ['required', 'integer', 'exists:users,id'],
            'finance_ids' => ['required', 'array', 'min:1', 'max:100'],
            'finance_ids.*' => ['integer', 'distinct'],
            'amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('evidence')->store('finance-evidence', 'private');

        $hub = User::findOrFail($data['hub_id']);
        $batch = $service->submit(
            $request->user(),
            $hub,
            $data['finance_ids'],
            CheckoutPricingService::cents($data['amount']),
            $path,
            $data['reason']
        );

        return response()->json([
            'success' => true,
            'message' => 'Remittance batch submitted successfully. Awaiting hub physical receipt.',
            'batch_id' => $batch->id,
        ]);
    }

    /**
     * Get Complete Rider Profile
     */
    public function riderProfile(Request $request, UserDocumentService $documents): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user()->load(['serviceAreas', 'logisticsOwner']);
        $submittedDocuments = $documents->submittedForReview($courier);
        $pendingProfile = StaffProfileRevision::where('pending_user_id', $courier->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'courier' => [
                'id' => $courier->id,
                'first_name' => $courier->first_name,
                'middle_name' => $courier->middle_name,
                'middle_initial' => $courier->middle_initial,
                'last_name' => $courier->last_name,
                'suffix' => $courier->suffix,
                'name' => trim("{$courier->first_name} {$courier->last_name}"),
                'email' => $courier->email,
                'contact_no' => $courier->contact_no,
                'role' => $courier->role,
                'status' => $courier->status,
                'badge_code' => $courier->badge_code,
                'vehicle_type' => $courier->vehicle_type,
                'plate_number' => $courier->plate_number,
                'vehicle_category' => $courier->vehicle_category,
                'vehicle_description' => $courier->vehicle_description,
                'license_number' => $courier->license_number,
                'license_expires_at' => $courier->license_expires_at?->format('Y-m-d'),
                'vehicle_capacity_grams' => $courier->vehicle_capacity_grams,
                'vehicle_capacity_kg' => $courier->vehicle_capacity_grams ? number_format($courier->vehicle_capacity_grams / 1000, 2) . ' kg' : 'Unset',
                'vehicle_capacity_verified_at' => $courier->vehicle_capacity_verified_at?->toIso8601String(),
                'hub_transfer_only' => (bool) $courier->hub_transfer_only,
                'work_availability' => $courier->work_availability ?? 'available',
                'work_availability_version' => (int) ($courier->work_availability_version ?? 0),
                'work_availability_changed_at' => $courier->work_availability_changed_at?->toIso8601String(),
                'municipality' => $courier->municipality,
                'province' => $courier->province,
                'region' => $courier->region,
                'barangay' => $courier->barangay,
                'street_address' => $courier->street_address,
                'house_number' => $courier->house_number,
                'postal_code' => $courier->postal_code,
                'logistics_owner' => $courier->logisticsOwner ? [
                    'id' => $courier->logisticsOwner->id,
                    'name' => $courier->logisticsOwner->business_name ?? trim("{$courier->logisticsOwner->first_name} {$courier->logisticsOwner->last_name}"),
                    'contact_no' => $courier->logisticsOwner->contact_no,
                ] : null,
                'service_areas' => $courier->serviceAreas->map(fn ($area) => [
                    'id' => $area->id,
                    'name' => $area->name,
                    'province' => $area->province,
                    'municipality' => $area->municipality,
                ]),
                'submitted_documents' => $submittedDocuments,
                'has_pending_profile_update' => $pendingProfile !== null,
                'profile_display_name' => $courier->profile_display_name,
                'profile_bio' => $courier->profile_bio,
                'profile_theme' => $courier->profile_theme ?: 'rose',
                'has_avatar' => !empty($courier->profile_avatar_path),
                'avatar_url' => $courier->profile_avatar_path ? url('/api/account/profile/media/avatar?v='.($courier->updated_at?->timestamp ?? time())) : null,
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
            'parcel_weight_grams' => $order->parcel_weight_grams ?? $order->weight_grams,
            'weight_grams' => $order->parcel_weight_grams ?? $order->weight_grams,
            'weight_kg' => ($order->parcel_weight_grams ?? $order->weight_grams) ? number_format(($order->parcel_weight_grams ?? $order->weight_grams) / 1000, 2) . ' kg' : 'Not recorded',
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

    /**
     * Inter-hub Parcel Transfers for Cross-hub Courier Riders
     */
    public function parcelTransfers(Request $request): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();

        $transfers = ParcelTransfer::query()
            ->with(['order:id,order_number,status,parcel_code', 'sourceHub:id,business_name,name,municipality,province', 'destinationHub:id,business_name,name,municipality,province'])
            ->where('carrier_rider_id', $courier->id)
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'transfers' => $transfers->items(),
            'pagination' => [
                'current_page' => $transfers->currentPage(),
                'last_page' => $transfers->lastPage(),
                'total' => $transfers->total(),
            ],
        ]);
    }
}
