<?php

namespace App\Http\Controllers;

use App\Models\DispatchCycle;
use App\Models\DispatchLocation;
use App\Models\DispatchOffer;
use App\Models\Order;
use App\Models\User;
use App\Services\DispatchLocationService;
use App\Services\DispatchOfferService;
use App\Services\HubOrderScope;
use Illuminate\Http\Request;

class DispatchController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        $status = $request->validate(['status' => ['nullable', 'in:OFFERING,UNASSIGNED,ACCEPTED,CANCELLED']])['status'] ?? null;
        $point = DispatchLocation::where('user_id', $actor->id)->first();
        $jobs = $actor->role === 'sorting_center' ? app(HubOrderScope::class)->query($actor)->whereIn('status', ['READY_FOR_PICKUP', 'SORTED'])->with('seller')->latest('id')->paginate(25, ['*'], 'jobs_page')->withQueryString() : null;
        $cycles = $actor->role !== 'courier' ? DispatchCycle::when($actor->role === 'sorting_center', fn ($q) => $q->where('hub_id', $actor->id))->when($status, fn ($q) => $q->where('status', $status))->with(['order', 'offers.rider', 'hub'])->latest('id')->paginate(25, ['*'], 'cycles_page')->withQueryString() : null;
        $offers = $actor->role === 'courier' ? DispatchOffer::where('rider_id', $actor->id)->with(['order', 'cycle.hub'])->latest('id')->paginate(25)->withQueryString() : null;
        $pinTargets = $actor->role === 'sorting_center' ? User::whereIn('id', $jobs->getCollection()->pluck('seller_id')->push($actor->id)->unique())->whereIn('role', ['seller', 'sorting_center'])->get() : ($actor->isLogisticsAdmin() ? User::where('role', 'sorting_center')->where('status', 'approved')->orderBy('id')->limit(1000)->get() : collect());

        return response()->view('logistics.automatic-dispatch', compact('actor', 'point', 'jobs', 'cycles', 'offers', 'pinTargets', 'status'))->header('Cache-Control', 'private, no-store');
    }

    public function position(Request $request, DispatchLocationService $locations)
    {
        $data = $request->validate(['latitude' => ['required', 'numeric'], 'longitude' => ['required', 'numeric'], 'accuracy_meters' => ['required', 'numeric'], 'captured_at' => ['required', 'date']]);
        $locations->rider($request->user(), $data);

        return back()->with('success', 'Device-reported position updated. It is used for offers for five minutes and removed after 24 hours. This does not change your availability.');
    }

    public function pin(Request $request, DispatchLocationService $locations)
    {
        $data = $request->validate(['user_id' => ['required', 'integer'], 'latitude' => ['required', 'numeric'], 'longitude' => ['required', 'numeric'], 'reason' => ['required', 'string', 'min:10', 'max:500']]);
        $locations->pin($request->user(), User::findOrFail($data['user_id']), $data);

        return back()->with('success', 'Pickup pin confirmed for the current approved address. Address or pin changes invalidate existing automatic cycles.');
    }

    public function start(Request $request, Order $order, DispatchOfferService $offers)
    {
        $data = $request->validate(['operation_key' => ['required', 'uuid']]);
        $cycle = $offers->start($order, $request->user(), $data['operation_key']);

        return redirect()->route('dispatch.index')->with('success', 'Cycle #'.$cycle->id.': '.$cycle->status.'. '.($cycle->outcome_reason ?? 'Nearest eligible rider offered; physical custody is unchanged.'));
    }

    public function respond(Request $request, DispatchOffer $offer, DispatchOfferService $offers)
    {
        $data = $request->validate(['decision' => ['required', 'in:accept,decline'], 'reason' => ['nullable', 'string', 'max:500']]);
        $result = $offers->respond($offer, $request->user(), $data['decision'] === 'accept', $data['reason'] ?? null);

        return back()->with('success', 'Offer '.$result.'. Open your work board for accepted parcel actions; normal physical scans remain required.');
    }

    public function cancel(Request $request, DispatchCycle $cycle, DispatchOfferService $offers)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $offers->cancel($cycle, $request->user(), $data['reason']);

        return back()->with('success', 'Pending automatic cycle cancelled. Only provisional offer capacity was released.');
    }
}
