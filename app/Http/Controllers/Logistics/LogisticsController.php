<?php

namespace App\Http\Controllers\Logistics;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Services\OrderAreaService;
use App\Services\OrderTransitionService;
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
            'returned' => Order::whereIn('status', ['RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'])->count(),
            'active_riders' => User::where('role', 'courier')->where('status', 'approved')->count(),
            'available_riders' => User::where('role', 'courier')->where('status', 'approved')->whereDoesntHave('finalDeliveries', fn (Builder $query) => $query->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED']))->count(),
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

    public function pickupRequests(): View
    {
        $orders = Order::query()->with(['seller', 'items'])
            ->where('status', 'READY_FOR_PICKUP')
            ->whereNotNull('pickup_requested_at')
            ->whereNull('pickup_courier_id')
            ->latest()->paginate(20);
        $riders = User::query()->where('role', 'courier')->where('status', 'approved')->orderBy('first_name')->get();

        return view('logistics.pickup-requests', compact('orders', 'riders'));
    }

    public function assignPickup(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate(['pickup_courier_id' => ['required', 'integer', 'exists:users,id']]);
        $operator = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $validated, $operator): RedirectResponse {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->status === 'READY_FOR_PICKUP' && $lockedOrder->pickup_requested_at !== null && $lockedOrder->pickup_courier_id === null, 422, 'This pickup request is no longer available.');
            $rider = User::query()->whereKey($validated['pickup_courier_id'])->lockForUpdate()->firstOrFail();
            abort_unless($rider->role === 'courier' && $rider->status === 'approved', 422, 'Choose an approved rider.');

            $lockedOrder->update(['pickup_courier_id' => $rider->id]);
            ParcelTrackingEvent::create([
                'order_id' => $lockedOrder->id,
                'actor_id' => $operator->id,
                'event_type' => 'pickup_assigned',
                'status' => $lockedOrder->status,
                'location' => $lockedOrder->seller?->municipality,
                'notes' => "Pickup assigned to {$rider->first_name} {$rider->last_name}.",
            ]);

            return back()->with('success', "Pickup for {$lockedOrder->order_number} assigned to {$rider->first_name} {$rider->last_name}.");
        });
    }

    public function scan(Request $request, OrderTransitionService $transitions): RedirectResponse
    {
        $validated = $request->validate(['reference' => ['required', 'string', 'max:100']]);
        $order = Order::where('order_number', $validated['reference'])->first();

        if (! $order) {
            return back()->withErrors(['reference' => 'The parcel reference could not be processed.']);
        }

        return $this->receiveParcel($order, $transitions);
    }

    public function receiveParcel(Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $center = $this->authenticatedUser();

        $transitions->transition(
            $order,
            $center,
            OrderStatus::AtSortingCenter,
            'hub_received',
            $center->municipality,
            'Parcel received at sorting center.',
            [
                'sorting_center_id' => $center->id,
                'received_at' => now(),
            ],
        );

        return back()->with('success', "Parcel {$order->order_number} received at the hub.");
    }

    public function sorting(): View
    {
        $parcels = Order::where('status', 'AT_SORTING_CENTER')->with(['buyer', 'trackingEvents'])->latest()->paginate(15);

        return view('logistics.sorting', compact('parcels'));
    }

    public function sortParcel(Order $order, OrderTransitionService $transitions, OrderAreaService $areas): RedirectResponse
    {
        $area = $areas->resolve($order);
        $transitions->transition($order, $this->authenticatedUser(), OrderStatus::Sorted, 'sorted', $area->name, "Sorted to destination area {$area->code}.", [
            'destination_area_id' => $area->id,
            'delivery_area' => $area->name,
            'sorted_at' => now(),
        ]);

        return back()->with('success', "Parcel {$order->order_number} sorted to {$area->name}.");
    }

    public function dispatch(): View
    {
        $parcels = Order::whereIn('status', ['SORTED', 'ASSIGNED_TO_RIDER', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT'])->with(['buyer', 'deliveryCourier', 'destinationArea'])->latest()->paginate(15);
        $riders = User::where('role', 'courier')->where('status', 'approved')->with('serviceAreas')->withCount([
            'finalDeliveries as active_deliveries_count' => fn (Builder $query) => $query->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY']),
            'finalDeliveries as failed_deliveries_count' => fn (Builder $query) => $query->where('status', 'DELIVERY_FAILED'),
            'finalDeliveries as capacity_load_count' => fn (Builder $query) => $query->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED']),
        ])->orderBy('first_name')->get();
        $maxActiveDeliveries = max(1, (int) config('logistics.maximum_active_deliveries_per_rider', 10));
        $suggestedRiders = [];
        foreach ($parcels as $parcel) {
            $suggestedRiders[$parcel->id] = $riders
                ->filter(fn (User $rider): bool => $parcel->destination_area_id !== null
                    && $rider->capacity_load_count < $maxActiveDeliveries
                    && $rider->serviceAreas->contains('id', $parcel->destination_area_id))
                ->sortBy('failed_deliveries_count')
                ->sortBy('active_deliveries_count')
                ->first();
        }

        return view('logistics.dispatch', compact('parcels', 'riders', 'suggestedRiders', 'maxActiveDeliveries'));
    }

    public function assignRider(Request $request, Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $validated = $request->validate(['delivery_courier_id' => ['required', 'integer', 'exists:users,id']]);
        $operator = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $validated, $operator, $transitions): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedOrder->status, ['SORTED', 'ASSIGNED_TO_RIDER', 'DELIVERY_FAILED'], true), 422, 'This parcel cannot be assigned at its current stage.');

            $rider = User::whereKey($validated['delivery_courier_id'])->lockForUpdate()->firstOrFail();
            abort_unless($rider->role === 'courier' && $rider->status === 'approved', 422, 'Choose an approved rider.');
            abort_if($rider->status === 'suspended', 422, 'Suspended riders cannot receive parcels.');
            abort_unless($lockedOrder->destination_area_id !== null, 422, 'Sort this parcel into a destination area before dispatch.');
            abort_unless($rider->serviceAreas()->whereKey($lockedOrder->destination_area_id)->exists(), 422, 'The rider is not assigned to this destination area.');
            $activeDeliveries = Order::query()
                ->where('delivery_courier_id', $rider->id)
                ->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED'])
                ->where('id', '!=', $lockedOrder->id)
                ->count();
            abort_if($activeDeliveries >= max(1, (int) config('logistics.maximum_active_deliveries_per_rider', 10)), 422, 'This rider is at the active delivery capacity limit.');

            $transitions->transition($lockedOrder, $operator, OrderStatus::AssignedToRider, 'rider_assigned', $lockedOrder->delivery_area, "Assigned to {$rider->first_name} {$rider->last_name}.", [
                'delivery_courier_id' => $rider->id,
                'assigned_at' => now(),
                'failed_at' => null,
                'delivery_failure_reason' => null,
            ]);

            return back()->with('success', "Parcel {$lockedOrder->order_number} assigned to {$rider->first_name} {$rider->last_name}.");
        });
    }

    public function riders(): View
    {
        $riders = User::where('role', 'courier')->with('serviceAreas')->withCount([
            'finalDeliveries as active_deliveries_count' => fn (Builder $query) => $query->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY']),
            'finalDeliveries as completed_deliveries_count' => fn (Builder $query) => $query->whereIn('status', ['DELIVERED', 'COMPLETED']),
            'finalDeliveries as failed_deliveries_count' => fn (Builder $query) => $query->where('status', 'DELIVERY_FAILED'),
        ])->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'suspended' THEN 2 ELSE 3 END")->paginate(20);

        $areas = Area::query()->where('is_active', true)->orderBy('name')->get();

        return view('logistics.riders', compact('riders', 'areas'));
    }

    public function updateRiderAreas(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'courier', 404);
        $validated = $request->validate([
            'area_ids' => ['nullable', 'array'],
            'area_ids.*' => ['integer', 'distinct', 'exists:areas,id'],
        ]);
        $areaIds = array_values(array_unique($validated['area_ids'] ?? []));
        $activeIds = Area::query()->where('is_active', true)->whereKey($areaIds)->pluck('id')->all();
        abort_unless(count($activeIds) === count($areaIds), 422, 'Select active service areas only.');

        DB::transaction(function () use ($user, $activeIds): void {
            DB::table('area_user')->where('user_id', $user->id)->update(['is_active' => false, 'is_primary' => false, 'updated_at' => now()]);
            foreach ($activeIds as $index => $areaId) {
                DB::table('area_user')->updateOrInsert(
                    ['user_id' => $user->id, 'area_id' => $areaId],
                    ['is_active' => true, 'is_primary' => $index === 0, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        });

        return back()->with('success', 'Rider service areas updated. The first selected area is primary.');
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

    public function returnParcel(Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $operator = $this->authenticatedUser();

        $transitions->transition($order, $operator, OrderStatus::ReturnInTransit, 'return_in_transit', $operator->municipality, 'Logistics initiated return to seller.');

        return back()->with('success', "Order {$order->order_number} marked for return handling.");
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
        $validated = $request->validate([
            'status' => ['nullable', 'in:PICKED_UP,AT_SORTING_CENTER,SORTED,ASSIGNED_TO_RIDER,OUT_FOR_DELIVERY,DELIVERY_FAILED,RETURN_IN_TRANSIT,RETURNED_TO_SELLER'],
            'rider' => ['nullable', 'integer', 'exists:users,id'],
            'area' => ['nullable', 'integer', 'exists:areas,id'],
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $orders = Order::with(['deliveryCourier', 'pickupCourier', 'trackingEvents.actor'])
            ->whereIn('status', ['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'])
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['rider'] ?? null, fn (Builder $query, int $rider) => $query->where('delivery_courier_id', $rider))
            ->when($validated['area'] ?? null, fn (Builder $query, int $area) => $query->where('destination_area_id', $area))
            ->when($validated['date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('updated_at', $date))
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where('order_number', 'like', '%'.$search.'%'))
            ->latest()->paginate(20)->withQueryString();
        $riders = User::where('role', 'courier')->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $areas = Area::query()->orderBy('name')->get(['id', 'name']);

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
        $areaCounts = Order::query()
            ->select('destination_area_id')
            ->selectRaw('COUNT(*) as total')
            ->with('destinationArea')
            ->whereNotNull('destination_area_id')
            ->whereIn('status', ['SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERED', 'COMPLETED'])
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('destination_area_id')
            ->orderByDesc('total')
            ->get();
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
}
