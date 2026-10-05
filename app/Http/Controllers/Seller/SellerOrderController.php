<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use App\Services\LogisticsHubNotificationService;
use App\Services\OrderTransitionService;
use App\Services\ParcelLabelService;
use App\Services\QrCodeService;
use App\Services\RiderBadgeService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerOrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $query = $this->authenticatedUser()->sellerOrders()
            ->with(['buyer', 'items'])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(10)->withQueryString();

        $counts = [
            'all' => $this->authenticatedUser()->sellerOrders()->count(),
            'placed' => $this->authenticatedUser()->sellerOrders()->where('status', 'PLACED')->count(),
            'preparing' => $this->authenticatedUser()->sellerOrders()->whereIn('status', ['CONFIRMED', 'PREPARING'])->count(),
            'ready' => $this->authenticatedUser()->sellerOrders()->where('status', 'READY_FOR_PICKUP')->count(),
            'completed' => $this->authenticatedUser()->sellerOrders()->where('status', 'COMPLETED')->count(),
        ];

        return view('seller.orders.index', compact('orders', 'counts'));
    }

    public function show(Order $order)
    {
        abort_if($order->seller_id !== $this->authenticatedUser()->id, 403);

        $order->load(['buyer', 'items.product']);

        return view('seller.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order, OrderTransitionService $transitions)
    {
        abort_if($order->seller_id !== $this->authenticatedUser()->id, 403);

        $validated = $request->validate(['status' => 'required|in:CONFIRMED,PREPARING,READY_FOR_PICKUP']);
        $actor = $this->authenticatedUser();

        $transitions->transition(
            $order,
            $actor,
            OrderStatus::from($validated['status']),
            strtolower($validated['status']),
            $actor->municipality,
            'Seller updated order fulfillment status.',
        );

        return back()->with('success', 'Order status updated to '.str_replace('_', ' ', $validated['status']).'.');
    }

    public function cancel(Request $request, Order $order, OrderTransitionService $transitions)
    {
        $seller = $this->authenticatedUser();
        abort_if($order->seller_id !== $seller->id, 403);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $transitions->transition(
            $order,
            $seller,
            OrderStatus::Cancelled,
            'seller_cancelled',
            $seller->municipality,
            'Seller cancelled the order: '.$validated['reason'],
        );

        return back()->with('success', 'Order cancelled and reserved inventory restored.');
    }

    public function confirmReturn(Request $request, Order $order, OrderTransitionService $transitions, RiderBadgeService $badges)
    {
        $actor = $this->authenticatedUser();
        abort_if($order->seller_id !== $actor->id, 403);
        $validated = $request->validate([
            'rider_badge' => ['required', 'string', 'max:100'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $rider = User::query()->find($order->delivery_courier_id);
        $submittedBadge = str_starts_with($validated['rider_badge'], 'EZR:')
            ? substr($validated['rider_badge'], 4)
            : $validated['rider_badge'];
        $badgeMatches = $rider !== null
            && $rider->role === 'courier'
            && hash_equals($badges->ensure($rider), $submittedBadge);

        if (! $badgeMatches) {
            DB::table('scan_events')->insert([
                'order_id' => $order->id,
                'actor_id' => $actor->id,
                'station' => 'seller_return_receipt',
                'result' => 'rejected',
                'failure_reason' => 'Scanned rider badge did not match the rider assigned to the return.',
                'method' => $validated['method'] ?? 'manual',
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);

            throw ValidationException::withMessages(['rider_badge' => 'The scanned badge does not match the rider assigned to this return.']);
        }

        DB::transaction(function () use ($request, $order, $actor, $transitions, $validated, $rider): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->return_handed_to_seller_at !== null, 422, 'The courier must record the return handoff before you confirm receipt.');
            abort_unless((int) $lockedOrder->delivery_courier_id === $rider->id, 422, 'The return assignment changed after the rider badge scan. Scan the current badge again.');
            $transitions->transition($lockedOrder, $actor, OrderStatus::ReturnedToSeller, 'returned_to_seller', $actor->municipality, 'Seller confirmed receipt of the returned parcel.');
            DB::table('scan_events')->insert([
                'order_id' => $lockedOrder->id,
                'actor_id' => $actor->id,
                'station' => 'seller_return_receipt',
                'result' => 'accepted',
                'method' => $validated['method'] ?? 'manual',
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Return receipt confirmed and inventory restored.');
    }

    public function schedulePickup(Request $request, Order $order, TransactionAwareNotificationSender $notifications, LogisticsHubNotificationService $hubNotifications)
    {
        $seller = $this->authenticatedUser();
        abort_if($order->seller_id !== $seller->id, 403);

        $validated = $request->validate([
            'pickup_scheduled_for' => ['required', 'date', 'after:now'],
            'pickup_window' => ['required', 'string', 'max:80'],
            'pickup_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($order, $seller, $validated, $notifications, $hubNotifications) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $lockedOrder->status === 'READY_FOR_PICKUP'
                    && $lockedOrder->payment_method === 'COD'
                    && $lockedOrder->pickup_requested_at === null
                    && $lockedOrder->pickup_courier_id === null,
                422,
                'This order is not eligible for pickup scheduling.',
            );

            $lockedOrder->forceFill([
                'pickup_requested_at' => now(),
                'pickup_scheduled_for' => $validated['pickup_scheduled_for'],
                'pickup_window' => $validated['pickup_window'],
                'pickup_notes' => $validated['pickup_notes'] ?? null,
            ])->save();
            ParcelTrackingEvent::create([
                'order_id' => $lockedOrder->id,
                'actor_id' => $seller->id,
                'event_type' => 'pickup_requested',
                'status' => $lockedOrder->status,
                'location' => implode(', ', array_filter([$seller->street_address, $seller->municipality, $seller->province])),
                'notes' => 'Seller requested pickup for '.$lockedOrder->pickup_scheduled_for->format('M j, Y g:i A').' ('.$lockedOrder->pickup_window.').',
            ]);
            $hubNotifications->sendForOrder($lockedOrder, $notifications, new OrderWorkflowNotification($lockedOrder, 'pickup_requested', 'A seller requested pickup for an order.'));

            return back()->with('success', 'Pickup request sent to Logistics.');
        });
    }

    public function confirmHandover(Request $request, Order $order, TransactionAwareNotificationSender $notifications, RiderBadgeService $badges): RedirectResponse
    {
        $seller = $this->authenticatedUser();
        abort_if($order->seller_id !== $seller->id, 403);
        $validated = $request->validate([
            'rider_badge' => ['required', 'string', 'max:100'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $badgeRiderId = null;

        if (filled($validated['rider_badge'])) {
            $assignedRider = User::query()->find($order->pickup_courier_id);
            $submittedBadge = str_starts_with($validated['rider_badge'], 'EZR:')
                ? substr($validated['rider_badge'], 4)
                : $validated['rider_badge'];
            $badgeMatches = $assignedRider !== null
                && $assignedRider->role === 'courier'
                && hash_equals($badges->ensure($assignedRider), $submittedBadge);

            if (! $badgeMatches) {
                DB::table('scan_events')->insert([
                    'order_id' => $order->id,
                    'actor_id' => $seller->id,
                    'station' => 'seller_handover',
                    'result' => 'rejected',
                    'failure_reason' => 'The scanned rider badge did not match the assigned pickup rider.',
                    'method' => $validated['method'] ?? 'manual',
                    'ip' => $request->ip(),
                    'created_at' => now(),
                ]);

                throw ValidationException::withMessages([
                    'rider_badge' => 'The scanned badge does not match the assigned pickup rider.',
                ]);
            }

            $badgeRiderId = $assignedRider->id;
        }

        return DB::transaction(function () use ($order, $seller, $notifications, $validated, $badgeRiderId, $request): RedirectResponse {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $lockedOrder->status === 'READY_FOR_PICKUP'
                    && $lockedOrder->payment_method === 'COD'
                    && $lockedOrder->pickup_courier_id !== null
                    && $lockedOrder->pickup_arrived_at !== null
                    && $lockedOrder->seller_handover_at === null,
                422,
                'Seller handover is not ready to confirm.',
            );
            abort_unless((int) $lockedOrder->pickup_courier_id === $badgeRiderId, 422, 'The pickup assignment changed after the badge scan. Scan the current rider badge again.');
            $lockedOrder->forceFill(['seller_handover_at' => now()])->save();
            DB::table('scan_events')->insert([
                'order_id' => $lockedOrder->id,
                'actor_id' => $seller->id,
                'station' => 'seller_handover',
                'result' => 'accepted',
                'method' => $validated['method'] ?? 'manual',
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);
            ParcelTrackingEvent::create([
                'order_id' => $lockedOrder->id,
                'actor_id' => $seller->id,
                'event_type' => 'seller_handover_confirmed',
                'status' => $lockedOrder->status,
                'location' => $seller->municipality,
                'notes' => 'Seller confirmed releasing the parcel to the assigned rider.',
            ]);
            $notifications->send($lockedOrder->pickupCourier, new OrderWorkflowNotification($lockedOrder, 'seller_handover_confirmed', 'The seller confirmed parcel handover. Confirm possession to complete pickup.'));

            return back()->with('success', 'Handover recorded. The rider must confirm possession to complete pickup.');
        });
    }

    public function waybill(Order $order, QrCodeService $qrCodes)
    {
        abort_if($order->seller_id !== $this->authenticatedUser()->id, 403);

        $order->load(['seller', 'buyer', 'items']);
        $parcelQr = $qrCodes->svg('EZP:'.$order->parcel_code);

        return view('seller.orders.waybill', compact('order', 'parcelQr'));
    }

    public function reprintWaybill(Request $request, Order $order, ParcelLabelService $labels): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $seller = $this->authenticatedUser();
        $labels->reprint($order, $seller, $validated['reason']);

        return redirect()->route('seller.orders.waybill', $order)->with('success', 'A new parcel label code was issued. Any previous label code is now invalid.');
    }
}
