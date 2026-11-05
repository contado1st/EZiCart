<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\ParcelTransfer;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParcelTransferService
{
    public function __construct(private AdministrativeAuditService $audits, private TransactionAwareNotificationSender $notifications) {}

    public function plan(Order $order, User $actor, User $destination, string $reason): ParcelTransfer
    {
        abort_unless($actor->isLogisticsAdmin() && $actor->status === 'approved', 403);

        return DB::transaction(function () use ($order, $actor, $destination, $reason): ParcelTransfer {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['AT_SORTING_CENTER', 'SORTED'], true) && $locked->sorting_center_id !== null, 422, 'Transfer only a parcel physically received at a hub and not assigned to a rider.');
            abort_unless($destination->role === 'sorting_center' && $destination->status === 'approved' && $destination->id !== $locked->sorting_center_id, 422, 'Choose a different approved destination hub.');
            abort_unless($locked->destinationArea?->sorting_center_id === $destination->id, 422, 'The destination hub must own the parcel service area.');
            abort_if(ParcelTransfer::query()->where('active_order_id', $locked->id)->exists(), 422, 'A transfer is already active for this parcel.');
            app(ParcelLoadService::class)->requireWeight($locked);
            abort_if(DeliveryAssignment::query()->where('active_order_id', $locked->id)->exists(), 422, 'Release any active rider assignment before planning a hub transfer.');
            $transfer = ParcelTransfer::create(['order_id' => $locked->id, 'active_order_id' => $locked->id, 'source_hub_id' => $locked->sorting_center_id, 'destination_hub_id' => $destination->id, 'requested_by' => $actor->id, 'status' => 'PLANNED', 'reason' => $reason]);
            $this->audits->record($actor, $transfer, 'hub_transfer_planned', [], ['source_hub_id' => $locked->sorting_center_id, 'destination_hub_id' => $destination->id], $reason);
            $this->notifications->send([$locked->sortingCenter, $destination], new WebOperationsNotification(['message' => 'Hub transfer planned for '.$locked->order_number.'. Source release and destination receipt scans are required.', 'url' => route('parcel-transfers.index')]));

            return $transfer;
        });
    }

    public function release(ParcelTransfer $transfer, User $actor, string $reference, string $transport, ?User $carrier = null): void
    {
        DB::transaction(function () use ($transfer, $actor, $reference, $transport, $carrier): void {
            if ($carrier) {
                $carrier = app(ParcelLoadService::class)->lockVehicle($carrier->id);
            }
            $order = Order::query()->whereKey($transfer->order_id)->lockForUpdate()->firstOrFail();
            $locked = ParcelTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->role === 'sorting_center' && $actor->status === 'approved' && $actor->id === $locked->source_hub_id, 403);
            abort_unless($locked->status === 'PLANNED' && $order->sorting_center_id === $actor->id && in_array($order->status, ['AT_SORTING_CENTER', 'SORTED'], true), 422, 'This transfer is not awaiting source release.');
            abort_unless(hash_equals('EZP:'.$order->parcel_code, $reference), 422, 'Scan the parcel label for this transfer.');
            abort_if(DB::table('parcel_placements')->where('active_order_id', $order->id)->exists(), 422, 'Retrieve the parcel from its storage location before transfer release.');
            if (! $carrier) {
                throw ValidationException::withMessages(['carrier_rider_id' => 'Select the registered transport driver and verified vehicle before hub transfer release.']);
            }
            abort_unless((int) $carrier->logistics_owner_id === $actor->id || $carrier->serviceAreas()->where('sorting_center_id', $actor->id)->exists(), 403, 'Select a carrier belonging to the source hub.');
            app(ParcelLoadService::class)->assertFits($order, $carrier, hubTransfer: true);
            $locked->forceFill(['carrier_rider_id' => $carrier->id, 'status' => 'IN_TRANSIT', 'released_by' => $actor->id, 'released_at' => now(), 'transport_reference' => $transport])->save();
            ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $actor->id, 'event_type' => 'hub_transfer_released', 'status' => $order->status, 'notes' => 'Released for hub transfer. Transport manifest: '.$transport]);
            $this->audits->record($actor, $locked, 'hub_transfer_released', ['status' => 'PLANNED'], ['status' => 'IN_TRANSIT', 'transport_reference' => $transport, 'carrier_rider_id' => $carrier->id, 'parcel_weight_grams' => $order->parcel_weight_grams, 'vehicle_capacity_grams' => $carrier->vehicle_capacity_grams], 'Parcel label scanned at source hub.');
            $this->notifications->send([$locked->destinationHub, $carrier], new WebOperationsNotification([
                'message' => 'Hub transfer '.$order->order_number.' assigned to '.$carrier->first_name.'. '.$locked->sourceHub->business_name.' to '.$locked->destinationHub->business_name.'. Destination must scan the parcel on physical receipt.',
                'url' => route('parcel-transfers.index'),
            ]));
        }, 3);
    }
}
