<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\ReturnPickupService;
use Illuminate\Http\Request;

class ReturnPickupController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        $orders = Order::whereHas('dispute', fn ($query) => $query->whereIn('status', ['REFUND_APPROVED', 'REPLACEMENT_APPROVED']))
            ->whereIn('status', ['DELIVERED', 'COMPLETED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'])
            ->when($actor->role === 'buyer', fn ($query) => $query->where('buyer_id', $actor->id))
            ->when($actor->role === 'seller', fn ($query) => $query->where('seller_id', $actor->id))
            ->when($actor->role === 'sorting_center', fn ($query) => $query->where('sorting_center_id', $actor->id))
            ->when($actor->role === 'courier', fn ($query) => $query->where(fn ($q) => $q->whereHas('returnPickupAssignment', fn ($assignment) => $assignment->where('rider_id', $actor->id))
                ->orWhere(fn ($return) => $return->where('status', 'RETURN_IN_TRANSIT')->where('delivery_courier_id', $actor->id))))
            ->with(['buyer', 'seller', 'sortingCenter', 'dispute', 'returnPickupAssignment.rider'])->oldest('id')->paginate(20);
        $riders = collect();
        if ($actor->isLogisticsAdmin() || $actor->role === 'sorting_center') {
            $riders = User::where('role', 'courier')->where('status', 'approved')->where('hub_transfer_only', false)
                ->when($actor->role === 'sorting_center', fn ($query) => $query->where(fn ($q) => $q->where('logistics_owner_id', $actor->id)->orWhereHas('serviceAreas', fn ($area) => $area->where('sorting_center_id', $actor->id))))
                ->orderBy('first_name')->get();
        }

        return view('logistics.return-pickups', compact('orders', 'riders', 'actor'));
    }

    public function assign(Request $request, Order $order, ReturnPickupService $service)
    {
        $data = $request->validate(['rider_id' => ['required', 'integer', 'exists:users,id']]);
        $service->assign($order, $request->user(), User::findOrFail($data['rider_id']));

        return back()->with('success', 'Return pickup assigned. Await rider acceptance.');
    }

    public function accept(Request $request, Order $order, ReturnPickupService $service)
    {
        $service->accept($order, $request->user());

        return back()->with('success', 'Return pickup accepted. Wait for buyer handover confirmation.');
    }

    public function handover(Request $request, Order $order, ReturnPickupService $service)
    {
        $service->handover($order, $request->user());

        return back()->with('success', 'Handover confirmed. The rider must scan the parcel to confirm possession.');
    }

    public function collect(Request $request, Order $order, ReturnPickupService $service)
    {
        $data = $request->validate(['parcel_reference' => ['required', 'string', 'max:100']]);
        $service->collect($order, $request->user(), $data['parcel_reference']);

        return back()->with('success', 'Return parcel collected. Complete the existing return handoff to the seller.');
    }
}
