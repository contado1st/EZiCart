<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use App\Notifications\WebOperationsNotification;
use App\Services\AdministrativeAuditService;
use App\Services\ParcelLoadService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParcelWeightController extends Controller
{
    public function record(Request $request, Order $order, AdministrativeAuditService $audits, TransactionAwareNotificationSender $notifications)
    {
        $actor = $request->user();
        abort_unless(($actor->role === 'seller' && $order->seller_id === $actor->id) || ($actor->role === 'sorting_center' && $order->sorting_center_id === $actor->id), 403);
        $data = $request->validate(['parcel_weight_kg' => ParcelLoadService::KG_RULES, 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($order, $actor, $data, $audits, $notifications): void {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $seller = $actor->role === 'seller' && $locked->seller_id === $actor->id && in_array($locked->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true);
            $hub = $actor->role === 'sorting_center' && $locked->sorting_center_id === $actor->id && in_array($locked->status, ['AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER'], true) && $locked->hub_released_at === null;
            abort_unless(($seller || $hub) && ! $locked->activeTransfer()->exists(), 422, 'Weigh the parcel only while it is physically held by the seller before handover or by its current sorting hub before release.');
            $previous = $locked->only(['parcel_weight_grams', 'weight_recorded_by', 'weight_recorded_at']);
            $locked->forceFill(['parcel_weight_grams' => ParcelLoadService::grams($data['parcel_weight_kg']), 'weight_recorded_by' => $actor->id, 'weight_recorded_at' => now()])->save();
            $audits->record($actor, $locked, 'parcel_weight_recorded', $previous, $locked->only(['parcel_weight_grams', 'weight_recorded_by', 'weight_recorded_at']), $data['reason']);
            $notifications->send(collect([$locked->seller, $locked->pickupCourier, $locked->deliveryCourier, $locked->sortingCenter])->filter()->unique('id'), new OrderWorkflowNotification($locked, 'parcel_weight_recorded', 'Actual packed parcel weight recorded: '.ParcelLoadService::kg($locked->parcel_weight_grams).'. Vehicle capacity is checked again before movement.'));
        }, 3);

        return back()->with('success', 'Actual parcel weight recorded. Reassign any vehicle whose reserved load now exceeds its verified capacity before moving the parcel.');
    }

    public function capacity(Request $request, User $rider, AdministrativeAuditService $audits, TransactionAwareNotificationSender $notifications)
    {
        abort_unless($request->user()->isLogisticsAdmin() && $rider->role === 'courier', 403);
        $data = $request->validate(['vehicle_capacity_kg' => ParcelLoadService::KG_RULES, 'hub_transfer_only' => ['sometimes', 'boolean'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($request, $rider, $data, $audits, $notifications): void {
            $locked = User::whereKey($rider->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->role === 'courier' && $locked->status === 'approved', 422, 'Verify capacity for an approved rider and their current vehicle.');
            $duty = array_key_exists('hub_transfer_only', $data) ? (bool) $data['hub_transfer_only'] : $locked->hub_transfer_only;
            if ($duty !== $locked->hub_transfer_only && app(ParcelLoadService::class)->activeLoads($locked->id)->exists()) {
                throw ValidationException::withMessages(['hub_transfer_only' => 'Complete or release all reserved loads before changing this driver duty.']);
            }
            $before = $locked->only(['vehicle_capacity_grams', 'vehicle_capacity_verified_by', 'vehicle_capacity_verified_at', 'hub_transfer_only']);
            $locked->forceFill(['hub_transfer_only' => $duty, 'vehicle_capacity_grams' => ParcelLoadService::grams($data['vehicle_capacity_kg']), 'vehicle_capacity_verified_by' => $request->user()->id, 'vehicle_capacity_verified_at' => now()])->save();
            $audits->record($request->user(), $locked, 'vehicle_capacity_verified', $before, $locked->only(['vehicle_capacity_grams', 'vehicle_capacity_verified_by', 'vehicle_capacity_verified_at', 'hub_transfer_only']), $data['reason']);
            $notifications->send(collect([$locked, $locked->logisticsOwner])->filter()->unique('id'), new WebOperationsNotification(['message' => 'Logistics administration verified this vehicle’s cargo limit: '.ParcelLoadService::kg($locked->vehicle_capacity_grams).'. Reduce or reassign any load above this limit before movement.', 'url' => route('load-planning.index')]));
        }, 3);

        return back()->with('success', 'Vehicle cargo capacity verified. Overweight or unknown-weight loads remain blocked.');
    }
}
