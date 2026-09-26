<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CourierController extends Controller
{
    public function dashboard(): View
    {
        $courier = $this->authenticatedUser();
        $availablePickups = Order::where('status', 'READY_FOR_PICKUP')->whereNull('pickup_courier_id')->with(['seller', 'items'])->latest()->paginate(8, ['*'], 'pickups');
        $claimedPickups = Order::where('pickup_courier_id', $courier->id)->where('status', 'READY_FOR_PICKUP')->with('seller')->latest()->get();
        $myActivePickups = Order::where('pickup_courier_id', $courier->id)->where('status', 'PICKED_UP')->with('seller')->latest()->get();
        $myDeliveryAssignments = Order::where('delivery_courier_id', $courier->id)->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])->with(['buyer', 'items'])->latest()->paginate(10, ['*'], 'deliveries');
        $stats = [
            'claimed_pickups' => $claimedPickups->count(),
            'in_transit_hub' => $myActivePickups->count(),
            'assigned_delivery' => Order::where('delivery_courier_id', $courier->id)->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])->count(),
            'completed_today' => Order::where('delivery_courier_id', $courier->id)->whereIn('status', ['DELIVERED', 'COMPLETED'])->whereDate('delivered_at', today())->count(),
            'failed_today' => Order::where('delivery_courier_id', $courier->id)->where('status', 'DELIVERY_FAILED')->whereDate('failed_at', today())->count(),
        ];

        return view('courier.dashboard', compact('courier', 'availablePickups', 'claimedPickups', 'myActivePickups', 'myDeliveryAssignments', 'stats'));
    }

    public function claimPickup(Order $order): RedirectResponse
    {
        $courier = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $courier): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($courier->status === 'approved', 403, 'Your rider account is not approved for pickups.');
            abort_unless($lockedOrder->status === 'READY_FOR_PICKUP' && $lockedOrder->pickup_courier_id === null, 422, 'This pickup has already been claimed or is no longer available.');
            $lockedOrder->update(['pickup_courier_id' => $courier->id, 'pickup_claimed_at' => now()]);
            $this->recordEvent($lockedOrder, 'pickup_claimed', $courier, $lockedOrder->seller?->municipality, 'Rider accepted the pickup request.');

            return back()->with('success', "Pickup {$lockedOrder->order_number} added to your route.");
        });
    }

    public function confirmPickup(Order $order): RedirectResponse
    {
        $courier = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $courier): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->pickup_courier_id === $courier->id, 403);
            abort_unless($courier->status === 'approved' && $lockedOrder->status === 'READY_FOR_PICKUP' && $lockedOrder->pickup_claimed_at !== null, 422, 'This pickup cannot be confirmed.');
            $lockedOrder->update(['status' => 'PICKED_UP', 'picked_up_at' => now()]);
            $this->recordEvent($lockedOrder, 'picked_up', $courier, $lockedOrder->seller?->municipality, 'Parcel collected from seller; traveling to sorting center.');

            return back()->with('success', "Parcel {$lockedOrder->order_number} collected. Bring it to the sorting center for handoff.");
        });
    }

    public function startDelivery(Order $order): RedirectResponse
    {
        $courier = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $courier): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->delivery_courier_id === $courier->id, 403);
            abort_unless($courier->status === 'approved' && $lockedOrder->status === 'ASSIGNED_TO_RIDER', 422, 'This parcel cannot be started for delivery.');
            $lockedOrder->update(['status' => 'OUT_FOR_DELIVERY', 'out_for_delivery_at' => now()]);
            $this->recordEvent($lockedOrder, 'out_for_delivery', $courier, $lockedOrder->delivery_area, 'Rider collected parcel from the hub.');

            return back()->with('success', "Order {$lockedOrder->order_number} is out for delivery.");
        });
    }

    public function completeDelivery(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_confirmation' => ['required', 'string', 'max:120'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'cod_collected_amount' => ['required_if:payment_method,COD', 'nullable', 'numeric', 'min:0'],
        ]);
        $courier = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $courier, $validated): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->delivery_courier_id === $courier->id, 403);
            abort_unless($courier->status === 'approved' && $lockedOrder->status === 'OUT_FOR_DELIVERY', 422, 'This parcel is not out for delivery.');
            if ($lockedOrder->payment_method === 'COD') {
                abort_unless((float) $validated['cod_collected_amount'] === (float) $lockedOrder->total_amount, 422, 'Confirm the full order amount collected for cash on delivery.');
            }
            $lockedOrder->update([
                'status' => 'DELIVERED',
                'delivered_at' => now(),
                'delivery_notes' => trim('Recipient: '.$validated['recipient_confirmation'].'. '.($validated['delivery_notes'] ?? '')),
                'cod_collected_amount' => $validated['cod_collected_amount'] ?? null,
            ]);
            $this->recordEvent($lockedOrder, 'delivered', $courier, $lockedOrder->delivery_area, 'Delivered to '.$validated['recipient_confirmation'].'.');

            return back()->with('success', "Order {$lockedOrder->order_number} marked delivered.");
        });
    }

    public function failDelivery(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'failure_reason' => ['required', 'in:recipient_unavailable,incorrect_address,recipient_refused,unreachable_contact,access_issue,damaged_parcel,other'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $courier = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $courier, $validated): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->delivery_courier_id === $courier->id, 403);
            abort_unless($courier->status === 'approved' && $lockedOrder->status === 'OUT_FOR_DELIVERY', 422, 'Only a delivery in progress can be marked failed.');
            $lockedOrder->update([
                'status' => 'DELIVERY_FAILED',
                'failed_at' => now(),
                'delivery_failure_reason' => $validated['failure_reason'],
                'delivery_notes' => $validated['delivery_notes'] ?? null,
            ]);
            $this->recordEvent($lockedOrder, 'delivery_failed', $courier, $lockedOrder->delivery_area, $validated['failure_reason'].(filled($validated['delivery_notes'] ?? null) ? ': '.$validated['delivery_notes'] : ''));

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
}
