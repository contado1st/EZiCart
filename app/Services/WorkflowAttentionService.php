<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\StaffProfileRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class WorkflowAttentionService
{
    public function __construct(private OrderProgressService $progress, private HubOrderScope $hubs) {}

    public function payload(User $user, array $visibleIds = []): array
    {
        $items = [];
        $count = 0;
        if ($user->isCommerceAdmin() || $user->isLogisticsAdmin()) {
            $groups = [
                ['query' => User::whereIn('role', $user->managedAccountRoles())->where('status', 'pending'), 'label' => 'Review account applications', 'route' => $user->isCommerceAdmin() ? 'admin.registrations.index' : 'logistics-admin.dashboard'],
                ['query' => StaffProfileRevision::where('status', 'pending')->whereHas('user', fn ($q) => $q->whereIn('role', $user->managedAccountRoles())), 'label' => 'Review profile change requests', 'route' => $user->isCommerceAdmin() ? 'admin.profile-reviews.index' : 'logistics-admin.profile-reviews.index'],
            ];
            if ($user->isCommerceAdmin()) {
                $groups[] = ['query' => Product::where('compliance_status', 'pending_review')->where('is_archived', false), 'label' => 'Review products awaiting publication', 'route' => 'admin.compliance.products.index'];
                $groups[] = ['query' => DB::table('disputes')->whereIn('status', ['PENDING', 'UNDER_REVIEW']), 'label' => 'Review open disputes', 'route' => 'admin.disputes.index'];
            } else {
                $groups[] = ['query' => DB::table('logistics_exceptions')->where('status', 'OPEN'), 'label' => 'Review logistics recovery exceptions', 'route' => 'logistics-admin.dashboard'];
                $groups[] = ['query' => User::where('role', 'courier')->where('status', 'approved')->where(fn ($q) => $q->whereNull('vehicle_capacity_verified_at')->orWhereNull('vehicle_capacity_grams')), 'label' => 'Verify rider vehicle cargo capacities', 'route' => 'load-planning.index'];
            }
            foreach ($groups as $group) {
                $total = $group['query']->count();
                $count += $total;
                if ($total) {
                    $items[] = ['label' => $group['label'], 'url' => route($group['route']), 'count' => $total, 'target' => null];
                }
            }
        } else {
            if ($user->role === 'courier' && $user->hub_transfer_only) {
                $transfers = DB::table('parcel_transfers')->where('carrier_rider_id', $user->id)->where('status', 'IN_TRANSIT')->count();

                return ['count' => $transfers, 'items' => $transfers ? [['label' => 'Deliver assigned hub transfers to their destination', 'url' => route('parcel-transfers.index'), 'count' => $transfers, 'target' => null]] : [], 'progress' => []];
            }
            $query = $this->candidates($user);
            $count = (clone $query)->count();
            foreach ($query->with(['pendingCancellation', 'activeTransfer', 'deliveryAssignments', 'dispute', 'items', 'reviews'])->oldest('orders.id')->limit(20)->get() as $order) {
                $state = $this->progress->forViewer($order, $user);
                $target = $state['action_route'] && Route::has($state['action_route']) ? route($state['action_route'], ['order' => $order->id]) : null;
                $items[] = ['order_id' => $order->id, 'label' => $order->order_number.': '.$state['message'], 'url' => $this->orderUrl($user, $order), 'target' => $target, 'count' => 1];
            }
            if ($user->role === 'sorting_center') {
                $transfers = DB::table('parcel_transfers')->where(fn ($q) => $q
                    ->where(fn ($s) => $s->where('source_hub_id', $user->id)->where('status', 'PLANNED'))
                    ->orWhere(fn ($s) => $s->where('destination_hub_id', $user->id)->where('status', 'IN_TRANSIT')))->count();
                $count += $transfers;
                if ($transfers) {
                    $items[] = ['label' => 'Scan and complete hub transfers', 'url' => route('parcel-transfers.index'), 'count' => $transfers, 'target' => null];
                }
            }
        }
        $states = [];
        if ($visibleIds !== []) {
            foreach ($this->scoped($user)->whereIn('orders.id', $visibleIds)->with(['pendingCancellation', 'activeTransfer', 'deliveryAssignments', 'dispute', 'items', 'reviews'])->get() as $order) {
                $states[$order->id] = $this->progress->forViewer($order, $user);
            }
        }

        return ['count' => $count, 'items' => $items, 'progress' => $states];
    }

    private function scoped(User $user): Builder
    {
        return match ($user->role) {
            'buyer' => Order::where('buyer_id', $user->id),
            'seller' => Order::where('seller_id', $user->id),
            'courier' => Order::where(fn ($q) => $q->where('pickup_courier_id', $user->id)->orWhere('delivery_courier_id', $user->id)),
            'sorting_center' => $this->hubs->query($user),
            'admin', 'ecommerce_admin', 'logistics_admin' => Order::query(),
            default => Order::whereRaw('1 = 0'),
        };
    }

    private function candidates(User $user): Builder
    {
        $query = $this->scoped($user)->where('payment_method', 'COD')->whereDoesntHave('activeTransfer')
            ->whereDoesntHave('dispute', fn ($q) => $q->whereIn('status', ['PENDING', 'UNDER_REVIEW']));
        if ($user->role !== 'seller') {
            $query->whereDoesntHave('pendingCancellation');
        }

        return $query->where(function (Builder $q) use ($user): void {
            match ($user->role) {
                'seller' => $q->whereHas('pendingCancellation')->orWhereIn('status', ['PLACED', 'CONFIRMED', 'PREPARING'])
                    ->orWhere(fn ($r) => $r->where('status', 'READY_FOR_PICKUP')->whereNull('parcel_weight_grams'))
                    ->orWhere(fn ($r) => $r->where('status', 'READY_FOR_PICKUP')->where(fn ($s) => $s->whereNull('pickup_requested_at')->orWhere(fn ($t) => $t->whereNotNull('pickup_arrived_at')->whereNull('seller_handover_at'))))
                    ->orWhere(fn ($r) => $r->where('status', 'RETURN_IN_TRANSIT')->whereNotNull('return_handed_to_seller_at')),
                'buyer' => $q->where('status', 'DELIVERED')->orWhere(fn ($r) => $r->where('status', 'COMPLETED')->whereHas('items', fn ($i) => $i->whereNotExists(fn ($review) => $review->selectRaw('1')->from('reviews')->whereColumn('reviews.order_id', 'order_items.order_id')->whereColumn('reviews.product_id', 'order_items.product_id')))),
                'courier' => $q->where(fn ($r) => $r->where('pickup_courier_id', $user->id)->where(fn ($s) => $s->where('status', 'PICKED_UP')->orWhere(fn ($t) => $t->where('status', 'READY_FOR_PICKUP')->whereNotNull('parcel_weight_grams')->where(fn ($a) => $a->whereNull('pickup_claimed_at')->orWhereNull('pickup_arrived_at')->orWhereNotNull('seller_handover_at')))))
                    ->orWhere(fn ($r) => $r->where('delivery_courier_id', $user->id)->where(fn ($s) => $s->where('status', 'OUT_FOR_DELIVERY')
                        ->orWhere(fn ($t) => $t->where('status', 'RETURN_IN_TRANSIT')->whereNull('return_handed_to_seller_at'))
                        ->orWhere(fn ($t) => $t->where('status', 'ASSIGNED_TO_RIDER')->where(fn ($a) => $a->whereNotNull('hub_released_at')->orWhereHas('deliveryAssignments', fn ($assignment) => $assignment->where('status', 'active')->whereNull('accepted_at')))))),
                'sorting_center' => $q->whereIn('status', ['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'DELIVERY_FAILED'])
                    ->orWhere(fn ($r) => $r->where('status', 'READY_FOR_PICKUP')->whereNotNull('parcel_weight_grams')->whereNotNull('pickup_requested_at')->whereNull('pickup_courier_id'))
                    ->orWhere(fn ($r) => $r->where('status', 'ASSIGNED_TO_RIDER')->whereNull('hub_released_at')->whereHas('deliveryAssignments', fn ($a) => $a->where('status', 'active')->whereNotNull('accepted_at'))),
                default => $q->whereRaw('1 = 0'),
            };
        });
    }

    private function orderUrl(User $user, Order $order): string
    {
        return match ($user->role) {
            'buyer' => route('buyer.orders.show', $order), 'seller' => route('seller.orders.show', $order), 'courier' => route('courier.orders.show', $order),
            'sorting_center' => route(match ($order->status) {
                'READY_FOR_PICKUP' => 'logistics.pickupRequests', 'PICKED_UP' => 'logistics.intake', 'AT_SORTING_CENTER' => 'logistics.sorting', 'SORTED', 'ASSIGNED_TO_RIDER' => 'logistics.dispatch', default => 'logistics.tracking',
            }, ['search' => $order->order_number]),
            default => route('parcel-evidence.show', $order),
        };
    }
}
