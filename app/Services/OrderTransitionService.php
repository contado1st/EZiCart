<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
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

    /** @param array<string, mixed> $attributes */
    public function __construct(private InventoryRestorationService $inventoryRestoration) {}

    public function transition(
        Order $order,
        User $actor,
        OrderStatus $target,
        string $eventType,
        ?string $location = null,
        ?string $notes = null,
        array $attributes = [],
    ): Order {
        return DB::transaction(function () use ($order, $actor, $target, $eventType, $location, $notes, $attributes): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current = $lockedOrder->status;

            if (! in_array($target->value, self::TRANSITIONS[$current] ?? [], true)) {
                throw new HttpException(422, 'This order cannot move to that status.');
            }

            $this->authorizeActor($lockedOrder, $actor, $target);
            $lockedOrder->update([...$attributes, 'status' => $target->value]);

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
                $recipient->notify(new OrderStatusNotification(
                    $lockedOrder,
                    $target->value,
                    $eventType,
                    $notes ?? 'Order status changed to '.str_replace('_', ' ', strtolower($target->value)).'.',
                ));
            }

            return $lockedOrder->refresh();
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
            OrderStatus::AtSortingCenter, OrderStatus::Sorted, OrderStatus::AssignedToRider => $actor->role === 'sorting_center',
            OrderStatus::ReturnInTransit => $actor->role === 'sorting_center' || ($actor->role === 'courier' && $order->delivery_courier_id === $actor->id),
            default => false,
        };

        if (! $authorized || $actor->status !== 'approved') {
            abort(403, 'You are not authorized to make this order transition.');
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

        $finalStatus = $target === OrderStatus::Delivered ? 'completed' : 'returned';
        $activeAssignment->update([
            'status' => $finalStatus,
            'active_order_id' => null,
            'released_at' => now(),
            'completed_at' => $target === OrderStatus::Delivered ? now() : null,
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
