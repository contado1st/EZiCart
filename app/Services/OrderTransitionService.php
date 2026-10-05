<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ParcelTrackingEvent;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
use App\Notifications\OrderWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderTransitionService
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'PLACED' => ['CONFIRMED', 'CANCELLED'],
        'CONFIRMED' => ['PREPARING', 'CANCELLED'],
        'PREPARING' => ['READY_FOR_PICKUP', 'CANCELLED'],
        'READY_FOR_PICKUP' => ['PICKED_UP'],
        'PICKED_UP' => ['AT_SORTING_CENTER'],
        'AT_SORTING_CENTER' => ['SORTED'],
        'SORTED' => ['ASSIGNED_TO_RIDER'],
        'ASSIGNED_TO_RIDER' => ['OUT_FOR_DELIVERY', 'ASSIGNED_TO_RIDER'],
        'OUT_FOR_DELIVERY' => ['DELIVERED', 'DELIVERY_FAILED'],
        'DELIVERY_FAILED' => ['ASSIGNED_TO_RIDER', 'RETURN_IN_TRANSIT'],
        'DELIVERED' => ['COMPLETED'],
        'RETURN_IN_TRANSIT' => ['RETURNED_TO_SELLER'],
    ];

    /** @var array<string, list<string>> */
    private const TRANSITION_ATTRIBUTES = [
        'PICKED_UP' => ['picked_up_at'],
        'AT_SORTING_CENTER' => ['sorting_center_id', 'received_at'],
        'SORTED' => ['destination_area_id', 'delivery_area', 'sorted_at'],
        'ASSIGNED_TO_RIDER' => ['delivery_courier_id', 'assigned_at', 'hub_released_at', 'failed_at', 'delivery_failure_reason', 'delivery_notes'],
        'OUT_FOR_DELIVERY' => ['out_for_delivery_at'],
        'DELIVERY_FAILED' => ['failed_at', 'delivery_failure_reason', 'delivery_notes'],
        'DELIVERED' => ['delivered_at', 'delivery_notes', 'cod_collected_amount'],
    ];

    /** @param array<string, mixed> $attributes */
    public function __construct(
        private InventoryRestorationService $inventoryRestoration,
        private TransactionAwareNotificationSender $notifications,
    ) {}

    public function transition(
        Order $order,
        User $actor,
        OrderStatus $target,
        string $eventType,
        ?string $location = null,
        ?string $notes = null,
        array $attributes = [],
    ): Order {
        $unexpectedAttributes = array_diff(array_keys($attributes), self::TRANSITION_ATTRIBUTES[$target->value] ?? []);
        if ($unexpectedAttributes !== []) {
            throw new HttpException(422, 'The transition included attributes that are not allowed for this order status.');
        }

        return DB::transaction(function () use ($order, $actor, $target, $eventType, $location, $notes, $attributes): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current = $lockedOrder->status;

            if (! in_array($target->value, self::TRANSITIONS[$current] ?? [], true)) {
                throw new HttpException(422, 'This order cannot move to that status.');
            }

            if ($lockedOrder->payment_method !== 'COD' && ! in_array($target, [
                OrderStatus::Cancelled,
                OrderStatus::DeliveryFailed,
                OrderStatus::ReturnInTransit,
                OrderStatus::ReturnedToSeller,
            ], true)) {
                throw new HttpException(422, 'Fulfillment is on hold because payment verification for this method is not configured.');
            }

            $this->authorizeActor($lockedOrder, $actor, $target);
            if ($target === OrderStatus::Confirmed) {
                $this->validateSellerOrderInventory($lockedOrder);
            }

            $lockedOrder->forceFill([...$attributes, 'status' => $target->value])->save();

            $this->recordAssignmentLifecycle($lockedOrder->refresh(), $actor, $target);

            if (in_array($target, [OrderStatus::Cancelled, OrderStatus::ReturnedToSeller], true)) {
                $this->inventoryRestoration->restore($lockedOrder);
            }

            ParcelTrackingEvent::create([
                'order_id' => $lockedOrder->id,
                'actor_id' => $actor->id,
                'event_type' => $eventType,
                'status' => $target->value,
                'location' => $location,
                'notes' => $notes,
            ]);

            foreach ($this->notificationRecipients($lockedOrder, $target) as $recipient) {
                $this->notifications->send($recipient, new OrderStatusNotification(
                    $lockedOrder,
                    $target->value,
                    $eventType,
                    $notes ?? 'Order status changed to '.str_replace('_', ' ', strtolower($target->value)).'.',
                ));
            }

            return $lockedOrder->refresh();
        });
    }

    public function declineDeliveryAssignment(Order $order, User $actor, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $actor, $reason): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $actor->role === 'courier'
                    && $actor->status === 'approved'
                    && $lockedOrder->status === OrderStatus::AssignedToRider->value
                    && $lockedOrder->delivery_courier_id === $actor->id,
                403,
                'You do not have an active delivery assignment for this parcel.',
            );
            abort_unless($lockedOrder->hub_released_at === null, 422, 'A parcel cannot be declined after Logistics releases it from the hub.');

            $assignment = DeliveryAssignment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('rider_id', $actor->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();
            $message = filled($reason)
                ? "The assigned rider declined before hub release: {$reason}"
                : 'The assigned rider declined before hub release. Logistics can assign another rider.';
            $lockedOrder->forceFill([
                'status' => OrderStatus::Sorted->value,
                'delivery_courier_id' => null,
                'assigned_at' => null,
                'hub_released_at' => null,
            ])->save();

            $assignment->update([
                'status' => 'declined',
                'active_order_id' => null,
                'released_at' => now(),
            ]);
            $sortedOrder = $lockedOrder->refresh();
            ParcelTrackingEvent::query()->create([
                'order_id' => $sortedOrder->id,
                'actor_id' => $actor->id,
                'event_type' => 'delivery_assignment_declined',
                'status' => OrderStatus::Sorted->value,
                'location' => $sortedOrder->delivery_area,
                'notes' => $message,
            ]);
            foreach ($this->notificationRecipients($sortedOrder, OrderStatus::Sorted) as $recipient) {
                $this->notifications->send($recipient, new OrderStatusNotification(
                    $sortedOrder,
                    OrderStatus::Sorted->value,
                    'delivery_assignment_declined',
                    $message,
                ));
            }
            User::query()->where('role', 'sorting_center')->where('status', 'approved')->each(
                fn (User $operator) => $this->notifications->send($operator, new OrderWorkflowNotification($sortedOrder, 'delivery_assignment_declined', 'A rider declined a delivery before hub release. The parcel is back in the dispatch queue.')),
            );

            return $sortedOrder->refresh();
        });
    }

    public function recoverReleasedDeliveryParcel(Order $order, User $actor): Order
    {
        return DB::transaction(function () use ($order, $actor): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $actor->role === 'sorting_center'
                    && $actor->status === 'approved'
                    && $lockedOrder->status === OrderStatus::AssignedToRider->value
                    && $lockedOrder->delivery_courier_id !== null
                    && $lockedOrder->hub_released_at !== null,
                422,
                'Only a released parcel physically recovered at the hub can be returned to dispatch.',
            );

            $rider = $lockedOrder->deliveryCourier;
            $assignment = DeliveryAssignment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('rider_id', $lockedOrder->delivery_courier_id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $lockedOrder->forceFill([
                'status' => OrderStatus::Sorted->value,
                'delivery_courier_id' => null,
                'assigned_at' => null,
                'hub_released_at' => null,
                'delivery_recovered_at' => now(),
            ])->save();
            $assignment->update([
                'status' => 'reassigned',
                'active_order_id' => null,
                'released_at' => now(),
            ]);

            $sortedOrder = $lockedOrder->refresh();
            $message = 'Logistics confirmed physical recovery of the parcel from the rider at the sorting center.';
            ParcelTrackingEvent::query()->create([
                'order_id' => $sortedOrder->id,
                'actor_id' => $actor->id,
                'event_type' => 'delivery_parcel_recovered_at_hub',
                'status' => OrderStatus::Sorted->value,
                'location' => $actor->municipality,
                'notes' => $message,
            ]);

            if ($rider !== null) {
                $this->notifications->send($rider, new OrderWorkflowNotification($sortedOrder, 'delivery_parcel_recovered_at_hub', 'Logistics recorded that the parcel was recovered from you at the hub.'));
            }

            foreach ($this->notificationRecipients($sortedOrder, OrderStatus::Sorted) as $recipient) {
                $this->notifications->send($recipient, new OrderStatusNotification(
                    $sortedOrder,
                    OrderStatus::Sorted->value,
                    'delivery_parcel_recovered_at_hub',
                    $message,
                ));
            }

            return $sortedOrder;
        });
    }

    private function authorizeActor(Order $order, User $actor, OrderStatus $target): void
    {
        $authorized = match ($target) {
            OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::ReadyForPickup, OrderStatus::ReturnedToSeller => $actor->role === 'seller' && $order->seller_id === $actor->id,
            OrderStatus::Completed => $actor->role === 'buyer' && $order->buyer_id === $actor->id,
            OrderStatus::Cancelled => $actor->role === 'buyer' && $order->buyer_id === $actor->id,
            OrderStatus::PickedUp => $actor->role === 'courier' && $order->pickup_courier_id === $actor->id,
            OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::DeliveryFailed => $actor->role === 'courier' && $order->delivery_courier_id === $actor->id,
            OrderStatus::AtSortingCenter, OrderStatus::AssignedToRider => $actor->role === 'sorting_center',
            OrderStatus::Sorted => $actor->role === 'sorting_center',
            OrderStatus::ReturnInTransit => $actor->role === 'sorting_center' || ($actor->role === 'courier' && $order->delivery_courier_id === $actor->id),
            default => false,
        };

        if (! $authorized || $actor->status !== 'approved') {
            abort(403, 'You are not authorized to make this order transition.');
        }
    }

    private function validateSellerOrderInventory(Order $order): void
    {
        $items = OrderItem::query()
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->get();

        if ($items->isEmpty() || $order->inventory_restored_at !== null) {
            throw new HttpException(422, 'This order has no valid reserved inventory to fulfill.');
        }

        foreach ($items as $item) {
            $product = $item->product_id === null
                ? null
                : Product::query()->whereKey($item->product_id)->lockForUpdate()->first();

            if (
                $product === null
                || (int) $product->user_id !== (int) $order->seller_id
                || $product->is_archived
                || $product->compliance_status !== 'approved'
                || $item->quantity < 1
            ) {
                throw new HttpException(422, 'An order item is no longer available for fulfillment.');
            }

            if ($item->variation_id === null) {
                continue;
            }

            $variation = ProductVariation::query()->whereKey($item->variation_id)->lockForUpdate()->first();

            if ($variation === null || (int) $variation->product_id !== (int) $product->id) {
                throw new HttpException(422, 'An order variation is no longer available for fulfillment.');
            }
        }
    }

    private function recordAssignmentLifecycle(Order $order, User $actor, OrderStatus $target): void
    {
        $riderId = $order->delivery_courier_id === null ? null : (int) $order->delivery_courier_id;

        if ($target === OrderStatus::AssignedToRider && $riderId !== null) {
            $activeAssignment = DeliveryAssignment::query()
                ->where('order_id', $order->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($activeAssignment?->rider_id === $riderId) {
                return;
            }

            if ($activeAssignment !== null) {
                $activeAssignment->update(['status' => 'reassigned', 'active_order_id' => null, 'released_at' => now()]);
            }

            DeliveryAssignment::query()->create([
                'order_id' => $order->id,
                'active_order_id' => $order->id,
                'rider_id' => $riderId,
                'assigned_by' => $actor->id,
                'status' => 'active',
                'assigned_at' => $order->assigned_at ?? now(),
            ]);

            return;
        }

        if (! in_array($target, [OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::ReturnInTransit], true) || $riderId === null) {
            return;
        }

        $activeAssignment = DeliveryAssignment::query()
            ->where('order_id', $order->id)
            ->where('rider_id', $riderId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if ($activeAssignment === null) {
            $activeAssignment = DeliveryAssignment::query()->create([
                'order_id' => $order->id,
                'active_order_id' => $order->id,
                'rider_id' => $riderId,
                'assigned_by' => null,
                'status' => 'active',
                'assigned_at' => $order->assigned_at ?? now(),
            ]);
        }

        if ($target === OrderStatus::OutForDelivery) {
            $activeAssignment->update(['accepted_at' => $activeAssignment->accepted_at ?? now()]);

            return;
        }

        if ($target === OrderStatus::ReturnInTransit) {
            return;
        }

        $activeAssignment->update([
            'status' => 'completed',
            'active_order_id' => null,
            'released_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /** @return list<User> */
    private function notificationRecipients(Order $order, OrderStatus $target): array
    {
        $recipients = [$order->buyer, $order->seller];

        if ($target === OrderStatus::AssignedToRider || $target === OrderStatus::OutForDelivery || $target === OrderStatus::DeliveryFailed || $target === OrderStatus::ReturnInTransit) {
            $recipients[] = $order->deliveryCourier;
        }

        if ($target === OrderStatus::PickedUp) {
            $recipients[] = $order->pickupCourier;
        }

        if (in_array($target, [OrderStatus::AtSortingCenter, OrderStatus::DeliveryFailed, OrderStatus::ReturnInTransit], true)) {
            array_push($recipients, ...User::query()->where('role', 'sorting_center')->where('status', 'approved')->get()->all());
        }

        return collect($recipients)->filter(fn (?User $user): bool => $user !== null)->unique('id')->values()->all();
    }
}
