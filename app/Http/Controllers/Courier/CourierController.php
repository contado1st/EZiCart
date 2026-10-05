<?php

namespace App\Http\Controllers\Courier;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAttempt;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use App\Services\DeliveryCodeService;
use App\Services\LogisticsHubNotificationService;
use App\Services\OrderTransitionService;
use App\Services\QrCodeService;
use App\Services\RiderBadgeService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class CourierController extends Controller
{
    public function dashboard(RiderBadgeService $badges, QrCodeService $qrCodes): View
    {
        $courier = $this->authenticatedUser();
        $badgeCode = $badges->ensure($courier);
        $badgeQr = $qrCodes->svg('EZR:'.$badgeCode, 180);
        $todayStart = today()->startOfDay();
        $tomorrowStart = today()->addDay()->startOfDay();
        $availablePickups = Order::where('status', 'READY_FOR_PICKUP')->where('pickup_courier_id', $courier->id)->whereNull('pickup_claimed_at')->with(['seller', 'items'])->latest()->paginate(8, ['*'], 'pickups');
        $claimedPickups = Order::where('pickup_courier_id', $courier->id)->where('status', 'READY_FOR_PICKUP')->with('seller')->latest()->get();
        $myActivePickups = Order::where('pickup_courier_id', $courier->id)->where('status', 'PICKED_UP')->with('seller')->latest()->get();
        $myReturns = Order::query()
            ->where('delivery_courier_id', $courier->id)
            ->where('status', OrderStatus::ReturnInTransit->value)
            ->whereNull('return_handed_to_seller_at')
            ->with('seller')
            ->latest('failed_at')
            ->get();
        $myDeliveryAssignments = Order::where('delivery_courier_id', $courier->id)
            ->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])
            ->with(['buyer', 'items'])
            ->orderBy('delivery_area')
            ->orderBy('assigned_at')
            ->paginate(10, ['*'], 'deliveries');
        $myFailedDeliveries = Order::where('delivery_courier_id', $courier->id)->where('status', 'DELIVERY_FAILED')->with('deliveryAttempts')->latest('failed_at')->get();
        $stats = [
            'claimed_pickups' => $claimedPickups->count(),
            'in_transit_hub' => $myActivePickups->count(),
            'assigned_delivery' => Order::where('delivery_courier_id', $courier->id)->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])->count(),
            'returning_to_seller' => $myReturns->count(),
            'completed_today' => Order::where('delivery_courier_id', $courier->id)->whereIn('status', ['DELIVERED', 'COMPLETED'])->whereBetween('delivered_at', [$todayStart, $tomorrowStart])->count(),
            'failed_today' => DeliveryAttempt::query()
                ->where('rider_id', $courier->id)
                ->where('outcome', 'failed')
                ->whereBetween('attempted_at', [$todayStart, $tomorrowStart])
                ->count(),
        ];

        return view('courier.dashboard', compact('courier', 'badgeCode', 'badgeQr', 'availablePickups', 'claimedPickups', 'myActivePickups', 'myReturns', 'myDeliveryAssignments', 'myFailedDeliveries', 'stats'));
    }

    public function rotateBadge(RiderBadgeService $badges): RedirectResponse
    {
        $badges->rotate($this->authenticatedUser());

        return back()->with('success', 'Your rider badge was rotated. Previous printed codes are no longer valid.');
    }

    public function showOrder(Order $order): View
    {
        $courier = $this->authenticatedUser();
        $hasPickupAccess = $order->pickup_courier_id === $courier->id
            && in_array($order->status, ['READY_FOR_PICKUP', 'PICKED_UP'], true);
        $hasDeliveryAccess = $order->delivery_courier_id === $courier->id
            && in_array($order->status, ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED'], true);
        abort_unless($hasPickupAccess || $hasDeliveryAccess, 403);
        $order->load(['seller', 'buyer', 'items', 'trackingEvents.actor', 'deliveryAttempts']);

        return view('courier.orders.show', compact('order'));
    }

    public function claimPickup(Order $order, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        $courier = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $courier, $notifications): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($courier->status === 'approved', 403, 'Your rider account is not approved for pickups.');
            abort_unless($lockedOrder->pickup_courier_id === $courier->id, 403);
            abort_unless(
                $lockedOrder->payment_method === 'COD'
                    && $lockedOrder->status === 'READY_FOR_PICKUP'
                    && $lockedOrder->pickup_claimed_at === null,
                422,
                'This pickup has already been accepted, is on payment verification hold, or is no longer available.',
            );
            $lockedOrder->forceFill(['pickup_claimed_at' => now()])->save();
            $this->recordEvent($lockedOrder, 'pickup_accepted', $courier, $lockedOrder->seller?->municipality, 'Rider accepted the Logistics pickup assignment.');
            $notifications->send($lockedOrder->seller, new OrderWorkflowNotification($lockedOrder, 'pickup_accepted', 'The assigned rider accepted the pickup request.'));

            return back()->with('success', "Pickup {$lockedOrder->order_number} added to your route.");
        });
    }

    public function declinePickup(Request $request, Order $order, TransactionAwareNotificationSender $notifications, LogisticsHubNotificationService $hubNotifications): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $courier = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $courier, $validated, $notifications, $hubNotifications): RedirectResponse {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($courier->status === 'approved' && $lockedOrder->status === 'READY_FOR_PICKUP', 422, 'This pickup cannot be declined.');
            abort_unless($lockedOrder->pickup_courier_id === $courier->id && $lockedOrder->pickup_claimed_at === null, 403);

            $lockedOrder->forceFill(['pickup_courier_id' => null])->save();
            $this->recordEvent(
                $lockedOrder,
                'pickup_declined',
                $courier,
                $lockedOrder->seller?->municipality,
                filled($validated['reason'] ?? null) ? $validated['reason'] : 'Rider declined the pickup assignment.',
            );
            $notifications->send($lockedOrder->seller, new OrderWorkflowNotification($lockedOrder, 'pickup_declined', 'The assigned rider declined the pickup. Logistics will reassign it.'));
            $hubNotifications->sendForOrder($lockedOrder, $notifications, new OrderWorkflowNotification($lockedOrder, 'pickup_declined', 'A rider declined a pickup and it is back in the Logistics queue.'));

            return back()->with('success', "Pickup {$lockedOrder->order_number} returned to the Logistics queue.");
        });
    }

    public function confirmPickup(Request $request, Order $order, OrderTransitionService $transitions, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        $courier = $this->authenticatedUser();
        $validated = $request->validate([
            'parcel_reference' => ['nullable', 'string', 'max:100'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $scannedReference = $validated['parcel_reference'] ?? null;

        if (filled($scannedReference)) {
            $scannedCode = str_starts_with($scannedReference, 'EZP:')
                ? substr($scannedReference, 4)
                : $scannedReference;

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

                throw ValidationException::withMessages(['parcel_reference' => 'The scanned parcel does not match this pickup assignment.']);
            }
        }

        try {
            return DB::transaction(function () use ($request, $order, $courier, $transitions, $notifications, $validated, $scannedReference): RedirectResponse {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedOrder->pickup_courier_id === $courier->id, 403);
                abort_unless(
                    $courier->status === 'approved'
                        && $lockedOrder->payment_method === 'COD'
                        && $lockedOrder->status === 'READY_FOR_PICKUP'
                        && $lockedOrder->pickup_claimed_at !== null,
                    422,
                    'This pickup cannot be confirmed.',
                );
                if ($lockedOrder->pickup_arrived_at === null) {
                    $lockedOrder->forceFill(['pickup_arrived_at' => now()])->save();
                    $this->recordEvent($lockedOrder, 'pickup_arrived', $courier, $lockedOrder->seller?->municipality, 'Rider arrived and requested seller handover confirmation.');
                    $notifications->send($lockedOrder->seller, new OrderWorkflowNotification($lockedOrder, 'pickup_arrived', 'The rider arrived. Confirm the parcel handover when ready.'));

                    return back()->with('success', 'Arrival recorded. Wait for the seller to confirm parcel handover, then confirm possession.');
                }

                abort_unless($lockedOrder->seller_handover_at !== null, 422, 'The seller must confirm parcel handover before pickup can be completed.');
                abort_unless(filled($scannedReference), 422, 'Scan the parcel label before confirming possession.');
                abort_unless(hash_equals((string) $lockedOrder->parcel_code, str_starts_with($scannedReference, 'EZP:') ? substr($scannedReference, 4) : $scannedReference), 422, 'The assigned parcel changed. Scan the parcel label again.');
                $transitions->transition($lockedOrder, $courier, OrderStatus::PickedUp, 'picked_up', $lockedOrder->seller?->municipality, 'Parcel collected from seller; traveling to sorting center.', ['picked_up_at' => now()]);
                DB::table('scan_events')->insert([
                    'order_id' => $lockedOrder->id,
                    'actor_id' => $courier->id,
                    'station' => 'seller_pickup',
                    'result' => 'accepted',
                    'method' => $validated['method'] ?? 'manual',
                    'ip' => $request->ip(),
                    'created_at' => now(),
                ]);

                return back()->with('success', "Parcel {$lockedOrder->order_number} collected. Bring it to the sorting center for handoff.");
            });
        } catch (Throwable $exception) {
            if (filled($scannedReference)) {
                DB::table('scan_events')->insert([
                    'order_id' => $order->id,
                    'actor_id' => $courier->id,
                    'station' => 'seller_pickup',
                    'result' => 'rejected',
                    'failure_reason' => 'Parcel scan was valid but pickup could not be completed.',
                    'method' => $validated['method'] ?? 'manual',
                    'ip' => $request->ip(),
                    'created_at' => now(),
                ]);
            }

            throw $exception;
        }
    }

    public function startDelivery(Request $request, Order $order, OrderTransitionService $transitions, DeliveryCodeService $deliveryCodes): RedirectResponse
    {
        $courier = $this->authenticatedUser();
        $validated = $request->validate([
            'parcel_reference' => ['nullable', 'string', 'max:100'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $scannedReference = $validated['parcel_reference'] ?? null;
        if (filled($scannedReference)) {
            $scannedCode = str_starts_with($scannedReference, 'EZP:')
                ? substr($scannedReference, 4)
                : $scannedReference;

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

                throw ValidationException::withMessages(['parcel_reference' => 'The scanned parcel does not match this delivery assignment.']);
            }
        }

        try {
            return DB::transaction(function () use ($request, $order, $courier, $transitions, $deliveryCodes, $validated, $scannedReference): RedirectResponse {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedOrder->delivery_courier_id === $courier->id, 403);
                abort_unless($courier->status === 'approved' && $lockedOrder->status === 'ASSIGNED_TO_RIDER', 422, 'This parcel cannot be started for delivery.');
                abort_unless($lockedOrder->payment_method === 'COD', 422, 'Delivery is on hold until payment verification for this method is configured.');
                abort_unless($lockedOrder->hub_released_at !== null, 422, 'Logistics must confirm the hub handoff before delivery can start.');
                abort_unless(
                    DeliveryAttempt::query()->where('order_id', $lockedOrder->id)->where('outcome', 'failed')->count()
                        < max(1, (int) config('logistics.maximum_delivery_attempts', 3)),
                    422,
                    'The maximum delivery attempts have been reached. Logistics must return the parcel to the seller.',
                );
                $scheduledAttempt = DeliveryAttempt::query()
                    ->where('order_id', $lockedOrder->id)
                    ->where('rider_id', $courier->id)
                    ->where('outcome', 'scheduled')
                    ->lockForUpdate()
                    ->first();
                abort_unless($scheduledAttempt?->scheduled_at === null || $scheduledAttempt->scheduled_at->lte(now()), 422, 'Wait until the scheduled retry time before starting delivery.');
                $deliveryCode = $deliveryCodes->issue();
                unset($deliveryCode['code']);
                $transitions->transition($lockedOrder, $courier, OrderStatus::OutForDelivery, 'out_for_delivery', $lockedOrder->delivery_area, 'Rider collected parcel from the hub.', [
                    'out_for_delivery_at' => now(),
                    ...$deliveryCode,
                ]);
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

                return back()->with('success', "Order {$lockedOrder->order_number} is out for delivery.");
            });
        } catch (Throwable $exception) {
            if (filled($scannedReference)) {
                DB::table('scan_events')->insert([
                    'order_id' => $order->id,
                    'actor_id' => $courier->id,
                    'station' => 'delivery_start',
                    'result' => 'rejected',
                    'failure_reason' => 'Parcel scan was valid but the delivery could not be started.',
                    'method' => $validated['method'] ?? 'manual',
                    'ip' => $request->ip(),
                    'created_at' => now(),
                ]);
            }

            throw $exception;
        }
    }

    public function declineDeliveryAssignment(Request $request, Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $transitions->declineDeliveryAssignment($order, $this->authenticatedUser(), $validated['reason'] ?? null);

        return back()->with('success', "Delivery assignment for {$order->order_number} declined. Logistics can dispatch it to another rider.");
    }

    public function confirmReturnDelivery(Request $request, Order $order, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        $courier = $this->authenticatedUser();
        abort_unless($order->delivery_courier_id === $courier->id, 403);
        $validated = $request->validate([
            'parcel_reference' => ['nullable', 'string', 'max:100'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $scannedReference = $validated['parcel_reference'] ?? null;

        if (filled($scannedReference) && ! $this->parcelReferenceMatches($order, $scannedReference)) {
            $this->recordParcelScan($request, $order, $courier, 'return_to_seller', 'rejected', $validated['method'] ?? 'manual', 'Scanned parcel code did not match the assigned return.');

            throw ValidationException::withMessages(['parcel_reference' => 'The scanned parcel does not match this return assignment.']);
        }

        try {
            DB::transaction(function () use ($request, $order, $courier, $notifications, $validated, $scannedReference): void {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedOrder->delivery_courier_id === $courier->id, 403);
                abort_unless($courier->status === 'approved' && $lockedOrder->status === OrderStatus::ReturnInTransit->value, 422, 'This parcel is not on an active return to seller.');
                abort_unless($lockedOrder->return_handed_to_seller_at === null, 422, 'The seller handoff has already been recorded.');
                abort_unless(filled($scannedReference), 422, 'Scan the parcel label before confirming return handoff.');
                abort_unless($this->parcelReferenceMatches($lockedOrder, $scannedReference), 422, 'The assigned parcel changed. Scan its label again.');

                $lockedOrder->forceFill(['return_handed_to_seller_at' => now()])->save();
                $this->recordParcelScan($request, $lockedOrder, $courier, 'return_to_seller', 'accepted', $validated['method'] ?? 'manual');
                $seller = $lockedOrder->seller;
                ParcelTrackingEvent::query()->create([
                    'order_id' => $lockedOrder->id,
                    'actor_id' => $courier->id,
                    'event_type' => 'return_handed_to_seller',
                    'status' => $lockedOrder->status,
                    'location' => $seller?->municipality,
                    'notes' => 'Courier recorded handing the return parcel to the seller; seller receipt confirmation is pending.',
                ]);
                $notifications->send($seller, new OrderWorkflowNotification($lockedOrder, 'return_handed_to_seller', 'The courier recorded a return handoff. Confirm receipt only after you physically receive the parcel.'));

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
        } catch (Throwable $exception) {
            if (filled($scannedReference)) {
                $this->recordParcelScan($request, $order, $courier, 'return_to_seller', 'rejected', $validated['method'] ?? 'manual', 'Parcel scan was valid but the return handoff could not be completed.');
            }

            throw $exception;
        }

        return back()->with('success', "Return handoff for {$order->order_number} recorded. The seller must confirm receipt.");
    }

    public function completeDelivery(Request $request, Order $order, OrderTransitionService $transitions, DeliveryCodeService $deliveryCodes): RedirectResponse
    {
        $courier = $this->authenticatedUser();
        abort_unless($order->delivery_courier_id === $courier->id, 403);

        $validated = $request->validate([
            'recipient_confirmation' => ['required', 'string', 'max:120'],
            'delivery_code' => ['required', 'string', 'max:32'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'cod_collected_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $proofPath = null;
        $deliveryCode = str_starts_with($validated['delivery_code'], 'EZD:')
            ? substr($validated['delivery_code'], 4)
            : $validated['delivery_code'];

        $codeResult = DB::transaction(function () use ($order, $courier, $deliveryCode, $deliveryCodes): array {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->delivery_courier_id === $courier->id, 403);
            abort_unless($courier->status === 'approved' && $lockedOrder->status === 'OUT_FOR_DELIVERY', 422, 'This parcel is not out for delivery.');
            abort_unless($lockedOrder->payment_method === 'COD', 422, 'Delivery is on hold until payment verification for this method is configured.');

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

        if (! $codeResult['valid']) {
            throw ValidationException::withMessages(['delivery_code' => $codeResult['message']]);
        }

        try {
            return DB::transaction(function () use ($request, $order, $courier, $validated, $transitions, $deliveryCodes, $deliveryCode, &$proofPath): RedirectResponse {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedOrder->delivery_courier_id === $courier->id, 403);
                abort_unless($courier->status === 'approved' && $lockedOrder->status === 'OUT_FOR_DELIVERY', 422, 'This parcel is not out for delivery.');
                abort_unless($lockedOrder->payment_method === 'COD', 422, 'Delivery is on hold until payment verification for this method is configured.');
                abort_unless($deliveryCodes->matches($lockedOrder, $deliveryCode), 422, 'The delivery code has expired or was already used.');
                if (! isset($validated['cod_collected_amount']) || $this->amountInCentavos($validated['cod_collected_amount']) !== $this->amountInCentavos((string) $lockedOrder->total_amount)) {
                    throw ValidationException::withMessages(['cod_collected_amount' => 'Enter the exact full order amount collected for cash on delivery.']);
                }
                $notes = trim('Recipient: '.$validated['recipient_confirmation'].'. '.($validated['delivery_notes'] ?? ''));
                $proofPath = $request->file('proof_file')?->store('delivery-proofs', 'private');
                if (! is_string($proofPath)) {
                    throw new RuntimeException('Delivery proof could not be stored.');
                }
                $this->recordDeliveryAttempt($lockedOrder, $courier, 'delivered', [
                    'notes' => $notes,
                    'proof_path' => $proofPath,
                ]);
                $transitions->transition($lockedOrder, $courier, OrderStatus::Delivered, 'delivered', $lockedOrder->delivery_area, $notes, [
                    'delivered_at' => now(),
                    'delivery_notes' => $notes,
                    'cod_collected_amount' => $validated['cod_collected_amount'],
                    'delivery_code_used_at' => now(),
                ]);

                return back()->with('success', "Order {$lockedOrder->order_number} marked delivered.");
            });
        } catch (Throwable $exception) {
            if (is_string($proofPath)) {
                Storage::disk('private')->delete($proofPath);
            }

            throw $exception;
        }
    }

    public function failDelivery(Request $request, Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $validated = $request->validate([
            'failure_reason' => ['required', 'in:recipient_unavailable,incorrect_address,recipient_refused,unreachable_contact,access_issue,damaged_parcel,other'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $courier = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $courier, $validated, $transitions): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->delivery_courier_id === $courier->id, 403);
            abort_unless($courier->status === 'approved' && $lockedOrder->status === 'OUT_FOR_DELIVERY', 422, 'Only a delivery in progress can be marked failed.');
            $notes = $validated['failure_reason'].(filled($validated['delivery_notes'] ?? null) ? ': '.$validated['delivery_notes'] : '');
            $attempt = $this->recordDeliveryAttempt($lockedOrder, $courier, 'failed', [
                'reason' => $validated['failure_reason'],
                'notes' => $validated['delivery_notes'] ?? null,
            ]);
            $transitions->transition($lockedOrder, $courier, OrderStatus::DeliveryFailed, 'delivery_failed', $lockedOrder->delivery_area, $notes, [
                'failed_at' => now(),
                'delivery_failure_reason' => $validated['failure_reason'],
                'delivery_notes' => $validated['delivery_notes'] ?? null,
            ]);

            $message = $attempt->attempt_no >= max(1, (int) config('logistics.maximum_delivery_attempts', 3))
                ? "Failure recorded for {$lockedOrder->order_number}. The parcel must be physically scanned back into the hub before Logistics can return it to the seller."
                : "Failure recorded for {$lockedOrder->order_number}. Logistics can review the next step.";

            return back()->with('success', $message);
        });
    }

    public function history(Request $request): View
    {
        $courier = $this->authenticatedUser();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:DELIVERED,COMPLETED,DELIVERY_FAILED,RETURN_IN_TRANSIT,RETURNED_TO_SELLER,PICKED_UP'],
        ]);
        $orders = Order::query()
            ->select([
                'id', 'order_number', 'status', 'delivery_courier_id', 'pickup_courier_id',
                'delivery_area', 'municipality', 'delivered_at', 'failed_at', 'picked_up_at',
                'delivery_failure_reason', 'delivery_notes', 'updated_at',
            ])
            ->where(function (Builder $query) use ($courier): void {
                $query->where('delivery_courier_id', $courier->id)
                    ->orWhere('pickup_courier_id', $courier->id)
                    ->orWhereHas('deliveryAssignments', fn (Builder $assignments) => $assignments->where('rider_id', $courier->id));
            })
            ->when(filled($validated['search'] ?? null), fn (Builder $query) => $query->whereRaw(
                "order_number LIKE ? ESCAPE '!'",
                [$this->orderNumberSearchPattern($validated['search'])],
            ))
            ->when(filled($validated['status'] ?? null), fn (Builder $query) => $query->where('status', $validated['status']))
            ->latest('updated_at')->paginate(20)->withQueryString();

        return view('courier.history', compact('orders'));
    }

    public function tracking(): View
    {
        $courier = $this->authenticatedUser();
        $orders = Order::where(fn (Builder $query) => $query->where('pickup_courier_id', $courier->id)->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP'])
            ->orWhere(fn (Builder $query) => $query->where('delivery_courier_id', $courier->id)->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT'])))
            ->with('trackingEvents.actor')->latest('updated_at')->paginate(20);

        return view('courier.tracking', compact('orders'));
    }

    public function earnings(): View
    {
        return view('courier.earnings');
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

    private function parcelReferenceMatches(Order $order, string $reference): bool
    {
        $code = str_starts_with($reference, 'EZP:') ? substr($reference, 4) : $reference;

        return hash_equals((string) $order->parcel_code, $code);
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

    private function amountInCentavos(string $amount): int
    {
        [$pesos, $centavos] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $pesos * 100) + (int) str_pad(substr($centavos, 0, 2), 2, '0');
    }

    private function orderNumberSearchPattern(string $search): string
    {
        return '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
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
