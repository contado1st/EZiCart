<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use Illuminate\Support\Facades\DB;

class ReturnPickupService
{
    public function __construct(private AdministrativeAuditService $audits, private TransactionAwareNotificationSender $notifications, private OrderTransitionService $transitions) {}

    public function authorized(Order $order, User $actor): void
    {
        ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $actor->id, 'event_type' => 'return_authorized', 'status' => $order->status, 'notes' => 'Return approved. Parcel remains with the buyer until a verified rider accepts and collects it.']);
        $this->notify($order, 'Return approved. Logistics must arrange pickup; approval does not mean the parcel was collected.');
        $this->audits->record($actor, $order, 'return_authorized', [], ['status' => $order->status], 'Return authorization only; physical custody unchanged.');
    }

    public function assign(Order $order, User $actor, User $rider): void
    {
        abort_unless($actor->status === 'approved' && ($actor->isLogisticsAdmin() || ($actor->role === 'sorting_center' && $order->sorting_center_id === $actor->id)), 403);
        DB::transaction(function () use ($order, $actor, $rider): void {
            $rider = app(ParcelLoadService::class)->lockVehicle($rider->id);
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isLogisticsAdmin() || $locked->sorting_center_id === $actor->id, 403);
            $this->assertAwaitingPickup($locked);
            abort_unless($locked->sorting_center_id !== null && ((int) $rider->logistics_owner_id === (int) $locked->sorting_center_id || $rider->serviceAreas()->where('sorting_center_id', $locked->sorting_center_id)->exists()), 422, 'Choose a local rider belonging to the parcel destination hub.');
            app(ParcelLoadService::class)->assertFits($locked, $rider);
            $previous = $locked->returnPickupAssignment()->lockForUpdate()->first();
            abort_if($previous?->accepted_at !== null, 422, 'The rider has already accepted this return pickup. Recover custody before reassignment.');
            if ($previous) {
                $previous->update(['status' => 'reassigned', 'active_order_id' => null, 'released_at' => now()]);
            }
            DeliveryAssignment::create(['order_id' => $locked->id, 'active_order_id' => $locked->id, 'rider_id' => $rider->id, 'assigned_by' => $actor->id, 'status' => 'return_pending', 'assigned_at' => now()]);
            $this->audits->record($actor, $locked, 'return_pickup_assigned', [], ['rider_id' => $rider->id], 'Rider assigned for buyer return pickup; custody unchanged.');
            $this->notify($locked, 'Return pickup assigned. The rider must accept before the buyer confirms handover.');
        }, 3);
    }

    public function accept(Order $order, User $rider): void
    {
        DB::transaction(function () use ($order, $rider): void {
            app(ParcelLoadService::class)->lockVehicle($rider->id);
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertAwaitingPickup($locked);
            $assignment = $this->assignment($locked, $rider);
            app(ParcelLoadService::class)->assertFits($locked, $rider);
            if ($assignment->accepted_at === null) {
                $assignment->update(['accepted_at' => now()]);
                $this->notify($locked, 'Return rider accepted. Confirm handover only when the rider arrives and takes the parcel.');
            }
        }, 3);
    }

    public function handover(Order $order, User $buyer): void
    {
        abort_unless($buyer->role === 'buyer' && $buyer->status === 'approved' && $order->buyer_id === $buyer->id, 403);
        DB::transaction(function () use ($order, $buyer): void {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertAwaitingPickup($locked);
            $assignment = $locked->returnPickupAssignment()->lockForUpdate()->firstOrFail();
            abort_unless($assignment->accepted_at !== null, 422, 'Wait for the assigned rider to accept this pickup.');
            $locked->trackingEvents()->firstOrCreate(['event_type' => 'return_buyer_handover', 'notes' => 'Return pickup assignment #'.$assignment->id], ['actor_id' => $buyer->id, 'status' => $locked->status]);
            $this->notify($locked, 'Buyer confirmed return handover. Assigned rider: scan the parcel label to confirm possession.');
        }, 3);
    }

    public function collect(Order $order, User $rider, string $reference): void
    {
        DB::transaction(function () use ($order, $rider, $reference): void {
            app(ParcelLoadService::class)->lockVehicle($rider->id);
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertAwaitingPickup($locked);
            $assignment = $this->assignment($locked, $rider);
            abort_unless($assignment->accepted_at && $locked->trackingEvents()->where('event_type', 'return_buyer_handover')->where('actor_id', $locked->buyer_id)->where('notes', 'Return pickup assignment #'.$assignment->id)->exists(), 422, 'Rider acceptance and buyer handover confirmation are required before collection.');
            abort_unless(hash_equals('EZP:'.$locked->parcel_code, $reference), 422, 'Scan this return parcel label to confirm possession.');
            app(ParcelLoadService::class)->assertFits($locked, $rider);
            $this->transitions->transition($locked, $rider, OrderStatus::ReturnInTransit, 'return_pickup_collected', null, 'Buyer confirmed handover and rider scanned the return parcel.', ['delivery_courier_id' => $rider->id]);
            $assignment->update(['status' => 'active']);
        }, 3);
    }

    private function assertAwaitingPickup(Order $order): void
    {
        abort_unless(in_array($order->status, ['DELIVERED', 'COMPLETED'], true) && $order->dispute()->whereIn('status', ['REFUND_APPROVED', 'REPLACEMENT_APPROVED'])->exists(), 422, 'This order has no approved return awaiting collection.');
    }

    private function assignment(Order $order, User $rider): DeliveryAssignment
    {
        abort_unless($rider->role === 'courier' && $rider->status === 'approved', 403);
        $assignment = $order->returnPickupAssignment()->lockForUpdate()->firstOrFail();
        abort_unless($assignment->rider_id === $rider->id, 403);

        return $assignment;
    }

    private function notify(Order $order, string $message): void
    {
        $rider = $order->returnPickupAssignment?->rider;
        $admins = User::where('role', 'logistics_admin')->where('status', 'approved')->get();
        $this->notifications->send(collect([$order->buyer, $order->seller, $order->sortingCenter, $rider])->filter()->merge($admins)->unique('id'), new WebOperationsNotification(['message' => $order->order_number.': '.$message, 'url' => route('return-pickups.index')]));
    }
}
