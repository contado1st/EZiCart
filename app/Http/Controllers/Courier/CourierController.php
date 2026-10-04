<?php

namespace App\Http\Controllers\Courier;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAttempt;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
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
        $myDeliveryAssignments = Order::where('delivery_courier_id', $courier->id)->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])->with(['buyer', 'items'])->latest()->paginate(10, ['*'], 'deliveries');
        $myFailedDeliveries = Order::where('delivery_courier_id', $courier->id)->where('status', 'DELIVERY_FAILED')->with('deliveryAttempts')->latest('failed_at')->get();
        $stats = [
            'claimed_pickups' => $claimedPickups->count(),
            'in_transit_hub' => $myActivePickups->count(),
            'assigned_delivery' => Order::where('delivery_courier_id', $courier->id)->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])->count(),
            'completed_today' => Order::where('delivery_courier_id', $courier->id)->whereIn('status', ['DELIVERED', 'COMPLETED'])->whereBetween('delivered_at', [$todayStart, $tomorrowStart])->count(),
            'failed_today' => Order::where('delivery_courier_id', $courier->id)->where('status', 'DELIVERY_FAILED')->whereBetween('failed_at', [$todayStart, $tomorrowStart])->count(),
        ];

        return view('courier.dashboard', compact('courier', 'availablePickups', 'claimedPickups', 'myActivePickups', 'myDeliveryAssignments', 'myFailedDeliveries', 'stats'));
    }

    public function showOrder(Order $order): View
    {
        $courier = $this->authenticatedUser();
        abort_unless($order->pickup_courier_id === $courier->id || $order->delivery_courier_id === $courier->id, 403);
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
            $lockedOrder->update(['pickup_claimed_at' => now()]);
            $this->recordEvent($lockedOrder, 'pickup_accepted', $courier, $lockedOrder->seller?->municipality, 'Rider accepted the Logistics pickup assignment.');

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

            $lockedOrder->update(['pickup_courier_id' => null]);
            $this->recordEvent(
                $lockedOrder,
                'pickup_declined',
                $courier,
                $lockedOrder->seller?->municipality,
                filled($validated['reason'] ?? null) ? $validated['reason'] : 'Rider declined the pickup assignment.',
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
                $lockedOrder->update(['pickup_arrived_at' => now()]);
                $this->recordEvent($lockedOrder, 'pickup_arrived', $courier, $lockedOrder->seller?->municipality, 'Rider arrived and requested seller handover confirmation.');

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
            $transitions->transition($lockedOrder, $courier, OrderStatus::OutForDelivery, 'out_for_delivery', $lockedOrder->delivery_area, 'Rider collected parcel from the hub.', ['out_for_delivery_at' => now()]);

            return back()->with('success', "Order {$lockedOrder->order_number} is out for delivery.");
        });
    }

    public function completeDelivery(Request $request, Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_confirmation' => ['required', 'string', 'max:120'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'cod_collected_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);
        $courier = $this->authenticatedUser();

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
            $attemptNo = ((int) DeliveryAttempt::where('order_id', $lockedOrder->id)->max('attempt_no')) + 1;
            DeliveryAttempt::create([
                'order_id' => $lockedOrder->id,
                'rider_id' => $courier->id,
                'attempt_no' => $attemptNo,
                'outcome' => 'delivered',
                'notes' => $notes,
                'proof_path' => $request->file('proof_file')?->store('delivery-proofs', 'private'),
                'attempted_at' => now(),
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
            $attemptNo = ((int) DeliveryAttempt::where('order_id', $lockedOrder->id)->max('attempt_no')) + 1;
            DeliveryAttempt::create([
                'order_id' => $lockedOrder->id,
                'rider_id' => $courier->id,
                'attempt_no' => $attemptNo,
                'outcome' => 'failed',
                'reason' => $validated['failure_reason'],
                'notes' => $validated['delivery_notes'] ?? null,
                'attempted_at' => now(),
            ]);
            $transitions->transition($lockedOrder, $courier, OrderStatus::DeliveryFailed, 'delivery_failed', $lockedOrder->delivery_area, $notes, [
                'failed_at' => now(),
                'delivery_failure_reason' => $validated['failure_reason'],
                'delivery_notes' => $validated['delivery_notes'] ?? null,
            ]);

            if ($attemptNo >= max(1, (int) config('logistics.maximum_delivery_attempts', 3))) {
                $transitions->transition($lockedOrder->refresh(), $courier, OrderStatus::ReturnInTransit, 'return_in_transit', $lockedOrder->delivery_area, 'Maximum delivery attempts reached; parcel is returning to sender.');
            }

            return back()->with('success', "Failure recorded for {$lockedOrder->order_number}. Logistics can review the next step.");
        });
    }

    public function history(Request $request): View
    {
        $courier = $this->authenticatedUser();
        $orders = Order::where(fn (Builder $query) => $query->where('delivery_courier_id', $courier->id)->orWhere('pickup_courier_id', $courier->id))
            ->with(['seller', 'buyer'])
            ->when($request->filled('search'), fn (Builder $query) => $query->where('order_number', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->latest('updated_at')->paginate(20)->withQueryString();

        return view('courier.history', compact('orders'));
    }

    public function tracking(): View
    {
        $courier = $this->authenticatedUser();
        $orders = Order::where(fn (Builder $query) => $query->where('pickup_courier_id', $courier->id)->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP'])
            ->orWhere(fn (Builder $query) => $query->where('delivery_courier_id', $courier->id)->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED'])))
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
}
