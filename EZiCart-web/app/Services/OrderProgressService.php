<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class OrderProgressService
{
    public const STEPS = ['Order placed', 'Seller preparing', 'Awaiting pickup', 'Collected', 'At sorting hub', 'Out for delivery', 'Delivered', 'Completed'];

    public function describe(Order $order): array
    {
        $order->loadMissing(['pendingCancellation', 'activeTransfer', 'deliveryAssignments', 'dispute', 'returnPickupAssignment']);
        $stage = match ($order->status) {
            'PLACED' => 0, 'CONFIRMED', 'PREPARING' => 1, 'READY_FOR_PICKUP' => 2, 'PICKED_UP' => 3,
            'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER' => 4, 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED' => 5,
            'DELIVERED' => 6, 'COMPLETED' => 7, default => null,
        };
        [$owner, $label, $route] = match ($order->status) {
            'PLACED' => ['seller', 'Confirm the new order.', 'seller.orders.updateStatus'],
            'CONFIRMED' => ['seller', 'Prepare and pack the parcel.', 'seller.orders.updateStatus'],
            'PREPARING' => ['seller', 'Mark the packed parcel ready for pickup.', 'seller.orders.updateStatus'],
            'READY_FOR_PICKUP' => $this->pickup($order),
            'PICKED_UP' => ['sorting_center', 'The rider has collected the parcel. The sorting hub must scan it at intake.', 'logistics.scan'],
            'AT_SORTING_CENTER' => ['sorting_center', 'Verify the destination and sort the parcel.', 'logistics.orders.sort'],
            'SORTED' => ['sorting_center', 'Assign a delivery rider and prepare the parcel for release.', 'logistics.orders.assignRider'],
            'ASSIGNED_TO_RIDER' => $order->hub_released_at
                ? ['courier', 'The hub released the parcel. The delivery rider must start delivery.', 'courier.orders.startDelivery']
                : ($order->deliveryAssignments->where('status', 'active')->first()?->accepted_at
                    ? ['sorting_center', 'The rider accepted. Scan the rider badge and parcel to confirm physical release.', 'logistics.orders.releaseToRider']
                    : ['courier', 'The assigned delivery rider must accept the assignment.', 'courier.orders.accept-assignment']),
            'OUT_FOR_DELIVERY' => ['courier', ($order->payment_method === 'WALLET' ? 'Deliver the prepaid parcel and record the delivery code and proof photo. Do not collect cash.' : 'Deliver to the buyer, collect COD and record the delivery code and proof photo.'), 'courier.orders.completeDelivery'],
            'DELIVERY_FAILED' => ['sorting_center', 'Review the failure and physically recover the parcel before retrying or returning it.', ''],
            'DELIVERED' => ['buyer', 'The parcel was delivered. Inspect it and confirm receipt.', 'buyer.orders.confirm'],
            'COMPLETED' => ['buyer', 'Receipt is confirmed. You can review the purchased products.', 'buyer.orders.review'],
            'RETURN_IN_TRANSIT' => $order->return_handed_to_seller_at
                ? ['seller', 'The rider recorded return handover. Confirm receipt after receiving the parcel.', 'seller.orders.confirmReturn']
                : ['courier', 'Return the parcel and record the physical handover to the seller.', 'courier.orders.confirmReturnDelivery'],
            'RETURNED_TO_SELLER' => [null, 'The return was received by the seller. Review the return and financial records.', ''],
            'CANCELLED' => [null, 'The order was cancelled. No further fulfillment action is required.', ''],
            default => [null, 'Review the parcel history for the latest recorded event.', ''],
        };
        if ($order->pendingCancellation) {
            [$owner, $label, $route] = ['seller', 'Cancellation requested. Fulfillment is paused until the seller responds.', 'seller.orders.cancellation'];
        } elseif ($order->activeTransfer) {
            [$owner, $label, $route] = ['sorting_center', 'A hub transfer is active. Complete the physical dispatch and receiving scans in Hub transfers.', ''];
        } elseif (in_array($order->dispute?->status, ['PENDING', 'UNDER_REVIEW'], true)) {
            [$owner, $label, $route] = ['ecommerce_admin', 'A dispute is under review. Wait for the marketplace administrator’s decision.', ''];
        } elseif (in_array($order->status, ['DELIVERED', 'COMPLETED'], true) && in_array($order->dispute?->status, ['REFUND_APPROVED', 'REPLACEMENT_APPROVED'], true)) {
            $assignment = $order->returnPickupAssignment;
            $handedOver = $assignment && $order->trackingEvents()->where('event_type', 'return_buyer_handover')->where('actor_id', $order->buyer_id)->where('notes', 'Return pickup assignment #'.$assignment->id)->exists();
            [$owner, $label, $route] = ! $assignment
                ? ['sorting_center', 'Return approved. Assign a verified rider for collection from the buyer.', 'return-pickups.index']
                : (! $assignment->accepted_at
                    ? ['courier', 'Return pickup assigned. The rider must accept before buyer handover.', 'return-pickups.accept']
                    : ($handedOver ? ['courier', 'Buyer confirmed handover. Scan the return parcel to confirm possession.', 'return-pickups.collect']
                        : ['buyer', 'Return rider accepted. Confirm handover only after physically giving the parcel to that rider.', 'return-pickups.handover']));
        } elseif (! $order->parcel_weight_grams && $order->status === 'READY_FOR_PICKUP') {
            [$owner, $label, $route] = ['seller', 'Record the actual packed parcel weight before pickup or assignment.', 'orders.weight'];
        } elseif (! $order->parcel_weight_grams && in_array($order->status, ['AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER'], true) && ! $order->hub_released_at) {
            [$owner, $label, $route] = ['sorting_center', 'Weigh the packed parcel at this hub before assignment or dispatch.', 'orders.weight'];
        } elseif ($order->status === 'COMPLETED') {
            $order->loadMissing(['items', 'reviews']);
            if (! $order->items->contains(fn ($item) => ! $order->reviews->contains('product_id', $item->product_id))) {
                [$owner, $label, $route] = [null, 'The order is complete and its purchased products have been reviewed.', ''];
            }
        }

        return ['stage' => $stage, 'status' => $order->status, 'owner' => $owner, 'message' => $label, 'action_route' => $route];
    }

    private function pickup(Order $order): array
    {
        return match (true) {
            ! $order->pickup_requested_at => ['seller', 'The parcel is ready. Schedule its pickup.', 'seller.orders.schedulePickup'],
            ! $order->pickup_courier_id => ['sorting_center', 'Pickup requested. Logistics must assign a pickup rider.', 'logistics.orders.assignPickup'],
            ! $order->pickup_claimed_at => ['courier', 'A pickup rider is assigned and must accept the pickup.', 'courier.orders.claim'],
            ! $order->pickup_arrived_at => ['courier', 'The pickup was accepted. The rider must record arrival at the seller.', 'courier.orders.confirmPickup'],
            ! $order->seller_handover_at => ['seller', 'The rider arrived. Scan the rider badge and confirm parcel handover.', 'seller.orders.confirmHandover'],
            default => ['courier', 'The seller confirmed handover. Scan the parcel to prove possession and complete pickup.', 'courier.orders.confirmPickup'],
        };
    }

    public function forViewer(Order $order, User $viewer): array
    {
        $progress = $this->describe($order);
        $role = $viewer->isCommerceAdmin() ? 'ecommerce_admin' : $viewer->role;
        if ($order->status === 'PICKED_UP' && $role === 'courier' && (int) $order->pickup_courier_id === $viewer->id) {
            $progress['owner'] = 'courier';
            $progress['message'] = 'Bring the collected parcel to the sorting hub and hand it over for the intake scan.';
            $progress['action_route'] = '';
        }
        $responsible = $progress['owner'] === $role;
        if ($role === 'courier') {
            $assignedRider = $order->returnPickupAssignment?->rider_id ?? (in_array($order->status, ['READY_FOR_PICKUP', 'PICKED_UP'], true) ? $order->pickup_courier_id : $order->delivery_courier_id);
            $responsible = $responsible && (int) $assignedRider === $viewer->id;
        }

        $actionRoute = $progress['action_route'];

        return $progress + ['requires_action' => $responsible, 'action_url' => $actionRoute && Route::has($actionRoute) ? route($actionRoute, ['order' => $order->id]) : null, 'owner_label' => match ($progress['owner']) {
            'seller' => 'seller', 'buyer' => 'buyer', 'courier' => 'assigned rider', 'sorting_center' => 'sorting hub', 'ecommerce_admin' => 'marketplace admin', default => 'no further action',
        }];
    }
}
