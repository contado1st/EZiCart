<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ParcelTransfer;
use App\Models\User;
use App\Services\AdministrativeAuditService;
use App\Services\OrderTransitionService;
use App\Services\ParcelTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParcelTransferController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        abort_if($actor->role === 'courier' && ! $actor->hub_transfer_only, 403);
        $transfers = ParcelTransfer::query()->with(['order', 'sourceHub', 'destinationHub', 'carrier'])
            ->when($actor->role === 'courier', fn ($query) => $query->where('carrier_rider_id', $actor->id))
            ->when($actor->role === 'sorting_center', fn ($query) => $query->where(fn ($q) => $q->where('source_hub_id', $actor->id)->orWhere('destination_hub_id', $actor->id)))
            ->latest('id')->paginate(20);

        $carriers = User::where('role', 'courier')->where('status', 'approved')
            ->where('hub_transfer_only', true)
            ->when(! $actor->isLogisticsAdmin(), fn ($query) => $query->where(fn ($q) => $q->where('logistics_owner_id', $actor->id)->orWhereHas('serviceAreas', fn ($a) => $a->where('sorting_center_id', $actor->id))))->get();

        return view('logistics.transfers', compact('transfers', 'actor', 'carriers'));
    }

    public function plan(Request $request, Order $order, ParcelTransferService $service)
    {
        $data = $request->validate(['destination_hub_id' => ['required', 'integer', 'exists:users,id'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->plan($order, $request->user(), User::findOrFail($data['destination_hub_id']), $data['reason']);

        return back()->with('success', 'Transfer planned. The source hub must scan and release the parcel.');
    }

    public function release(Request $request, ParcelTransfer $transfer, ParcelTransferService $service)
    {
        $data = $request->validate(['parcel_reference' => ['required', 'string', 'max:100'], 'transport_reference' => ['required', 'string', 'max:120'], 'carrier_rider_id' => ['required', 'integer', 'exists:users,id']]);
        $service->release($transfer, $request->user(), $data['parcel_reference'], $data['transport_reference'], User::findOrFail($data['carrier_rider_id']));

        return back()->with('success', 'Transfer released to transport. Destination receipt is pending.');
    }

    public function receive(Request $request, ParcelTransfer $transfer, OrderTransitionService $service)
    {
        $data = $request->validate(['parcel_reference' => ['required', 'string', 'max:100']]);
        $service->receiveHubTransfer($transfer, $request->user(), $data['parcel_reference']);

        return back()->with('success', 'Transfer physically received at the destination hub.');
    }

    public function replaceCarrier(Request $request, ParcelTransfer $transfer, ParcelTransferService $service)
    {
        $data = $request->validate(['carrier_rider_id' => ['required', 'integer', 'exists:users,id'], 'parcel_reference' => ['required', 'string', 'max:100'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->replaceCarrier($transfer, $request->user(), User::findOrFail($data['carrier_rider_id']), $data['parcel_reference'], $data['reason']);

        return back()->with('success', 'Replacement custody recorded. Continue to the original destination hub.');
    }

    public function cancel(Request $request, ParcelTransfer $transfer, AdministrativeAuditService $audits)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($request, $transfer, $data, $audits): void {
            Order::query()->whereKey($transfer->order_id)->lockForUpdate()->firstOrFail();
            $locked = ParcelTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'PLANNED', 422, 'A released transfer cannot be cancelled. Complete physical receipt instead.');
            $locked->forceFill(['status' => 'CANCELLED', 'active_order_id' => null])->save();
            $audits->record($request->user(), $locked, 'hub_transfer_cancelled', ['status' => 'PLANNED'], ['status' => 'CANCELLED'], $data['reason']);
        });

        return back()->with('success', 'Unreleased transfer cancelled.');
    }
}
