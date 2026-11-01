<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use App\Services\AdministrativeAuditService;
use App\Services\OrderAreaService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LogisticsAdminController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $orders = Order::query()->with(['seller', 'sortingCenter', 'deliveryCourier', 'activeTransfer'])
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('order_number', 'like', '%'.$search.'%'))
            ->whereNotIn('status', ['PLACED', 'CONFIRMED', 'PREPARING', 'CANCELLED', 'COMPLETED'])
            ->latest('id')->paginate(20)->withQueryString();
        $pendingUsers = User::query()->whereIn('role', ['courier', 'sorting_center'])->where('status', 'pending')->latest()->limit(25)->get();
        $centers = User::query()->where('role', 'sorting_center')->where('status', 'approved')->orderBy('business_name')->get();
        $riders = User::query()->where('role', 'courier')->where('status', 'approved')->orderBy('first_name')->limit(100)->get();
        $managedUsers = User::query()->whereIn('role', ['courier', 'sorting_center'])->whereIn('status', ['approved', 'suspended'])
            ->withCount(['finalDeliveries as active_parcels_count' => fn ($query) => $query->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT'])])
            ->latest('id')->paginate(25, ['*'], 'users_page');
        $areas = Area::query()->with('sortingCenter')->orderBy('name')->paginate(20, ['*'], 'areas_page');
        $exceptions = DB::table('logistics_exceptions')->where('status', 'OPEN')->latest('id')->limit(25)->get();
        $aging = Order::query()->whereIn('status', ['READY_FOR_PICKUP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT'])
            ->where('created_at', '<=', now()->subHours((int) config('commerce.parcel_alert_hours')))
            ->whereDoesntHave('trackingEvents', fn ($query) => $query->where('created_at', '>', now()->subHours((int) config('commerce.parcel_alert_hours'))))->count();

        return view('admin.logistics.index', compact('orders', 'pendingUsers', 'centers', 'riders', 'managedUsers', 'areas', 'exceptions', 'aging'));
    }

    public function assignArea(Request $request, Area $area, AdministrativeAuditService $audits): RedirectResponse
    {
        $data = $request->validate(['sorting_center_id' => ['required', 'integer', 'exists:users,id'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($request, $area, $data, $audits): void {
            $locked = Area::query()->whereKey($area->id)->lockForUpdate()->firstOrFail();
            $hub = User::query()->whereKey($data['sorting_center_id'])->firstOrFail();
            abort_unless($hub->role === 'sorting_center' && $hub->status === 'approved', 422, 'Choose an approved sorting center.');
            abort_if($locked->orders()->whereNotIn('status', ['PLACED', 'CONFIRMED', 'PREPARING', 'CANCELLED', 'COMPLETED', 'RETURNED_TO_SELLER', 'DELIVERED'])->exists(), 422, 'Finish or transfer active parcels before changing area ownership.');
            $previous = $locked->sorting_center_id;
            $locked->update(['sorting_center_id' => $hub->id]);
            $audits->record($request->user(), $locked, 'area_hub_assigned', ['sorting_center_id' => $previous], ['sorting_center_id' => $hub->id], $data['reason']);
        });

        return back()->with('success', 'Area hub ownership updated.');
    }

    public function reassignPickup(Request $request, Order $order, OrderAreaService $areas, AdministrativeAuditService $audits, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        $data = $request->validate(['pickup_courier_id' => ['required', 'integer', 'exists:users,id'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($request, $order, $data, $areas, $audits, $notifications): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'READY_FOR_PICKUP' && $locked->pickup_requested_at !== null && $locked->pickup_arrived_at === null && $locked->seller_handover_at === null && $locked->picked_up_at === null, 422, 'Only pickups awaiting rider arrival may be reassigned.');
            $rider = User::query()->whereKey($data['pickup_courier_id'])->lockForUpdate()->firstOrFail();
            $previous = $locked->pickupCourier;
            abort_unless($locked->pickup_claimed_at === null || $previous?->status === 'suspended', 422, 'An accepted pickup must be released before reassignment.');
            abort_unless($rider->role === 'courier' && $rider->status === 'approved' && $rider->id !== $locked->pickup_courier_id, 422, 'Choose a different approved rider.');
            $origin = $areas->findForAddress($locked->seller->province, $locked->seller->municipality);
            abort_unless($origin && $rider->serviceAreas()->where('areas.id', $origin->id)->exists(), 422, 'The rider must serve the seller pickup area.');
            $locked->forceFill(['pickup_courier_id' => $rider->id, 'pickup_claimed_at' => null])->save();
            $audits->record($request->user(), $locked, 'control_tower_pickup_reassigned', ['rider_id' => $previous?->id], ['rider_id' => $rider->id], $data['reason']);
            ParcelTrackingEvent::create(['order_id' => $locked->id, 'actor_id' => $request->user()->id, 'event_type' => 'pickup_reassigned', 'status' => $locked->status, 'notes' => $data['reason']]);
            $notifications->send(collect([$previous, $rider, $locked->seller])->filter()->unique('id'), new OrderWorkflowNotification($locked, 'pickup_reassigned', 'Logistics control tower reassigned this pickup before handover.'));
        });

        return back()->with('success', 'Pickup reassigned with a recorded reason.');
    }
}
