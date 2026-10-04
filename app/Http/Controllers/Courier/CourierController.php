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
use App\Services\OrderTransitionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourierController extends Controller
{
    public function dashboard(): View
    {
        $courier = $this->authenticatedUser();
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
            'failed_today' => Order::where('delivery_courier_id', $courier->id)->where('status', 'DELIVERY_FAILED')->whereBetween('failed_at', [$todayStart, $tomorrowStart])->count(),
        ];

        return view('courier.dashboard', compact('courier', 'availablePickups', 'claimedPickups', 'myActivePickups', 'myReturns', 'myDeliveryAssignments', 'myFailedDeliveries', 'stats'));
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

    public function claimPickup(Order $order): RedirectResponse
    {
        $courier = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $courier): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($courier->status === 'approved', 403, 'Your rider account is not approved for pickups.');
            abort_unless($lockedOrder->pickup_courier_id === $courier->id, 403);
            abort_unless($lockedOrder->status === 'READY_FOR_PICKUP' && $lockedOrder->pickup_claimed_at === null, 422, 'This pickup has already been accepted or is no longer available.');
            $lockedOrder->forceFill(['pickup_claimed_at' => now()])->save();
            $this->recordEvent($lockedOrder, 'pickup_accepted', $courier, $lockedOrder->seller?->municipality, 'Rider accepted the Logistics pickup assignment.');
            $lockedOrder->seller?->notify(new OrderWorkflowNotification($lockedOrder, 'pickup_accepted', 'The assigned rider accepted the pickup request.'));

            return back()->with('success', "Pickup {$lockedOrder->order_number} added to your route.");
        });
    }

    public function declinePickup(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $courier = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $courier, $validated): RedirectResponse {
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
            $lockedOrder->seller?->notify(new OrderWorkflowNotification($lockedOrder, 'pickup_declined', 'The assigned rider declined the pickup. Logistics will reassign it.'));
            User::query()->where('role', 'sorting_center')->where('status', 'approved')->each(
                fn (User $operator) => $operator->notify(new OrderWorkflowNotification($lockedOrder, 'pickup_declined', 'A rider declined a pickup and it is back in the Logistics queue.')),
            );

            return back()->with('success', "Pickup {$lockedOrder->order_number} returned to the Logistics queue.");
        });
    }

    public function confirmPickup(Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $courier = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $courier, $transitions): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->pickup_courier_id === $courier->id, 403);
            abort_unless($courier->status === 'approved' && $lockedOrder->status === 'READY_FOR_PICKUP' && $lockedOrder->pickup_claimed_at !== null, 422, 'This pickup cannot be confirmed.');
            if ($lockedOrder->pickup_arrived_at === null) {
                $lockedOrder->forceFill(['pickup_arrived_at' => now()])->save();
                $this->recordEvent($lockedOrder, 'pickup_arrived', $courier, $lockedOrder->seller?->municipality, 'Rider arrived and requested seller handover confirmation.');
                $lockedOrder->seller?->notify(new OrderWorkflowNotification($lockedOrder, 'pickup_arrived', 'The rider arrived. Confirm the parcel handover when ready.'));

                return back()->with('success', 'Arrival recorded. Wait for the seller to confirm parcel handover, then confirm possession.');
            }

            abort_unless($lockedOrder->seller_handover_at !== null, 422, 'The seller must confirm parcel handover before pickup can be completed.');
            $transitions->transition($lockedOrder, $courier, OrderStatus::PickedUp, 'picked_up', $lockedOrder->seller?->municipality, 'Parcel collected from seller; traveling to sorting center.', ['picked_up_at' => now()]);

            return back()->with('success', "Parcel {$lockedOrder->order_number} collected. Bring it to the sorting center for handoff.");
        });
    }

    public function startDelivery(Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $courier = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $courier, $transitions): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->delivery_courier_id === $courier->id, 403);
            abort_unless($courier->status === 'approved' && $lockedOrder->status === 'ASSIGNED_TO_RIDER', 422, 'This parcel cannot be started for delivery.');
            $scheduledAttempt = DeliveryAttempt::query()
                ->where('order_id', $lockedOrder->id)
                ->where('rider_id', $courier->id)
                ->where('outcome', 'scheduled')
                ->lockForUpdate()
                ->first();
            abort_unless($scheduledAttempt?->scheduled_at === null || $scheduledAttempt->scheduled_at->lte(now()), 422, 'Wait until the scheduled retry time before starting delivery.');
            $transitions->transition($lockedOrder, $courier, OrderStatus::OutForDelivery, 'out_for_delivery', $lockedOrder->delivery_area, 'Rider collected parcel from the hub.', ['out_for_delivery_at' => now()]);

            return back()->with('success', "Order {$lockedOrder->order_number} is out for delivery.");
        });
    }

    public function confirmReturnDelivery(Order $order): RedirectResponse
    {
        $courier = $this->authenticatedUser();
        abort_unless($order->delivery_courier_id === $courier->id, 403);

        DB::transaction(function () use ($order, $courier): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->delivery_courier_id === $courier->id, 403);
            abort_unless($courier->status === 'approved' && $lockedOrder->status === OrderStatus::ReturnInTransit->value, 422, 'This parcel is not on an active return to seller.');
            abort_unless($lockedOrder->return_handed_to_seller_at === null, 422, 'The seller handoff has already been recorded.');

            $lockedOrder->forceFill(['return_handed_to_seller_at' => now()])->save();
            $seller = $lockedOrder->seller;
            ParcelTrackingEvent::query()->create([
                'order_id' => $lockedOrder->id,
                'actor_id' => $courier->id,
                'event_type' => 'return_handed_to_seller',
                'status' => $lockedOrder->status,
                'location' => $seller?->municipality,
                'notes' => 'Courier recorded handing the return parcel to the seller; seller receipt confirmation is pending.',
            ]);
            $seller?->notify(new OrderWorkflowNotification($lockedOrder, 'return_handed_to_seller', 'The courier recorded a return handoff. Confirm receipt only after you physically receive the parcel.'));

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

        return back()->with('success', "Return handoff for {$order->order_number} recorded. The seller must confirm receipt.");
    }

    public function completeDelivery(Request $request, Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $courier = $this->authenticatedUser();
        abort_unless($order->delivery_courier_id === $courier->id, 403);

        $validated = $request->validate([
            'recipient_confirmation' => ['required', 'string', 'max:120'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'cod_collected_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        return DB::transaction(function () use ($request, $order, $courier, $validated, $transitions): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->delivery_courier_id === $courier->id, 403);
            abort_unless($courier->status === 'approved' && $lockedOrder->status === 'OUT_FOR_DELIVERY', 422, 'This parcel is not out for delivery.');
            if ($lockedOrder->payment_method === 'COD') {
                if (! isset($validated['cod_collected_amount']) || $this->amountInCentavos($validated['cod_collected_amount']) !== $this->amountInCentavos((string) $lockedOrder->total_amount)) {
                    throw ValidationException::withMessages(['cod_collected_amount' => 'Enter the exact full order amount collected for cash on delivery.']);
                }
            }
            $notes = trim('Recipient: '.$validated['recipient_confirmation'].'. '.($validated['delivery_notes'] ?? ''));
            $attempt = $this->recordDeliveryAttempt($lockedOrder, $courier, 'delivered', [
                'notes' => $notes,
                'proof_path' => $request->file('proof_file')?->store('delivery-proofs', 'private'),
            ]);
            $transitions->transition($lockedOrder, $courier, OrderStatus::Delivered, 'delivered', $lockedOrder->delivery_area, $notes, [
                'delivered_at' => now(),
                'delivery_notes' => $notes,
                'cod_collected_amount' => $validated['cod_collected_amount'] ?? null,
            ]);

            return back()->with('success', "Order {$lockedOrder->order_number} marked delivered.");
        });
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

            if ($attempt->attempt_no >= max(1, (int) config('logistics.maximum_delivery_attempts', 3))) {
                $transitions->transition($lockedOrder->refresh(), $courier, OrderStatus::ReturnInTransit, 'return_in_transit', $lockedOrder->delivery_area, 'Maximum delivery attempts reached; parcel is returning to sender.');
            }

            return back()->with('success', "Failure recorded for {$lockedOrder->order_number}. Logistics can review the next step.");
        });
    }

    public function history(Request $request): View
    {
        $courier = $this->authenticatedUser();
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
            ->when($request->filled('search'), fn (Builder $query) => $query->where('order_number', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
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

    private function amountInCentavos(string $amount): int
    {
        [$pesos, $centavos] = array_pad(explode('.', $amount, 2), 2, '');

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
