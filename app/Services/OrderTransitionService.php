<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
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
}
