<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\HubOrderScope;
use App\Services\ParcelLoadService;
use Illuminate\Http\Request;

class LoadPlanningController extends Controller
{
    public function __invoke(Request $request, HubOrderScope $hubs, ParcelLoadService $loads)
    {
        $actor = $request->user();
        $query = match ($actor->role) {
            'seller' => Order::where('seller_id', $actor->id),
            'courier' => $loads->activeLoads($actor->id),
            'sorting_center' => $hubs->query($actor),
            'logistics_admin' => Order::query(),
        };
        $orders = $query->whereNotIn('status', ['CANCELLED', 'COMPLETED', 'DELIVERED', 'RETURNED_TO_SELLER'])
            ->with(['seller', 'pickupCourier', 'deliveryCourier', 'activeTransfer.carrier'])->latest()->paginate(30);
        $riders = User::where('role', 'courier')->where('status', 'approved');
        if ($actor->role === 'courier') {
            $riders->whereKey($actor->id);
        } elseif ($actor->role === 'seller') {
            $riders->whereIn('id', $orders->getCollection()->flatMap(fn ($o) => [$o->pickup_courier_id, $o->delivery_courier_id])->filter()->unique());
        } elseif ($actor->role === 'sorting_center') {
            $single = User::where('role', 'sorting_center')->where('status', 'approved')->limit(2)->count() < 2;
            $riders->where(fn ($q) => $q->where('logistics_owner_id', $actor->id)->orWhereHas('serviceAreas', fn ($a) => $a->where('sorting_center_id', $actor->id))->when($single, fn ($q) => $q->orWhereNull('logistics_owner_id')));
        }
        $riders = $riders->orderBy('first_name')->get();
        $loadSummaries = $loads->summaries($riders);

        return view('logistics.load-planning', compact('orders', 'riders', 'loadSummaries', 'actor'));
    }
}
