<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LogisticsController extends Controller
{
    public function dashboard(): View
    {
        $stats = [
            'inbound' => Order::where('status', 'PICKED_UP')->count(),
            'at_center' => Order::where('status', 'AT_SORTING_CENTER')->count(),
            'sorted' => Order::where('status', 'SORTED')->count(),
            'assigned' => Order::where('status', 'ASSIGNED_TO_RIDER')->count(),
            'out' => Order::where('status', 'OUT_FOR_DELIVERY')->count(),
            'delivered_today' => Order::whereIn('status', ['DELIVERED', 'COMPLETED'])->whereDate('delivered_at', today())->count(),
            'failed' => Order::where('status', 'DELIVERY_FAILED')->count(),
            'returned' => Order::where('status', 'RETURNED')->count(),
            'active_riders' => User::where('role', 'courier')->where('status', 'approved')->count(),
            'available_riders' => User::where('role', 'courier')->where('status', 'approved')->whereDoesntHave('finalDeliveries', fn (Builder $query) => $query->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY']))->count(),
        ];

        $queues = [
            'inbound' => Order::with(['seller', 'pickupCourier'])->where('status', 'PICKED_UP')->latest()->limit(8)->get(),
            'sorting' => Order::with('buyer')->where('status', 'AT_SORTING_CENTER')->latest()->limit(8)->get(),
            'dispatch' => Order::with(['buyer', 'deliveryCourier'])->where('status', 'SORTED')->latest()->limit(8)->get(),
            'failed' => Order::with(['buyer', 'deliveryCourier'])->where('status', 'DELIVERY_FAILED')->latest()->limit(5)->get(),
        ];

        return view('logistics.dashboard', compact('stats', 'queues'));
    }

    public function intake(Request $request): View
    {
        $parcels = Order::with(['seller', 'pickupCourier'])
            ->where('status', 'PICKED_UP')
            ->when($request->filled('search'), fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('order_number', 'like', '%'.$request->string('search').'%')))
            ->latest()->paginate(15)->withQueryString();

        return view('logistics.intake', compact('parcels'));
    }

    public function scan(Request $request): RedirectResponse
    {
        $validated = $request->validate(['reference' => ['required', 'string', 'max:100']]);
        $order = Order::where('order_number', $validated['reference'])->first();

        if (! $order) {
            return back()->withErrors(['reference' => 'No order matches that order or waybill reference.']);
        }

        return $this->receiveParcel($order);
    }

    public function receiveParcel(Order $order): RedirectResponse
    {
        $center = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $center): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedOrder->status !== 'PICKED_UP', 422, 'This parcel is not eligible for hub intake.');

            $lockedOrder->update([
                'status' => 'AT_SORTING_CENTER',
                'sorting_center_id' => $center->id,
                'received_at' => now(),
            ]);
            $this->recordEvent($lockedOrder, 'hub_received', $center, $center->municipality, 'Parcel received at sorting center.');

            return back()->with('success', "Parcel {$lockedOrder->order_number} received at the hub.");
        });
    }

    public function sorting(): View
    {
        $parcels = Order::where('status', 'AT_SORTING_CENTER')->with(['buyer', 'trackingEvents'])->latest()->paginate(15);
        $areas = Order::query()->select('province', 'municipality')->whereNotNull('municipality')->distinct()->orderBy('province')->orderBy('municipality')->get();

        return view('logistics.sorting', compact('parcels', 'areas'));
    }

    public function sortParcel(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate(['delivery_area' => ['required', 'string', 'max:100']]);

        return DB::transaction(function () use ($order, $validated): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedOrder->status !== 'AT_SORTING_CENTER', 422, 'Only parcels received at the hub can be sorted.');
            abort_unless($lockedOrder->municipality === $validated['delivery_area'], 422, 'Select the parcel destination municipality as its delivery area.');

            $lockedOrder->update(['delivery_area' => $validated['delivery_area'], 'status' => 'SORTED', 'sorted_at' => now()]);
            $this->recordEvent($lockedOrder, 'sorted', $this->authenticatedUser(), $validated['delivery_area'], 'Sorted to destination municipality.');

            return back()->with('success', "Parcel {$lockedOrder->order_number} sorted to {$validated['delivery_area']}.");
        });
    }

    public function dispatch(): View
    {
        $parcels = Order::whereIn('status', ['SORTED', 'ASSIGNED_TO_RIDER'])->with(['buyer', 'deliveryCourier'])->latest()->paginate(15);
        $riders = User::where('role', 'courier')->where('status', 'approved')->orderBy('assigned_area')->orderBy('first_name')->get();

        return view('logistics.dispatch', compact('parcels', 'riders'));
    }

    public function assignRider(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate(['delivery_courier_id' => ['required', 'integer', 'exists:users,id']]);
        $operator = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $validated, $operator): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedOrder->status, ['SORTED', 'ASSIGNED_TO_RIDER', 'DELIVERY_FAILED'], true), 422, 'This parcel cannot be assigned at its current stage.');

            $rider = User::whereKey($validated['delivery_courier_id'])->lockForUpdate()->firstOrFail();
            abort_unless($rider->role === 'courier' && $rider->status === 'approved', 422, 'Choose an approved rider.');
            abort_if($rider->status === 'suspended', 422, 'Suspended riders cannot receive parcels.');
            abort_unless(blank($rider->assigned_area) || $rider->assigned_area === $lockedOrder->delivery_area, 422, 'The rider is assigned to a different delivery area.');

            $lockedOrder->update(['delivery_courier_id' => $rider->id, 'status' => 'ASSIGNED_TO_RIDER', 'assigned_at' => now()]);
            $this->recordEvent($lockedOrder, 'rider_assigned', $operator, $lockedOrder->delivery_area, "Assigned to {$rider->first_name} {$rider->last_name}.");

            return back()->with('success', "Parcel {$lockedOrder->order_number} assigned to {$rider->first_name} {$rider->last_name}.");
        });
    }

    public function riders(): View
    {
        $riders = User::where('role', 'courier')->withCount([
            'finalDeliveries as active_deliveries_count' => fn (Builder $query) => $query->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY']),
            'finalDeliveries as completed_deliveries_count' => fn (Builder $query) => $query->whereIn('status', ['DELIVERED', 'COMPLETED']),
            'finalDeliveries as failed_deliveries_count' => fn (Builder $query) => $query->where('status', 'DELIVERY_FAILED'),
        ])->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'suspended' THEN 2 ELSE 3 END")->paginate(20);

        return view('logistics.riders', compact('riders'));
    }

    public function approveRider(User $user): RedirectResponse
    {
        abort_unless($user->role === 'courier' && $user->status === 'pending', 404);
        $user->update(['status' => 'approved']);

        return back()->with('success', "Courier {$user->first_name} {$user->last_name} approved.");
    }

    public function rejectRider(User $user): RedirectResponse
    {
        abort_unless($user->role === 'courier' && $user->status === 'pending', 404);
        $user->update(['status' => 'rejected']);

        return back()->with('success', 'Courier application rejected.');
    }

    public function suspendRider(User $user): RedirectResponse
    {
        abort_unless($user->role === 'courier' && $user->status === 'approved', 404);
        $user->update(['status' => 'suspended']);

        return back()->with('success', 'Rider suspended from dispatch.');
    }

    public function reactivateRider(User $user): RedirectResponse
    {
        abort_unless($user->role === 'courier' && $user->status === 'suspended', 404);
        $user->update(['status' => 'approved']);

        return back()->with('success', 'Rider reactivated.');
    }

    public function tracking(Request $request): View
    {
        $orders = Order::with(['deliveryCourier', 'pickupCourier', 'trackingEvents.actor'])
            ->whereIn('status', ['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURNED'])
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('rider'), fn (Builder $query) => $query->where('delivery_courier_id', $request->integer('rider')))
            ->when($request->filled('area'), fn (Builder $query) => $query->where('delivery_area', $request->string('area')->toString()))
            ->when($request->filled('date'), fn (Builder $query) => $query->whereDate('updated_at', $request->date('date')))
            ->when($request->filled('search'), fn (Builder $query) => $query->where('order_number', 'like', '%'.$request->string('search').'%'))
            ->latest()->paginate(20)->withQueryString();
        $riders = User::where('role', 'courier')->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $areas = Order::whereNotNull('delivery_area')->distinct()->orderBy('delivery_area')->pluck('delivery_area');

        return view('logistics.tracking', compact('orders', 'riders', 'areas'));
    }

    public function reports(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = Carbon::parse($validated['from'] ?? today()->subDays(6))->startOfDay();
        $to = Carbon::parse($validated['to'] ?? today())->endOfDay();
        $base = Order::query()->whereBetween('created_at', [$from, $to]);
        $volume = (clone $base)->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupBy('day')->orderBy('day')->get();
        $areaCounts = Order::whereIn('status', ['SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERED', 'COMPLETED'])
            ->whereBetween('created_at', [$from, $to])->selectRaw('delivery_area, COUNT(*) as total')->groupBy('delivery_area')->orderByDesc('total')->get();
        $stats = [
            'received' => (clone $base)->whereNotNull('received_at')->count(),
            'sorted' => (clone $base)->whereNotNull('sorted_at')->count(),
            'dispatched' => (clone $base)->whereNotNull('assigned_at')->count(),
            'delivered' => (clone $base)->whereNotNull('delivered_at')->count(),
            'failed' => (clone $base)->whereNotNull('failed_at')->count(),
            'backlog' => Order::whereIn('status', ['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])->count(),
        ];

        return view('logistics.reports', compact('stats', 'volume', 'areaCounts', 'from', 'to'));
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
