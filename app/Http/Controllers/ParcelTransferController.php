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
        $transfers = ParcelTransfer::query()->with(['order', 'sourceHub', 'destinationHub'])
            ->when(! $actor->isLogisticsAdmin(), fn ($query) => $query->where(fn ($q) => $q->where('source_hub_id', $actor->id)->orWhere('destination_hub_id', $actor->id)))
            ->latest('id')->paginate(20);

        return view('logistics.transfers', compact('transfers', 'actor'));
    }

    public function plan(Request $request, Order $order, ParcelTransferService $service)
    {
        $data = $request->validate(['destination_hub_id' => ['required', 'integer', 'exists:users,id'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->plan($order, $request->user(), User::findOrFail($data['destination_hub_id']), $data['reason']);

        return back()->with('success', 'Transfer planned. The source hub must scan and release the parcel.');
    }

    public function release(Request $request, ParcelTransfer $transfer, ParcelTransferService $service)
    {
        $data = $request->validate(['parcel_reference' => ['required', 'string', 'max:100'], 'transport_reference' => ['required', 'string', 'max:120']]);
        $service->release($transfer, $request->user(), $data['parcel_reference'], $data['transport_reference']);

        return back()->with('success', 'Transfer released to transport. Destination receipt is pending.');
    }

    public function receive(Request $request, ParcelTransfer $transfer, OrderTransitionService $service)
    {
        $data = $request->validate(['parcel_reference' => ['required', 'string', 'max:100']]);
        $service->receiveHubTransfer($transfer, $request->user(), $data['parcel_reference']);

        return back()->with('success', 'Transfer physically received at the destination hub.');
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
