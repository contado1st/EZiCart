<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Placed = 'PLACED';
    case Confirmed = 'CONFIRMED';
    case Preparing = 'PREPARING';
    case ReadyForPickup = 'READY_FOR_PICKUP';
    case PickedUp = 'PICKED_UP';
    case AtSortingCenter = 'AT_SORTING_CENTER';
    case Sorted = 'SORTED';
    case AssignedToRider = 'ASSIGNED_TO_RIDER';
    case OutForDelivery = 'OUT_FOR_DELIVERY';
    case DeliveryFailed = 'DELIVERY_FAILED';
    case Delivered = 'DELIVERED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case ReturnInTransit = 'RETURN_IN_TRANSIT';
    case ReturnedToSeller = 'RETURNED_TO_SELLER';
}
