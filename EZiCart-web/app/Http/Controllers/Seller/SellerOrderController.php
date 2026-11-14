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
use App\Services\OrderWalletService;
use App\Services\ParcelIncidentService;
use App\Services\ParcelLabelService;
use App\Services\ParcelLoadService;
use App\Services\QrCodeService;
use App\Services\RiderBadgeService;
use App\Services\TransactionAwareNotificationSender;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SellerOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        if (filled($filters['from_date'] ?? null) && filled($filters['to_date'] ?? null) && $filters['from_date'] > $filters['to_date']) {
            throw ValidationException::withMessages(['to_date' => 'The end date must not precede the start date.']);
        }
        $base = Order::query()->where('seller_id', $this->authenticatedUser()->id)
            ->when(filled($filters['from_date'] ?? null), fn ($q) => $q->where('created_at', '>=', $filters['from_date'].' 00:00:00'))
            ->when(filled($filters['to_date'] ?? null), fn ($q) => $q->where('created_at', '<', CarbonImmutable::parse($filters['to_date'])->addDay()->startOfDay()));
        if (filled($filters['q'] ?? null)) {
            // Explicit escape character works on MySQL and SQLite; user wildcards are literal text.
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['q'])).'%';
            $base->where(fn ($q) => $q->whereRaw("order_number LIKE ? ESCAPE '!'", [$term])
                ->orWhereHas('buyer', fn ($buyer) => $buyer->where(fn ($names) => $names
                    ->whereRaw("first_name LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("last_name LIKE ? ESCAPE '!'", [$term])))
                ->orWhereRaw("recipient_name LIKE ? ESCAPE '!'", [$term]));
        }
        $byStatus = (clone $base)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $counts = ['all' => (int) $byStatus->sum(), 'placed' => (int) $byStatus->get('PLACED', 0),
            'preparing' => (int) $byStatus->get('CONFIRMED', 0) + (int) $byStatus->get('PREPARING', 0),
            'ready' => (int) $byStatus->get('READY_FOR_PICKUP', 0), 'completed' => (int) $byStatus->get('COMPLETED', 0)];
        $query = clone $base;
        if (filled($filters['status'] ?? null)) {
            $filters['status'] === 'PREPARING' ? $query->whereIn('status', ['CONFIRMED', 'PREPARING']) : $query->where('status', $filters['status']);
        }
        $orders = $query->with(['buyer', 'items', 'pendingCancellation'])->latest('created_at')->latest('id')->paginate(25)->withQueryString();

        return view('seller.orders.index', compact('orders', 'counts', 'filters'));
    }

    public function show(Order $order)
    {
        abort_if($order->seller_id !== $this->authenticatedUser()->id, 403);

        $order->load(['buyer', 'items.product', 'dispute.sellerResponse']);

        return view('seller.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order, OrderTransitionService $transitions)
    {
        abort_if($order->seller_id !== $this->authenticatedUser()->id, 403);

        $validated = $request->validate(['status' => 'required|in:CONFIRMED,PREPARING,READY_FOR_PICKUP']);
        $actor = $this->authenticatedUser();

        try {
            $transitions->transition(
                $order,
                $actor,
                OrderStatus::from($validated['status']),
                strtolower($validated['status']),
                $actor->municipality,
                'Seller updated order fulfillment status.',
            );
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 422 || $request->expectsJson()) {
                throw $exception;
            }

            $order->refresh();

            return redirect()->route('seller.orders.show', $order)->withErrors([
                'status' => $exception->getMessage().' Current order status: '.str_replace('_', ' ', $order->status).'. Review the available action below.',
            ]);
        }

        return redirect()->route('seller.orders.show', $order)->with('success', 'Order status updated to '.str_replace('_', ' ', $validated['status']).'.');
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
            ParcelIncidentService::assertClear($lockedOrder);
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

        return back()->with('success', 'Return receipt confirmed. Inspect the items before restocking.');
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
            ParcelIncidentService::assertClear($lockedOrder);
            abort_unless(
                $lockedOrder->status === 'READY_FOR_PICKUP'
                    && app(OrderWalletService::class)->paymentReady($lockedOrder)
                    && $lockedOrder->pickup_requested_at === null
                    && $lockedOrder->pickup_courier_id === null,
                422,
                'This order is not eligible for pickup scheduling.',
            );

            app(ParcelLoadService::class)->requireWeight($lockedOrder);
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
            $notifications->send([$lockedOrder->buyer, $lockedOrder->seller], new OrderWorkflowNotification($lockedOrder, 'pickup_requested', 'The seller scheduled pickup. The parcel is awaiting logistics assignment and rider collection.'));

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
            if ($order->pickup_courier_id) {
                app(ParcelLoadService::class)->lockVehicle((int) $order->pickup_courier_id);
            }
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            ParcelIncidentService::assertClear($lockedOrder);
            abort_if($lockedOrder->pendingCancellation()->exists(), 422, 'Resolve the pending cancellation request before parcel handover.');
            abort_unless(
                $lockedOrder->status === 'READY_FOR_PICKUP'
                    && app(OrderWalletService::class)->paymentReady($lockedOrder)
                    && $lockedOrder->pickup_courier_id !== null
                    && $lockedOrder->pickup_arrived_at !== null
                    && $lockedOrder->seller_handover_at === null,
                422,
                'Seller handover is not ready to confirm.',
            );
            abort_unless((int) $lockedOrder->pickup_courier_id === $badgeRiderId, 422, 'The pickup assignment changed after the badge scan. Scan the current rider badge again.');
            app(ParcelLoadService::class)->assertFits($lockedOrder, $lockedOrder->pickupCourier);
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
            $notifications->send([$lockedOrder->pickupCourier, $lockedOrder->buyer], new OrderWorkflowNotification($lockedOrder, 'seller_handover_confirmed', 'The seller confirmed parcel handover. The rider must scan the parcel to confirm possession and complete pickup.'));

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
