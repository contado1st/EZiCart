<?php

namespace App\Http\Controllers\Logistics;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\DeliveryAttempt;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\AccountStatusNotification;
use App\Notifications\OrderWorkflowNotification;
use App\Services\LogisticsExceptionService;
use App\Services\OrderAreaService;
use App\Services\OrderTransitionService;
use App\Services\QrCodeService;
use App\Services\RiderBadgeService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class LogisticsController extends Controller
{
    public function dashboard(): View
    {
        $operator = $this->authenticatedUser();
        $todayStart = today()->startOfDay();
        $tomorrowStart = today()->addDay()->startOfDay();
        $hubAreaIds = Area::query()->where(fn (Builder $query) => $query->where('sorting_center_id', $operator->id)
            ->when(! $this->isMultiHubOperation(), fn (Builder $legacy) => $legacy->orWhereNull('sorting_center_id')))
            ->pluck('id')->all();
        $stats = Cache::remember(
            'logistics.dashboard.stats.'.$operator->id.'.'.today()->toDateString(),
            now()->addSeconds(max(1, (int) config('logistics.dashboard_cache_seconds', 20))),
            fn (): array => [
                'inbound' => $this->hubOrders($operator)->where('status', 'PICKED_UP')->count(),
                'at_center' => $this->hubOrders($operator)->where('status', 'AT_SORTING_CENTER')->count(),
                'sorted' => $this->hubOrders($operator)->where('status', 'SORTED')->count(),
                'assigned' => $this->hubOrders($operator)->where('status', 'ASSIGNED_TO_RIDER')->count(),
                'out' => $this->hubOrders($operator)->where('status', 'OUT_FOR_DELIVERY')->count(),
                'delivered_today' => $this->hubOrders($operator)->whereIn('status', ['DELIVERED', 'COMPLETED'])->whereBetween('delivered_at', [$todayStart, $tomorrowStart])->count(),
                'failed' => $this->hubOrders($operator)->where('status', 'DELIVERY_FAILED')->count(),
                'returned' => $this->hubOrders($operator)->whereIn('status', ['RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'])->count(),
                'active_riders' => User::query()->where('role', 'courier')->where('status', 'approved')
                    ->whereHas('serviceAreas', fn (Builder $query) => $query->whereIn('areas.id', $hubAreaIds))->count(),
                'available_riders' => User::query()->where('role', 'courier')->where('status', 'approved')
                    ->whereHas('serviceAreas', fn (Builder $query) => $query->whereIn('areas.id', $hubAreaIds))
                    ->whereDoesntHave('finalDeliveries', fn (Builder $query) => $query->activeCourierWorkload())->count(),
            ],
        );

        $queues = [
            'inbound' => $this->hubOrders($operator)->with(['seller', 'pickupCourier'])->where('status', 'PICKED_UP')->latest()->limit(8)->get(),
            'sorting' => $this->hubOrders($operator)->with('buyer')->where('status', 'AT_SORTING_CENTER')->latest()->limit(8)->get(),
            'dispatch' => $this->hubOrders($operator)->with(['buyer', 'deliveryCourier'])->where('status', 'SORTED')->latest()->limit(8)->get(),
            'failed' => $this->hubOrders($operator)->with(['buyer', 'deliveryCourier'])->where('status', 'DELIVERY_FAILED')->latest()->limit(5)->get(),
        ];
        $exceptions = DB::table('logistics_exceptions')
            ->join('orders', 'orders.id', '=', 'logistics_exceptions.order_id')
            ->join('users as riders', 'riders.id', '=', 'logistics_exceptions.previous_rider_id')
            ->where('logistics_exceptions.status', 'OPEN')
            ->where(function ($query) use ($operator): void {
                $query->whereNull('orders.sorting_center_id')->orWhere('orders.sorting_center_id', $operator->id);
            })
            ->orderByDesc('logistics_exceptions.created_at')
            ->limit(25)
            ->get([
                'logistics_exceptions.id', 'logistics_exceptions.order_id', 'logistics_exceptions.reason',
                'logistics_exceptions.type',
                'orders.order_number', 'orders.status as order_status', 'orders.hub_released_at',
                'riders.first_name as rider_first_name', 'riders.last_name as rider_last_name',
            ]);

        return view('logistics.dashboard', compact('stats', 'queues', 'exceptions'));
    }

    public function intake(Request $request): View
    {
        $parcels = $this->hubOrders($this->authenticatedUser())->with(['seller', 'pickupCourier'])
            ->where('status', 'PICKED_UP')
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $query->whereRaw("order_number LIKE ? ESCAPE '!'", [$this->orderNumberSearchPattern($request->string('search')->toString())]);
            })
            ->latest()->paginate(15)->withQueryString();

        return view('logistics.intake', compact('parcels'));
    }

    public function pickupRequests(): View
    {
        $operator = $this->authenticatedUser();
        $orders = $this->hubOrders($operator)->with(['seller', 'items', 'pickupCourier'])
            ->where('status', 'READY_FOR_PICKUP')
            ->where('payment_method', 'COD')
            ->whereNotNull('pickup_requested_at')
            ->whereNull('pickup_claimed_at')
            ->latest()->paginate(20);
        $areaIds = $orders->getCollection()->pluck('destination_area_id')->filter()->unique()->all();
        $riders = User::query()->where('role', 'courier')->where('status', 'approved')
            ->whereHas('serviceAreas', fn (Builder $query) => $query->whereIn('areas.id', $areaIds))
            ->orderBy('first_name')->get();

        return view('logistics.pickup-requests', compact('orders', 'riders'));
    }

    public function assignPickup(Request $request, Order $order, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        $validated = $request->validate(['pickup_courier_id' => ['required', 'integer', 'exists:users,id']]);
        $operator = $this->authenticatedUser();
        abort_unless($this->hubOrders($operator)->whereKey($order->id)->exists(), 403, 'This pickup belongs to another sorting center.');

        return DB::transaction(function () use ($order, $validated, $operator, $notifications): RedirectResponse {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $lockedOrder->status === 'READY_FOR_PICKUP'
                    && $lockedOrder->payment_method === 'COD'
                    && $lockedOrder->pickup_requested_at !== null
                    && $lockedOrder->pickup_arrived_at === null
                    && $lockedOrder->seller_handover_at === null,
                422,
                'This pickup request is no longer available for assignment.',
            );
            $rider = User::query()->whereKey($validated['pickup_courier_id'])->lockForUpdate()->firstOrFail();
            abort_unless($rider->role === 'courier' && $rider->status === 'approved', 422, 'Choose an approved rider.');
            $previousRider = $lockedOrder->pickupCourier;
            abort_unless($lockedOrder->pickup_claimed_at === null || $previousRider?->status === 'suspended', 422, 'The accepted pickup must be released by its unavailable rider before reassignment.');
            abort_if($previousRider?->id === $rider->id, 422, 'Choose a different rider for reassignment.');

            $lockedOrder->forceFill(['pickup_courier_id' => $rider->id])->save();
            if ($previousRider !== null) {
                DB::table('logistics_exceptions')
                    ->where('order_id', $lockedOrder->id)
                    ->where('previous_rider_id', $previousRider->id)
                    ->where('status', 'OPEN')
                    ->update([
                        'new_rider_id' => $rider->id,
                        'status' => 'RESOLVED',
                        'resolution' => 'Accepted pickup was reassigned before seller handoff.',
                        'resolved_by' => $operator->id,
                        'resolved_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
            ParcelTrackingEvent::create([
                'order_id' => $lockedOrder->id,
                'actor_id' => $operator->id,
                'event_type' => $previousRider === null ? 'pickup_assigned' : 'pickup_reassigned',
                'status' => $lockedOrder->status,
                'location' => $lockedOrder->seller?->municipality,
                'notes' => $previousRider === null
                    ? "Pickup assigned to {$rider->first_name} {$rider->last_name}."
                    : "Pickup reassigned from {$previousRider->first_name} {$previousRider->last_name} to {$rider->first_name} {$rider->last_name} before rider acceptance.",
            ]);
            if ($previousRider !== null) {
                $notifications->send($previousRider, new OrderWorkflowNotification($lockedOrder, 'pickup_reassigned', 'Logistics reassigned this pickup before you accepted it.'));
            }
            $notifications->send($rider, new OrderWorkflowNotification($lockedOrder, 'pickup_assigned', 'Logistics assigned you a seller pickup.'));
            $notifications->send($lockedOrder->seller, new OrderWorkflowNotification(
                $lockedOrder,
                $previousRider === null ? 'pickup_assigned' : 'pickup_reassigned',
                $previousRider === null ? 'Logistics assigned a rider to your pickup request.' : 'Logistics reassigned the rider for your pickup request.',
            ));

            return back()->with('success', "Pickup for {$lockedOrder->order_number} assigned to {$rider->first_name} {$rider->last_name}.");
        });
    }

    public function scan(Request $request, OrderTransitionService $transitions, OrderAreaService $areaService): RedirectResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'location_reference' => ['nullable', 'string', 'max:100'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $reference = $validated['reference'];
        if (str_starts_with($reference, 'EZP:')) {
            $reference = substr($reference, 4);
        }
        $order = Order::query()->where('parcel_code', $reference)->orWhere('order_number', $reference)->first();

        $recoveringSuspendedRiderParcel = $order?->status === OrderStatus::OutForDelivery->value
            && $order->deliveryCourier?->status === 'suspended';
        if ($order?->status === OrderStatus::DeliveryFailed->value || $recoveringSuspendedRiderParcel) {
            $scanStation = $recoveringSuspendedRiderParcel ? 'rider_exception_intake' : 'failed_return_intake';
            $locationReference = $validated['location_reference'] ?? '';
            if (blank($locationReference)) {
                DB::table('scan_events')->insert([
                    'order_id' => $order->id,
                    'actor_id' => $this->authenticatedUser()->id,
                    'station' => $scanStation,
                    'result' => 'rejected',
                    'failure_reason' => 'A Returns or Exception location scan is required.',
                    'method' => $validated['method'] ?? 'manual',
                    'ip' => $request->ip(),
                    'created_at' => now(),
                ]);

                return back()->withErrors(['location_reference' => 'Scan a Returns or Exception location before receiving this failed parcel.']);
            }

            $locationCode = str_starts_with($locationReference, 'EZL:') ? substr($locationReference, 4) : $locationReference;
            try {
                $transitions->receiveFailedDeliveryAtHub($order, $this->authenticatedUser(), $locationCode, $validated['method'] ?? 'manual', $request->ip());
            } catch (HttpException $exception) {
                if (! in_array($exception->getStatusCode(), [403, 422], true)) {
                    throw $exception;
                }

                DB::table('scan_events')->insert([
                    'order_id' => $order->id,
                    'actor_id' => $this->authenticatedUser()->id,
                    'station' => $scanStation,
                    'result' => 'rejected',
                    'failure_reason' => 'Return intake validation was rejected.',
                    'method' => $validated['method'] ?? 'manual',
                    'ip' => $request->ip(),
                    'created_at' => now(),
                ]);

                return back()->withErrors(['reference' => $exception->getMessage()]);
            }

            return back()->with('success', "Parcel {$order->order_number} checked into the hub location. Logistics may now review dispatch or seller return.");
        }

        if (! $order || $order->status !== OrderStatus::PickedUp->value) {
            DB::table('scan_events')->insert([
                'order_id' => $order?->id,
                'actor_id' => $this->authenticatedUser()->id,
                'station' => 'intake',
                'result' => 'rejected',
                'failure_reason' => 'Unknown parcel or parcel is not eligible for intake.',
                'method' => $validated['method'] ?? 'manual',
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);

            return back()->withErrors(['reference' => 'The parcel reference could not be processed.']);
        }

        try {
            $response = $this->receiveParcel($order, $transitions, $areaService, $validated['method'] ?? 'manual');
            DB::table('scan_events')->insert([
                'order_id' => $order->id,
                'actor_id' => $this->authenticatedUser()->id,
                'station' => 'intake',
                'result' => 'accepted',
                'failure_reason' => null,
                'method' => $validated['method'] ?? 'manual',
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);

            return $response;
        } catch (HttpException $exception) {
            if (! in_array($exception->getStatusCode(), [403, 422], true)) {
                throw $exception;
            }

            DB::table('scan_events')->insert([
                'order_id' => $order->id,
                'actor_id' => $this->authenticatedUser()->id,
                'station' => 'intake',
                'result' => 'rejected',
                'failure_reason' => 'Parcel intake transition was rejected.',
                'method' => $validated['method'] ?? 'manual',
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);

            return back()->withErrors(['reference' => 'The parcel reference could not be processed.']);
        }
    }

    public function receiveParcel(Order $order, OrderTransitionService $transitions, OrderAreaService $areaService, string $method = 'manual'): RedirectResponse
    {
        $center = $this->authenticatedUser();
        DB::transaction(function () use ($order, $center, $transitions, $areaService, $method): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->status === OrderStatus::PickedUp->value, 422, 'This parcel is not eligible for hub intake.');
            abort_unless($lockedOrder->sorting_center_id === null || (int) $lockedOrder->sorting_center_id === $center->id, 403, 'This parcel belongs to another sorting center.');

            try {
                $area = $areaService->resolve($lockedOrder);
            } catch (HttpException $exception) {
                if ($exception->getStatusCode() !== 422 || $this->isMultiHubOperation()) {
                    throw $exception;
                }

                $area = null;
            }

            if ($area !== null) {
                $area = Area::query()->whereKey($area->id)->lockForUpdate()->firstOrFail();
                if ($area->sorting_center_id === null) {
                    abort_if($this->isMultiHubOperation(), 422, 'Assign this routing area to a hub before receiving its parcels.');
                    $area->forceFill(['sorting_center_id' => $center->id])->save();
                }
                abort_unless((int) $area->sorting_center_id === $center->id, 403, 'This parcel destination belongs to another sorting center.');
            }

            $transitions->transition(
                $lockedOrder,
                $center,
                OrderStatus::AtSortingCenter,
                'hub_received',
                $center->municipality,
                'Parcel received at sorting center by '.($method === 'manual' ? 'manual entry.' : $method.' scan.'),
                [
                    'sorting_center_id' => $center->id,
                    'received_at' => now(),
                ],
            );
        });

        return back()->with('success', "Parcel {$order->order_number} received at the hub.");
    }

    public function sorting(): View
    {
        $parcels = $this->hubOrders($this->authenticatedUser())->where('status', 'AT_SORTING_CENTER')
            ->with(['buyer', 'trackingEvents'])->latest()->paginate(15);

        return view('logistics.sorting', compact('parcels'));
    }

    public function storage(QrCodeService $qrCodes): View
    {
        $operator = $this->authenticatedUser();
        $locations = DB::table('storage_locations')
            ->leftJoinSub(
                DB::table('parcel_placements')->select('location_id', DB::raw('COUNT(*) as active_count'), DB::raw('MIN(placed_at) as oldest_placement_at'))
                    ->whereNull('removed_at')->groupBy('location_id'),
                'active_placements',
                'active_placements.location_id',
                '=',
                'storage_locations.id',
            )
            ->where('storage_locations.hub_id', $operator->id)
            ->orderBy('storage_locations.type')->orderBy('storage_locations.code')
            ->get(['storage_locations.*', 'active_placements.active_count', 'active_placements.oldest_placement_at']);

        $placements = DB::table('parcel_placements')
            ->join('storage_locations', 'storage_locations.id', '=', 'parcel_placements.location_id')
            ->join('orders', 'orders.id', '=', 'parcel_placements.order_id')
            ->where('storage_locations.hub_id', $operator->id)
            ->whereNull('parcel_placements.removed_at')
            ->orderBy('parcel_placements.placed_at')
            ->limit(200)
            ->get([
                'parcel_placements.id', 'parcel_placements.order_id', 'parcel_placements.placed_at',
                'storage_locations.code as location_code', 'storage_locations.label as location_label',
                'orders.order_number', 'orders.parcel_code',
            ]);
        $placements->each(function (object $placement): void {
            $placement->dwell_time = Carbon::parse($placement->placed_at)->diffForHumans();
        });
        $locations->each(function (object $location) use ($qrCodes): void {
            $location->qr_svg = $qrCodes->svg('EZL:'.$location->code, 120);
        });

        $areas = Area::query()->where('is_active', true)->orderBy('name')->get();

        return view('logistics.storage', compact('locations', 'placements', 'areas'));
    }

    public function scanHistory(Request $request): View
    {
        $validated = $request->validate([
            'station' => ['nullable', 'in:intake,seller_pickup,seller_handover,seller_return_receipt,putaway,pick,cycle_count,dispatch_release,dispatch_batch_rider,dispatch_batch_parcel,dispatch_batch_release,delivery_start,return_to_seller,rider_exception_intake,failed_return_intake,parcel_label_reprint'],
            'result' => ['nullable', 'in:accepted,rejected,correct,missing,unexpected'],
        ]);
        $operator = $this->authenticatedUser();
        $scans = DB::table('scan_events')
            ->leftJoin('orders', 'orders.id', '=', 'scan_events.order_id')
            ->leftJoin('users as scan_actors', 'scan_actors.id', '=', 'scan_events.actor_id')
            ->where(function (QueryBuilder $query) use ($operator): void {
                $query->where('orders.sorting_center_id', $operator->id)
                    ->orWhereExists(function ($areaQuery) use ($operator): void {
                        $areaQuery->selectRaw('1')->from('areas')
                            ->whereColumn('areas.id', 'orders.destination_area_id')
                            ->where('areas.sorting_center_id', $operator->id);
                    })
                    ->orWhere(function (QueryBuilder $unresolvedScan) use ($operator): void {
                        $unresolvedScan->whereNull('orders.id')->where('scan_events.actor_id', $operator->id);
                    });
            })
            ->when(isset($validated['station']), fn ($query) => $query->where('scan_events.station', $validated['station']))
            ->when(isset($validated['result']), fn ($query) => $query->where('scan_events.result', $validated['result']))
            ->orderByDesc('scan_events.created_at')->orderByDesc('scan_events.id')
            ->select([
                'scan_events.id', 'scan_events.order_id', 'scan_events.station', 'scan_events.result',
                'scan_events.failure_reason', 'scan_events.method', 'scan_events.created_at',
                'orders.order_number', 'scan_actors.first_name as actor_first_name', 'scan_actors.last_name as actor_last_name',
            ])
            ->paginate(50)->withQueryString();

        return view('logistics.scan-history', compact('scans'));
    }

    public function createStorageLocation(Request $request): RedirectResponse
    {
        $operator = $this->authenticatedUser();
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:RECEIVING,SORTING,STAGING,DISPATCH,EXCEPTION,RETURNS'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $code = 'LOC-'.strtoupper(Str::random(10));

        DB::table('storage_locations')->insert([
            'hub_id' => $operator->id,
            'code' => $code,
            'label' => $validated['label'],
            'type' => $validated['type'],
            'area_id' => $validated['area_id'] ?? null,
            'capacity' => $validated['capacity'] ?? 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', "Storage location {$code} created. Its QR payload is EZL:{$code}.");
    }

    public function putAwayParcel(Request $request): RedirectResponse
    {
        $operator = $this->authenticatedUser();
        $validated = $request->validate([
            'parcel_reference' => ['required', 'string', 'max:100'],
            'location_reference' => ['required', 'string', 'max:100'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $method = $validated['method'] ?? 'manual';
        $orderId = null;
        $locationId = null;

        try {
            DB::transaction(function () use ($validated, $operator, $method, &$orderId, &$locationId): void {
                $parcelCode = str_starts_with($validated['parcel_reference'], 'EZP:')
                    ? substr($validated['parcel_reference'], 4)
                    : $validated['parcel_reference'];
                $locationCode = str_starts_with($validated['location_reference'], 'EZL:')
                    ? substr($validated['location_reference'], 4)
                    : $validated['location_reference'];
                $order = Order::query()->where('parcel_code', $parcelCode)->orWhere('order_number', $parcelCode)->lockForUpdate()->firstOrFail();
                $location = DB::table('storage_locations')->where('hub_id', $operator->id)->where('code', $locationCode)->lockForUpdate()->first();
                abort_unless($location !== null && $location->is_active, 422, 'The storage location is unknown or inactive.');
                $orderId = $order->id;
                $locationId = $location->id;
                abort_unless((int) $order->sorting_center_id === $operator->id, 403, 'This parcel belongs to another sorting center.');
                abort_unless($order->status === 'SORTED' || ($order->status === 'AT_SORTING_CENTER' && in_array($location->type, ['RECEIVING', 'EXCEPTION'], true)), 422, 'This parcel cannot be placed at a location in its current stage.');
                abort_unless($location->type === 'EXCEPTION' || $location->area_id === null || (int) $location->area_id === (int) $order->destination_area_id, 422, 'The location area does not match this parcel route. Use an exception location for misrouted parcels.');

                $activeCount = DB::table('parcel_placements')->where('location_id', $location->id)->whereNull('removed_at')->lockForUpdate()->count();
                abort_if((int) $location->capacity > 0 && $activeCount >= (int) $location->capacity, 422, 'This location has reached its capacity.');
                abort_if(DB::table('parcel_placements')->where('active_order_id', $order->id)->exists(), 422, 'This parcel already has an active storage placement.');

                DB::table('parcel_placements')->insert([
                    'order_id' => $order->id,
                    'active_order_id' => $order->id,
                    'location_id' => $location->id,
                    'placed_by' => $operator->id,
                    'placed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('scan_events')->insert([
                    'order_id' => $order->id,
                    'location_id' => $location->id,
                    'actor_id' => $operator->id,
                    'station' => 'putaway',
                    'result' => 'accepted',
                    'method' => $method,
                    'ip' => request()->ip(),
                    'created_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            DB::table('scan_events')->insert([
                'order_id' => $orderId,
                'location_id' => $locationId,
                'actor_id' => $operator->id,
                'station' => 'putaway',
                'result' => 'rejected',
                'failure_reason' => 'Put-away validation or placement failed.',
                'method' => $method,
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);

            if ($exception instanceof HttpException) {
                return back()->withErrors(['parcel_reference' => $exception->getMessage()]);
            }

            if ($exception instanceof ModelNotFoundException) {
                return back()->withErrors(['parcel_reference' => 'The parcel reference could not be processed.']);
            }

            throw $exception;
        }

        return back()->with('success', 'Parcel placed in the scanned storage location.');
    }

    public function pickStoredParcel(Request $request): RedirectResponse
    {
        $operator = $this->authenticatedUser();
        $validated = $request->validate([
            'parcel_reference' => ['required', 'string', 'max:100'],
            'location_reference' => ['required', 'string', 'max:100'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $method = $validated['method'] ?? 'manual';
        $parcelCode = str_starts_with($validated['parcel_reference'], 'EZP:') ? substr($validated['parcel_reference'], 4) : $validated['parcel_reference'];
        $locationCode = str_starts_with($validated['location_reference'], 'EZL:') ? substr($validated['location_reference'], 4) : $validated['location_reference'];
        $order = Order::query()->where('parcel_code', $parcelCode)->orWhere('order_number', $parcelCode)->first();
        $location = DB::table('storage_locations')->where('hub_id', $operator->id)->where('code', $locationCode)->first();

        if ($order === null || $location === null) {
            return back()->withErrors(['parcel_reference' => 'The parcel or location could not be processed.']);
        }

        $removed = DB::transaction(function () use ($order, $location, $operator, $method, $request): bool {
            $placement = DB::table('parcel_placements')->where('order_id', $order->id)->where('location_id', $location->id)
                ->where('active_order_id', $order->id)->whereNull('removed_at')->lockForUpdate()->first();
            if ($placement === null) {
                return false;
            }

            DB::table('parcel_placements')->where('id', $placement->id)->update([
                'active_order_id' => null,
                'removed_by' => $operator->id,
                'removed_at' => now(),
                'removal_reason' => 'Picked from storage for dispatch or transfer.',
                'updated_at' => now(),
            ]);
            DB::table('scan_events')->insert([
                'order_id' => $order->id,
                'location_id' => $location->id,
                'actor_id' => $operator->id,
                'station' => 'pick',
                'result' => 'accepted',
                'method' => $method,
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);

            return true;
        });

        if (! $removed) {
            DB::table('scan_events')->insert([
                'order_id' => $order->id,
                'location_id' => $location->id,
                'actor_id' => $operator->id,
                'station' => 'pick',
                'result' => 'rejected',
                'failure_reason' => 'Parcel is not actively placed in the scanned location.',
                'method' => $method,
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);

            return back()->withErrors(['parcel_reference' => 'The parcel is not actively placed in that location.']);
        }

        return back()->with('success', 'Parcel removed from its active storage placement.');
    }

    public function cycleCount(Request $request): RedirectResponse
    {
        $operator = $this->authenticatedUser();
        $validated = $request->validate([
            'location_reference' => ['required', 'string', 'max:100'],
            'scanned_parcels' => ['nullable', 'string', 'max:50000'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $locationCode = str_starts_with($validated['location_reference'], 'EZL:')
            ? substr($validated['location_reference'], 4)
            : $validated['location_reference'];
        $location = DB::table('storage_locations')->where('hub_id', $operator->id)->where('code', $locationCode)->first();

        if ($location === null || ! $location->is_active) {
            DB::table('scan_events')->insert([
                'location_id' => null,
                'actor_id' => $operator->id,
                'station' => 'cycle_count',
                'result' => 'rejected',
                'failure_reason' => 'Unknown or inactive location.',
                'method' => $validated['method'] ?? 'manual',
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);

            return back()->withErrors(['location_reference' => 'The location could not be processed.']);
        }

        $expected = DB::table('parcel_placements')->join('orders', 'orders.id', '=', 'parcel_placements.order_id')
            ->where('parcel_placements.location_id', $location->id)->whereNull('parcel_placements.removed_at')
            ->pluck('orders.parcel_code', 'orders.id');
        $scannedCodes = collect(preg_split('/\R/u', $validated['scanned_parcels'] ?? '') ?: [])
            ->map(fn (string $code): string => trim(str_starts_with(trim($code), 'EZP:') ? substr(trim($code), 4) : trim($code)))
            ->filter()->values();
        $resolved = Order::query()->whereIn('parcel_code', $scannedCodes)->get(['id', 'parcel_code'])->keyBy('parcel_code');
        $expectedByCode = $expected->flip();
        $correct = $scannedCodes->filter(fn (string $code): bool => $expectedByCode->has($code))->unique()->values();
        $unexpected = $scannedCodes->diff($correct)->unique()->values();
        $missing = $expected->values()->diff($correct)->unique()->values();
        $method = $validated['method'] ?? 'manual';

        foreach ($scannedCodes as $code) {
            $order = $resolved->get($code);
            $isExpected = $expectedByCode->has($code);
            DB::table('scan_events')->insert([
                'order_id' => $order?->id,
                'location_id' => $location->id,
                'actor_id' => $operator->id,
                'station' => 'cycle_count',
                'result' => $isExpected ? 'correct' : 'unexpected',
                'failure_reason' => $isExpected ? null : 'Parcel is not expected in the scanned location.',
                'method' => $method,
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);
        }
        foreach ($missing as $code) {
            DB::table('scan_events')->insert([
                'order_id' => $expectedByCode->get($code),
                'location_id' => $location->id,
                'actor_id' => $operator->id,
                'station' => 'cycle_count',
                'result' => 'missing',
                'failure_reason' => 'Expected parcel was not scanned during cycle count.',
                'method' => $method,
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);
        }

        return back()->with('cycle_report', [
            'location' => $location->label,
            'correct' => $correct->all(),
            'missing' => $missing->all(),
            'unexpected' => $unexpected->all(),
        ]);
    }

    public function sortParcel(Order $order, OrderTransitionService $transitions, OrderAreaService $areas): RedirectResponse
    {
        $this->assertHubAccess($order, $this->authenticatedUser());
        $area = $areas->resolve($order);
        $transitions->transition($order, $this->authenticatedUser(), OrderStatus::Sorted, 'sorted', $area->name, "Sorted to destination area {$area->code}.", [
            'destination_area_id' => $area->id,
            'delivery_area' => $area->name,
            'sorted_at' => now(),
        ]);

        return back()->with('success', "Parcel {$order->order_number} sorted to {$area->name}.");
    }

    public function dispatch(Request $request, RiderBadgeService $badges, QrCodeService $qrCodes): View
    {
        $validated = $request->validate(['rider_search' => ['nullable', 'string', 'max:100']]);
        $operator = $this->authenticatedUser();
        $singleHub = ! $this->isMultiHubOperation();
        $parcels = $this->hubOrders($operator)->whereIn('status', ['SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT'])
            ->with(['buyer', 'deliveryCourier', 'destinationArea', 'deliveryAttempts'])->latest()->paginate(15);
        $pageAreaIds = $parcels->getCollection()->pluck('destination_area_id')->filter()->unique()->all();
        $riders = User::query()->where('role', 'courier')->where('status', 'approved')
            ->whereHas('serviceAreas', function (Builder $query) use ($pageAreaIds, $operator, $singleHub): void {
                $query->whereIn('areas.id', $pageAreaIds)
                    ->where(function (Builder $areaQuery) use ($operator, $singleHub): void {
                        $areaQuery->where('areas.sorting_center_id', $operator->id);
                        if ($singleHub) {
                            $areaQuery->orWhereNull('areas.sorting_center_id');
                        }
                    });
            })
            ->when(! $singleHub, fn (Builder $query) => $query->whereDoesntHave('serviceAreas', fn (Builder $areas) => $areas->whereNotNull('areas.sorting_center_id')->where('areas.sorting_center_id', '!=', $operator->id)))
            ->when(filled($validated['rider_search'] ?? null), function (Builder $query) use ($validated): void {
                $search = $validated['rider_search'];
                $query->where(fn (Builder $riders): Builder => $riders->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%"));
            })
            ->with('serviceAreas')->withCount([
                'finalDeliveries as active_deliveries_count' => fn (Builder $query) => $query->activeCourierWorkload(false),
                'finalDeliveries as failed_deliveries_count' => fn (Builder $query) => $query->where('status', 'DELIVERY_FAILED'),
                'finalDeliveries as capacity_load_count' => fn (Builder $query) => $query->activeCourierWorkload(),
            ])->orderBy('active_deliveries_count')->orderBy('failed_deliveries_count')->orderBy('first_name')->limit(50)->get();
        $riders->each(function (User $rider) use ($badges, $qrCodes): void {
            $badgeCode = $badges->ensure($rider);
            $rider->badge_code = $badgeCode;
            $rider->badge_qr = $qrCodes->svg('EZR:'.$badgeCode, 100);
        });
        $maxActiveDeliveries = max(1, (int) config('logistics.maximum_active_deliveries_per_rider', 10));
        $suggestedRiders = [];
        foreach ($parcels as $parcel) {
            $suggestedRiders[$parcel->id] = $riders
                ->filter(fn (User $rider): bool => $parcel->destination_area_id !== null
                    && $rider->capacity_load_count < $maxActiveDeliveries
                    && $rider->serviceAreas->contains('id', $parcel->destination_area_id))
                ->sortBy('failed_deliveries_count')
                ->sortBy('active_deliveries_count')
                ->first();
        }

        return view('logistics.dispatch', compact('parcels', 'riders', 'suggestedRiders', 'maxActiveDeliveries'));
    }

    public function assignRider(
        Request $request,
        Order $order,
        OrderTransitionService $transitions,
        TransactionAwareNotificationSender $notifications,
    ): RedirectResponse {
        $rules = [
            'delivery_courier_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
        $validated = $request->validate($rules);
        $operator = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $validated, $operator, $transitions, $notifications): RedirectResponse {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertHubAccess($lockedOrder, $operator);
            $previousRiderId = $lockedOrder->delivery_courier_id
                ?? DB::table('delivery_assignments')->where('order_id', $lockedOrder->id)->orderByDesc('assigned_at')->value('rider_id');
            abort_unless(in_array($lockedOrder->status, ['SORTED', 'ASSIGNED_TO_RIDER'], true), 422, 'A failed parcel must be scanned into a hub Returns or Exception location before it can be assigned again.');
            if ($lockedOrder->status === OrderStatus::AssignedToRider->value) {
                abort_unless($lockedOrder->hub_released_at === null, 422, 'This parcel cannot be reassigned after Logistics records the hub handoff.');
            }
            $rider = User::whereKey($validated['delivery_courier_id'])->lockForUpdate()->firstOrFail();
            abort_unless($rider->role === 'courier' && $rider->status === 'approved', 422, 'Choose an approved rider.');
            abort_unless($this->riderBelongsToHub($rider, $operator) || ! $this->isMultiHubOperation(), 422, 'The rider is not assigned to this hub.');
            abort_if($rider->status === 'suspended', 422, 'Suspended riders cannot receive parcels.');
            abort_unless($lockedOrder->destination_area_id !== null, 422, 'Sort this parcel into a destination area before dispatch.');
            abort_unless($rider->serviceAreas()->whereKey($lockedOrder->destination_area_id)->exists(), 422, 'The rider is not assigned to this destination area.');
            $activeDeliveries = Order::query()
                ->where('delivery_courier_id', $rider->id)
                ->activeCourierWorkload()
                ->where('id', '!=', $lockedOrder->id)
                ->count();
            abort_if($activeDeliveries >= max(1, (int) config('logistics.maximum_active_deliveries_per_rider', 10)), 422, 'This rider is at the active delivery capacity limit.');

            $scheduleNote = isset($validated['scheduled_at']) ? ' Next attempt scheduled for '.Carbon::parse($validated['scheduled_at'])->format('M j, Y g:i A').'.' : '';
            $transitions->transition($lockedOrder, $operator, OrderStatus::AssignedToRider, 'rider_assigned', $lockedOrder->delivery_area, "Assigned to {$rider->first_name} {$rider->last_name}.{$scheduleNote}", [
                'delivery_courier_id' => $rider->id,
                'assigned_at' => now(),
                'hub_released_at' => null,
                'failed_at' => null,
                'delivery_failure_reason' => null,
                'delivery_notes' => null,
                'sorting_center_id' => $operator->id,
            ]);

            if ($previousRiderId !== null && (int) $previousRiderId !== $rider->id) {
                DB::table('logistics_exceptions')
                    ->where('order_id', $lockedOrder->id)
                    ->where('previous_rider_id', $previousRiderId)
                    ->where('status', 'OPEN')
                    ->update([
                        'new_rider_id' => $rider->id,
                        'status' => 'RESOLVED',
                        'resolution' => 'Parcel reassigned to an approved rider before hub release.',
                        'resolved_by' => $operator->id,
                        'resolved_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            if ($previousRiderId !== null && (int) $previousRiderId !== $rider->id) {
                $previousRider = User::query()->find($previousRiderId);

                if ($previousRider !== null) {
                    $notifications->send($previousRider, new OrderWorkflowNotification(
                        $lockedOrder->refresh(),
                        'delivery_assignment_replaced',
                        'Logistics removed this delivery assignment and assigned it to another rider.',
                    ));
                }
            }

            if (isset($validated['scheduled_at'])) {
                $scheduledAttempt = DeliveryAttempt::query()
                    ->where('order_id', $lockedOrder->id)
                    ->where('outcome', 'scheduled')
                    ->lockForUpdate()
                    ->first();

                if ($scheduledAttempt !== null) {
                    $scheduledAttempt->update(['rider_id' => $rider->id, 'scheduled_at' => $validated['scheduled_at']]);
                } else {
                    DeliveryAttempt::query()->create([
                        'order_id' => $lockedOrder->id,
                        'rider_id' => $rider->id,
                        'attempt_no' => ((int) DeliveryAttempt::query()->where('order_id', $lockedOrder->id)->max('attempt_no')) + 1,
                        'outcome' => 'scheduled',
                        'scheduled_at' => $validated['scheduled_at'],
                    ]);
                }
            }

            return back()->with('success', "Parcel {$lockedOrder->order_number} assigned to {$rider->first_name} {$rider->last_name}.");
        });
    }

    public function releaseToRider(Request $request, Order $order, TransactionAwareNotificationSender $notifications, RiderBadgeService $badges): RedirectResponse
    {
        $validated = $request->validate([
            'rider_badge' => ['required', 'string', 'max:100'],
            'parcel_reference' => ['required', 'string', 'max:100'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $operator = $this->authenticatedUser();
        $badgeRiderId = null;
        $submittedBadge = null;
        if (filled($validated['rider_badge'])) {
            $preselectedRider = User::query()->find($order->delivery_courier_id);
            $submittedBadge = str_starts_with($validated['rider_badge'], 'EZR:')
                ? substr($validated['rider_badge'], 4)
                : $validated['rider_badge'];
            $badgeMatches = $preselectedRider !== null
                && $preselectedRider->role === 'courier'
                && hash_equals($badges->ensure($preselectedRider), $submittedBadge);

            if (! $badgeMatches) {
                DB::table('scan_events')->insert([
                    'order_id' => $order->id,
                    'actor_id' => $operator->id,
                    'station' => 'dispatch_release',
                    'result' => 'rejected',
                    'failure_reason' => 'The scanned rider badge did not match the assigned delivery rider.',
                    'method' => $validated['method'] ?? 'manual',
                    'ip' => $request->ip(),
                    'created_at' => now(),
                ]);

                throw ValidationException::withMessages([
                    'rider_badge' => 'The scanned badge does not match the assigned delivery rider.',
                ]);
            }

            $badgeRiderId = $preselectedRider->id;
        }

        $scannedParcelCode = str_starts_with($validated['parcel_reference'], 'EZP:')
            ? substr($validated['parcel_reference'], 4)
            : $validated['parcel_reference'];
        if (! hash_equals((string) $order->parcel_code, $scannedParcelCode)) {
            DB::table('scan_events')->insert([
                'order_id' => $order->id,
                'actor_id' => $operator->id,
                'station' => 'dispatch_release',
                'result' => 'rejected',
                'failure_reason' => 'Scanned parcel code did not match the assigned parcel.',
                'method' => $validated['method'] ?? 'manual',
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);

            throw ValidationException::withMessages(['parcel_reference' => 'The scanned parcel does not match this dispatch assignment.']);
        }

        return DB::transaction(function () use ($request, $order, $operator, $notifications, $badges, $validated, $badgeRiderId, $submittedBadge, $scannedParcelCode): RedirectResponse {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertHubAccess($lockedOrder, $operator);
            abort_unless($lockedOrder->status === OrderStatus::AssignedToRider->value, 422, 'Assign this parcel before releasing it to a rider.');
            abort_unless($lockedOrder->payment_method === 'COD', 422, 'This parcel is on hold until payment verification is configured.');
            abort_unless($lockedOrder->delivery_courier_id !== null && $lockedOrder->hub_released_at === null, 422, 'This parcel has already been released or has no assigned rider.');

            $rider = User::query()->whereKey($lockedOrder->delivery_courier_id)->lockForUpdate()->firstOrFail();
            abort_unless($rider->role === 'courier' && $rider->status === 'approved', 422, 'Only an approved rider can receive a hub release.');
            abort_unless($this->riderBelongsToHub($rider, $operator) || ! $this->isMultiHubOperation(), 422, 'The rider is not assigned to this hub.');
            abort_unless($badgeRiderId === $rider->id && hash_equals($badges->ensure($rider), $submittedBadge), 422, 'The rider assignment changed after the badge scan. Scan the current rider badge again.');
            abort_unless(hash_equals((string) $lockedOrder->parcel_code, $scannedParcelCode), 422, 'The parcel changed after the scan. Scan the current parcel label again.');

            foreach (['dispatch_release_rider', 'dispatch_release_parcel'] as $station) {
                DB::table('scan_events')->insert([
                    'order_id' => $lockedOrder->id,
                    'actor_id' => $operator->id,
                    'station' => $station,
                    'result' => 'accepted',
                    'method' => $validated['method'] ?? 'manual',
                    'ip' => $request->ip(),
                    'created_at' => now(),
                ]);
            }

            $lockedOrder->forceFill(['hub_released_at' => now()])->save();
            ParcelTrackingEvent::query()->create([
                'order_id' => $lockedOrder->id,
                'actor_id' => $operator->id,
                'event_type' => 'hub_released_to_rider',
                'status' => $lockedOrder->status,
                'location' => $operator->municipality,
                'notes' => "Logistics released the parcel to {$rider->first_name} {$rider->last_name} for final delivery.",
            ]);
            $notifications->send($rider, new OrderWorkflowNotification($lockedOrder, 'hub_released_to_rider', 'Logistics released your assigned parcel from the sorting center.'));

            return back()->with('success', "Parcel {$lockedOrder->order_number} released to {$rider->first_name} {$rider->last_name}.");
        });
    }

    public function releaseBatch(Request $request, TransactionAwareNotificationSender $notifications, RiderBadgeService $badges): RedirectResponse
    {
        $validated = $request->validate([
            'rider_badge' => ['required', 'string', 'max:100'],
            'order_ids' => ['required', 'array', 'min:1', 'max:100'],
            'order_ids.*' => ['required', 'integer', 'distinct', 'exists:orders,id'],
            'parcel_references_text' => ['required', 'string', 'max:12000'],
            'method' => ['nullable', 'in:camera,handheld,manual'],
        ]);
        $operator = $this->authenticatedUser();
        $badgeCode = str_starts_with($validated['rider_badge'], 'EZR:')
            ? substr($validated['rider_badge'], 4)
            : $validated['rider_badge'];
        $rider = User::query()->where('role', 'courier')->where('status', 'approved')->where('badge_code', $badgeCode)->first();

        if ($rider === null) {
            $this->recordDispatchBatchScan($request, null, $operator, 'dispatch_batch_rider', 'rejected', $validated['method'] ?? 'manual', 'Unknown or inactive rider badge.');

            throw ValidationException::withMessages(['rider_badge' => 'Scan the badge of an approved rider.']);
        }

        $references = collect(preg_split('/\\R/', trim($validated['parcel_references_text'])) ?: [])->filter()->values();
        $codes = $references->map(fn (string $reference): string => str_starts_with($reference, 'EZP:') ? substr($reference, 4) : $reference);
        $orders = Order::query()->whereIn('id', $validated['order_ids'])->get(['id', 'parcel_code']);
        $expectedCodes = $orders->pluck('parcel_code');
        if (count($validated['order_ids']) !== $expectedCodes->count()
            || $codes->count() !== $expectedCodes->count()
            || $codes->unique()->count() !== $codes->count()
            || $codes->sort()->values()->all() !== $expectedCodes->sort()->values()->all()) {
            foreach ($orders as $order) {
                $this->recordDispatchBatchScan($request, $order->id, $operator, 'dispatch_batch_parcel', 'rejected', $validated['method'] ?? 'manual', 'Scanned parcel list did not match the selected manifest.');
            }

            throw ValidationException::withMessages(['parcel_references' => 'Scanned parcel labels must match every selected parcel exactly once.']);
        }

        try {
            DB::transaction(function () use ($request, $validated, $operator, $notifications, $badges, $rider): void {
                $lockedRider = User::query()->whereKey($rider->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedRider->status === 'approved' && hash_equals($badges->ensure($lockedRider), str_starts_with($validated['rider_badge'], 'EZR:') ? substr($validated['rider_badge'], 4) : $validated['rider_badge']), 422, 'The rider badge changed. Scan the current badge again.');
                $orders = Order::query()->whereIn('id', $validated['order_ids'])->orderBy('id')->lockForUpdate()->get();
                abort_unless($orders->count() === count($validated['order_ids']), 422, 'The selected manifest changed. Refresh dispatch and scan again.');
                $parcelCodes = collect(preg_split('/\\R/', trim($validated['parcel_references_text'])) ?: [])
                    ->filter()
                    ->map(fn (string $reference): string => str_starts_with($reference, 'EZP:') ? substr($reference, 4) : $reference)
                    ->sort()
                    ->values()
                    ->all();
                abort_unless($orders->pluck('parcel_code')->sort()->values()->all() === $parcelCodes, 422, 'The scanned parcel list no longer matches the selected manifest.');

                foreach ($orders as $order) {
                    $this->assertHubAccess($order, $operator);
                    abort_unless($order->status === OrderStatus::AssignedToRider->value && $order->payment_method === 'COD' && $order->delivery_courier_id === $lockedRider->id && $order->hub_released_at === null, 422, 'Every selected parcel must be assigned to this rider, fulfillable, and not yet released.');
                    abort_unless($this->riderBelongsToHub($lockedRider, $operator) || ! $this->isMultiHubOperation(), 422, 'The rider is not assigned to this hub.');
                    abort_unless($order->destination_area_id !== null && $lockedRider->serviceAreas()->whereKey($order->destination_area_id)->exists(), 422, 'The assigned rider no longer serves every selected parcel destination.');
                    DB::table('scan_events')->insert([
                        'order_id' => $order->id,
                        'actor_id' => $operator->id,
                        'station' => 'dispatch_batch_rider',
                        'result' => 'accepted',
                        'method' => $validated['method'] ?? 'manual',
                        'ip' => $request->ip(),
                        'created_at' => now(),
                    ]);
                    DB::table('scan_events')->insert([
                        'order_id' => $order->id,
                        'actor_id' => $operator->id,
                        'station' => 'dispatch_batch_parcel',
                        'result' => 'accepted',
                        'method' => $validated['method'] ?? 'manual',
                        'ip' => $request->ip(),
                        'created_at' => now(),
                    ]);
                    $order->forceFill(['hub_released_at' => now()])->save();
                    ParcelTrackingEvent::query()->create([
                        'order_id' => $order->id,
                        'actor_id' => $operator->id,
                        'event_type' => 'hub_released_to_rider',
                        'status' => $order->status,
                        'location' => $operator->municipality,
                        'notes' => "Logistics batch-released the parcel to {$lockedRider->first_name} {$lockedRider->last_name}.",
                    ]);
                    $notifications->send($lockedRider, new OrderWorkflowNotification($order, 'hub_released_to_rider', 'Logistics released your assigned parcel from the sorting center.'));
                }
            });
        } catch (Throwable $exception) {
            $failureReason = $exception instanceof HttpException
                ? 'Manifest rejected: '.$exception->getMessage()
                : 'Manifest release failed before the custody update completed.';
            $failureReason = Str::limit($failureReason, 240);

            foreach ($validated['order_ids'] as $orderId) {
                $this->recordDispatchBatchScan($request, (int) $orderId, $operator, 'dispatch_batch_release', 'rejected', $validated['method'] ?? 'manual', $failureReason);
            }

            throw $exception;
        }

        return back()->with('success', count($validated['order_ids'])." parcels batch-released to {$rider->first_name} {$rider->last_name}.");
    }

    private function recordDispatchBatchScan(Request $request, ?int $orderId, User $operator, string $station, string $result, string $method, ?string $failureReason = null): void
    {
        DB::table('scan_events')->insert([
            'order_id' => $orderId,
            'actor_id' => $operator->id,
            'station' => $station,
            'result' => $result,
            'failure_reason' => $failureReason,
            'method' => $method,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    public function recoverReleasedParcel(Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $operator = $this->authenticatedUser();
        $this->assertHubAccess($order, $operator);
        $transitions->recoverReleasedDeliveryParcel($order, $operator);
        DB::table('logistics_exceptions')
            ->where('order_id', $order->id)
            ->where('status', 'OPEN')
            ->update([
                'status' => 'RESOLVED',
                'resolution' => 'Physical parcel recovery was confirmed at the assigned hub and returned to dispatch.',
                'resolved_by' => $operator->id,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

        return back()->with('success', "Parcel {$order->order_number} recovered at the hub and returned to dispatch.");
    }

    public function riders(RiderBadgeService $badges, QrCodeService $qrCodes): View
    {
        $operator = $this->authenticatedUser();
        $singleHub = ! $this->isMultiHubOperation();
        $assignedHubRiderIds = DB::table('area_user')
            ->join('areas', 'areas.id', '=', 'area_user.area_id')
            ->where('area_user.is_active', true)
            ->where('areas.is_active', true)
            ->where(function ($query) use ($operator, $singleHub): void {
                $query->where('areas.sorting_center_id', $operator->id);
                if ($singleHub) {
                    $query->orWhereNull('areas.sorting_center_id');
                }
            })
            ->distinct()
            ->pluck('area_user.user_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
        $ridersQuery = User::query()->where('role', 'courier');
        if (! $singleHub) {
            $ridersWithOtherHub = DB::table('area_user')
                ->join('areas', 'areas.id', '=', 'area_user.area_id')
                ->where('area_user.is_active', true)
                ->where('areas.is_active', true)
                ->whereNotNull('areas.sorting_center_id')
                ->where('areas.sorting_center_id', '!=', $operator->id)
                ->distinct()
                ->pluck('area_user.user_id')
                ->all();
            $ridersQuery->whereNotIn('id', $ridersWithOtherHub);
        }
        $riders = $ridersQuery->with('serviceAreas')->withCount([
            'finalDeliveries as active_deliveries_count' => fn (Builder $query) => $query->activeCourierWorkload(false),
            'finalDeliveries as completed_deliveries_count' => fn (Builder $query) => $query->whereIn('status', ['DELIVERED', 'COMPLETED']),
            'finalDeliveries as failed_deliveries_count' => fn (Builder $query) => $query->where('status', 'DELIVERY_FAILED'),
        ])->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'suspended' THEN 2 ELSE 3 END")->paginate(20);
        $riders->getCollection()->each(function (User $rider) use ($badges, $qrCodes, $assignedHubRiderIds, $singleHub): void {
            $rider->setAttribute('assigned_to_hub', $singleHub || in_array($rider->id, $assignedHubRiderIds, true));
            if (! $rider->assigned_to_hub) {
                return;
            }
            $badgeCode = $badges->ensure($rider);
            $rider->badge_code = $badgeCode;
            $rider->badge_qr = $qrCodes->svg('EZR:'.$badgeCode, 120);
        });

        $areas = Area::query()->where('is_active', true)
            ->where(fn (Builder $query) => $query->where('sorting_center_id', $operator->id)->orWhereNull('sorting_center_id'))
            ->orderBy('name')->get();

        return view('logistics.riders', compact('riders', 'areas'));
    }

    public function areas(): View
    {
        $operator = $this->authenticatedUser();
        $areas = Area::query()
            ->with('municipalities')
            ->withCount(['riders', 'orders'])
            ->where(fn (Builder $query) => $query->whereNull('sorting_center_id')->orWhere('sorting_center_id', $operator->id))
            ->orderBy('name')
            ->get();

        return view('logistics.areas', compact('areas'));
    }

    public function storeArea(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'alpha_dash', 'max:255', 'unique:areas,code'],
        ]);

        Area::query()->create([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
            'is_active' => true,
            'sorting_center_id' => $this->authenticatedUser()->id,
        ]);

        return back()->with('success', 'Routing area created. Map its municipalities to make it available for parcel sorting.');
    }

    public function storeAreaMunicipality(Request $request, OrderAreaService $areas): RedirectResponse
    {
        $validated = $request->validate([
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'province' => ['required', 'string', 'max:191'],
            'municipality' => ['required', 'string', 'max:191'],
        ]);
        $province = trim($validated['province']);
        $municipality = trim($validated['municipality']);
        $provinceNormalized = $areas->normalizeAddressValue($province);
        $municipalityNormalized = $areas->normalizeAddressValue($municipality);

        $this->assertAreaMunicipalityAvailable($provinceNormalized, $municipalityNormalized);
        $area = Area::query()->findOrFail($validated['area_id']);
        abort_unless($area->is_active, 422, 'Select an active routing area.');
        $this->claimAreaForHub($area, $this->authenticatedUser());

        try {
            AreaMunicipality::query()->create([
                'area_id' => $area->id,
                'province' => $province,
                'municipality' => $municipality,
                'province_normalized' => $provinceNormalized,
                'municipality_normalized' => $municipalityNormalized,
            ]);
        } catch (QueryException $exception) {
            $this->assertAreaMunicipalityAvailable($provinceNormalized, $municipalityNormalized);

            throw $exception;
        }

        return back()->with('success', "{$municipality}, {$province} now routes to {$area->name}.");
    }

    public function updateAreaMunicipality(Request $request, AreaMunicipality $areaMunicipality): RedirectResponse
    {
        $validated = $request->validate([
            'area_id' => ['required', 'integer', 'exists:areas,id'],
        ]);
        $currentArea = $areaMunicipality->area;
        abort_unless($currentArea?->sorting_center_id === null || (int) $currentArea->sorting_center_id === $this->authenticatedUser()->id, 403, 'This municipality mapping belongs to another sorting center.');
        $area = Area::query()->findOrFail($validated['area_id']);
        abort_unless($area->is_active, 422, 'Select an active routing area.');
        $this->claimAreaForHub($area, $this->authenticatedUser());
        abort_unless($areaMunicipality->area?->sorting_center_id === null || (int) $areaMunicipality->area->sorting_center_id === $this->authenticatedUser()->id, 403, 'This municipality mapping belongs to another sorting center.');

        $areaMunicipality->update(['area_id' => $area->id]);

        return back()->with('success', "{$areaMunicipality->municipality}, {$areaMunicipality->province} now routes to {$area->name}.");
    }

    public function claimArea(Area $area): RedirectResponse
    {
        $this->claimAreaForHub($area, $this->authenticatedUser());

        return back()->with('success', "Routing area {$area->name} now belongs to this hub.");
    }

    private function assertAreaMunicipalityAvailable(string $provinceNormalized, string $municipalityNormalized): void
    {
        $alreadyMapped = AreaMunicipality::query()
            ->where('province_normalized', $provinceNormalized)
            ->where('municipality_normalized', $municipalityNormalized)
            ->exists();

        if ($alreadyMapped) {
            throw ValidationException::withMessages([
                'municipality' => 'This province and municipality are already mapped. Change the area in the existing mapping.',
            ]);
        }
    }

    public function updateRiderAreas(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'courier', 404);
        $operator = $this->authenticatedUser();
        $validated = $request->validate([
            'area_ids' => ['nullable', 'array'],
            'area_ids.*' => ['integer', 'distinct', 'exists:areas,id'],
        ]);
        $areaIds = array_values(array_unique($validated['area_ids'] ?? []));
        $activeIds = Area::query()->where('is_active', true)->whereKey($areaIds)
            ->where(fn (Builder $query) => $query->whereNull('sorting_center_id')->orWhere('sorting_center_id', $operator->id))
            ->pluck('id')->all();
        abort_unless(count($activeIds) === count($areaIds), 422, 'Select active service areas only.');

        DB::transaction(function () use ($user, $activeIds, $operator): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedUser->role === 'courier', 404);
            $hasOtherHubAssignment = DB::table('area_user')
                ->join('areas', 'areas.id', '=', 'area_user.area_id')
                ->where('area_user.user_id', $lockedUser->id)
                ->where('area_user.is_active', true)
                ->where('areas.is_active', true)
                ->whereNotNull('areas.sorting_center_id')
                ->where('areas.sorting_center_id', '!=', $operator->id)
                ->exists();
            abort_unless(! $hasOtherHubAssignment, 403, 'This rider is assigned to another sorting center.');

            $lockedAreas = Area::query()->whereKey($activeIds)->lockForUpdate()->get();
            abort_unless($lockedAreas->count() === count($activeIds)
                && $lockedAreas->every(fn (Area $area): bool => $area->is_active && ($area->sorting_center_id === null || (int) $area->sorting_center_id === $operator->id)),
                422,
                'A selected service area was assigned to another hub. Refresh and choose areas owned by this hub.',
            );

            $activeParcelAreaIds = Order::query()
                ->whereNotNull('destination_area_id')
                ->where(function (Builder $query) use ($lockedUser): void {
                    $query->where(fn (Builder $deliveries) => $deliveries->where('delivery_courier_id', $lockedUser->id)
                        ->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT']))
                        ->orWhere(fn (Builder $pickups) => $pickups->where('pickup_courier_id', $lockedUser->id)
                            ->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP']));
                })
                ->distinct()
                ->pluck('destination_area_id')
                ->map(fn (int|string $id): int => (int) $id);
            abort_unless($activeParcelAreaIds->diff($activeIds)->isEmpty(), 422, 'A service area with active parcels cannot be removed from this rider.');

            DB::table('area_user')->where('user_id', $user->id)->update(['is_active' => false, 'is_primary' => false, 'updated_at' => now()]);
            foreach ($activeIds as $index => $areaId) {
                Area::query()->whereKey($areaId)->whereNull('sorting_center_id')->update(['sorting_center_id' => $operator->id]);
                DB::table('area_user')->updateOrInsert(
                    ['user_id' => $user->id, 'area_id' => $areaId],
                    ['is_active' => true, 'is_primary' => $index === 0, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        });

        return back()->with('success', 'Rider service areas updated. The first selected area is primary.');
    }

    public function approveRider(User $user, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        $operator = $this->authenticatedUser();
        DB::transaction(function () use ($user, $notifications, $operator): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedUser->role === 'courier' && $lockedUser->status === 'pending', 404);
            abort_unless($this->riderBelongsToHub($lockedUser, $operator) || ! $this->isMultiHubOperation(), 403, 'Assign this rider to the hub before approving the application.');

            $lockedUser->forceFill(['status' => 'approved'])->save();
            $notifications->send($lockedUser, new AccountStatusNotification('approved'));
        });

        return back()->with('success', "Courier {$user->first_name} {$user->last_name} approved.");
    }

    public function rejectRider(User $user, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        $operator = $this->authenticatedUser();
        DB::transaction(function () use ($user, $notifications, $operator): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedUser->role === 'courier' && $lockedUser->status === 'pending', 404);
            abort_unless($this->riderBelongsToHub($lockedUser, $operator) || ! $this->isMultiHubOperation(), 403, 'This rider application belongs to another hub or is unassigned.');

            $lockedUser->forceFill(['status' => 'rejected'])->save();
            $notifications->send($lockedUser, new AccountStatusNotification('rejected'));
        });

        return back()->with('success', 'Courier application rejected.');
    }

    public function returnParcel(Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $operator = $this->authenticatedUser();
        $this->assertHubAccess($order, $operator);
        abort_unless(
            $order->status === OrderStatus::AssignedToRider->value
                && $order->delivery_courier_id !== null
                && $order->hub_released_at !== null,
            422,
            'Assign the return to an approved rider and record the physical hub handoff before starting the seller return.',
        );

        $transitions->transition($order, $operator, OrderStatus::ReturnInTransit, 'return_in_transit', $operator->municipality, 'Logistics released the physically recovered parcel to a courier for seller return.');

        return back()->with('success', "Order {$order->order_number} marked for return handling.");
    }

    public function suspendRider(Request $request, User $user, TransactionAwareNotificationSender $notifications, LogisticsExceptionService $exceptions): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $operator = $this->authenticatedUser();

        DB::transaction(function () use ($user, $notifications, $validated, $operator, $exceptions): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedUser->role === 'courier' && $lockedUser->status === 'approved', 404);
            abort_unless($this->riderBelongsToHub($lockedUser, $operator) || ! $this->isMultiHubOperation(), 403, 'This rider is not assigned to this sorting center.');

            $lockedUser->forceFill([
                'status' => 'suspended',
                'suspension_previous_status' => 'approved',
                'suspension_source' => 'logistics',
                'suspension_reason' => $validated['reason'],
            ])->save();

            $exceptions->openForRiderSuspension($lockedUser, $operator, $validated['reason']);

            $notifications->send($lockedUser, new AccountStatusNotification('suspended'));
        });

        return back()->with('success', 'Rider suspended. Any active parcels are now listed in the Logistics exception queue.');
    }

    public function reactivateRider(User $user): RedirectResponse
    {
        $operator = $this->authenticatedUser();
        DB::transaction(function () use ($user, $operator): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $lockedUser->role === 'courier'
                    && $lockedUser->status === 'suspended'
                    && $lockedUser->suspension_previous_status === 'approved'
                    && $lockedUser->suspension_source === 'logistics',
                404,
            );
            abort_unless($this->riderBelongsToHub($lockedUser, $operator) || ! $this->isMultiHubOperation(), 403, 'This rider is not assigned to this sorting center.');

            $lockedUser->forceFill([
                'status' => 'approved',
                'suspension_previous_status' => null,
                'suspension_source' => null,
                'suspension_reason' => null,
                'suspended_at' => null,
            ])->save();
        });

        return back()->with('success', 'Rider reactivated.');
    }

    public function tracking(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:PICKED_UP,AT_SORTING_CENTER,SORTED,ASSIGNED_TO_RIDER,OUT_FOR_DELIVERY,DELIVERY_FAILED,RETURN_IN_TRANSIT,RETURNED_TO_SELLER'],
            'rider' => ['nullable', 'integer', 'exists:users,id'],
            'rider_search' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'integer', 'exists:areas,id'],
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $operator = $this->authenticatedUser();
        $orders = $this->hubOrders($operator)->with(['deliveryCourier', 'pickupCourier', 'trackingEvents.actor'])
            ->whereIn('status', ['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'])
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['rider'] ?? null, fn (Builder $query, int $rider) => $query->where('delivery_courier_id', $rider))
            ->when($validated['area'] ?? null, fn (Builder $query, int $area) => $query->where('destination_area_id', $area))
            ->when($validated['date'] ?? null, function (Builder $query, string $date): void {
                $day = Carbon::parse($date);
                $query->whereBetween('updated_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
            })
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->whereRaw("order_number LIKE ? ESCAPE '!'", [$this->orderNumberSearchPattern($search)]);
            })
            ->latest()->paginate(20)->withQueryString();
        $singleHub = ! $this->isMultiHubOperation();
        $areas = Area::query()->where(fn (Builder $query) => $query->where('sorting_center_id', $operator->id)
            ->when($singleHub, fn (Builder $legacy) => $legacy->orWhereNull('sorting_center_id')))->orderBy('name')->get(['id', 'name']);
        $areaIds = $areas->pluck('id')->all();
        $riders = User::query()->where('role', 'courier')->where('status', 'approved')
            ->whereHas('serviceAreas', fn (Builder $query) => $query->whereIn('areas.id', $areaIds))
            ->when(! $singleHub, fn (Builder $query) => $query->whereDoesntHave('serviceAreas', fn (Builder $areas) => $areas->whereNotNull('areas.sorting_center_id')->where('areas.sorting_center_id', '!=', $operator->id)))
            ->when(filled($validated['rider_search'] ?? null), function (Builder $query) use ($validated): void {
                $search = $validated['rider_search'];
                $query->where(fn (Builder $riders): Builder => $riders->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%"));
            })
            ->orderBy('first_name')->orderBy('last_name')->limit(50)->get(['id', 'first_name', 'last_name']);

        return view('logistics.tracking', compact('orders', 'riders', 'areas'));
    }

    public function reports(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = Carbon::parse($validated['from'] ?? today()->subDays(6))->startOfDay();
        $to = Carbon::parse($validated['to'] ?? today())->endOfDay();
        $operator = $this->authenticatedUser();
        $base = $this->hubOrders($operator)->whereBetween('created_at', [$from, $to]);
        $volume = (clone $base)->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupBy('day')->orderBy('day')->get();
        $areaCounts = $this->hubOrders($operator)
            ->select('destination_area_id')
            ->selectRaw('COUNT(*) as total')
            ->with('destinationArea')
            ->whereNotNull('destination_area_id')
            ->whereIn('status', ['SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERED', 'COMPLETED'])
            ->whereBetween('sorted_at', [$from, $to])
            ->groupBy('destination_area_id')
            ->orderByDesc('total')
            ->get();
        $stats = [
            'received' => $this->hubOrders($operator)->whereBetween('received_at', [$from, $to])->count(),
            'sorted' => $this->hubOrders($operator)->whereBetween('sorted_at', [$from, $to])->count(),
            'dispatched' => $this->hubOrders($operator)->whereBetween('assigned_at', [$from, $to])->count(),
            'delivered' => $this->hubOrders($operator)->whereBetween('delivered_at', [$from, $to])->count(),
            'failed' => $this->hubOrders($operator)->whereBetween('failed_at', [$from, $to])->count(),
            'backlog' => $this->hubOrders($operator)->whereIn('status', ['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])->count(),
        ];

        return view('logistics.reports', compact('stats', 'volume', 'areaCounts', 'from', 'to'));
    }

    private function orderNumberSearchPattern(string $search): string
    {
        return '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
    }

    private function hubOrders(User $operator): Builder
    {
        $singleHub = ! $this->isMultiHubOperation();

        return Order::query()->where(function (Builder $query) use ($operator, $singleHub): void {
            $query->where('sorting_center_id', $operator->id)
                ->orWhere(function (Builder $unreceived) use ($operator, $singleHub): void {
                    $unreceived->whereNull('sorting_center_id')->where(function (Builder $areaQuery) use ($operator, $singleHub): void {
                        $areaQuery->whereHas('destinationArea', fn (Builder $area) => $area->where('sorting_center_id', $operator->id));

                        if ($singleHub) {
                            $areaQuery->orWhereHas('destinationArea', fn (Builder $area) => $area->whereNull('sorting_center_id'))
                                ->orWhereDoesntHave('destinationArea');
                        }
                    });
                });
        });
    }

    private function isMultiHubOperation(): bool
    {
        return User::query()->where('role', 'sorting_center')->where('status', 'approved')->limit(2)->count() > 1;
    }

    private function riderBelongsToHub(User $rider, User $operator): bool
    {
        $hasCurrentHubArea = DB::table('area_user')
            ->join('areas', 'areas.id', '=', 'area_user.area_id')
            ->where('area_user.user_id', $rider->id)
            ->where('area_user.is_active', true)
            ->where('areas.is_active', true)
            ->where(function ($query) use ($operator): void {
                $query->where('areas.sorting_center_id', $operator->id);
                if (! $this->isMultiHubOperation()) {
                    $query->orWhereNull('areas.sorting_center_id');
                }
            })
            ->exists();

        if (! $hasCurrentHubArea || ! $this->isMultiHubOperation()) {
            return $hasCurrentHubArea;
        }

        return ! DB::table('area_user')
            ->join('areas', 'areas.id', '=', 'area_user.area_id')
            ->where('area_user.user_id', $rider->id)
            ->where('area_user.is_active', true)
            ->where('areas.is_active', true)
            ->whereNotNull('areas.sorting_center_id')
            ->where('areas.sorting_center_id', '!=', $operator->id)
            ->exists();
    }

    private function assertHubAccess(Order $order, User $operator): void
    {
        abort_unless($operator->role === 'sorting_center' && $operator->status === 'approved', 403);
        abort_unless($order->sorting_center_id === null || (int) $order->sorting_center_id === $operator->id, 403, 'This parcel belongs to another sorting center.');

        $areaHubId = $order->destination_area_id === null
            ? null
            : Area::query()->whereKey($order->destination_area_id)->value('sorting_center_id');
        abort_unless($areaHubId === null || (int) $areaHubId === $operator->id, 403, 'This destination area belongs to another sorting center.');
        abort_unless($areaHubId !== null || $order->sorting_center_id !== null || ! $this->isMultiHubOperation(), 403, 'Assign the destination area to this hub before operating the parcel.');
    }

    private function claimAreaForHub(Area $area, User $operator): void
    {
        DB::transaction(function () use ($area, $operator): void {
            $lockedArea = Area::query()->whereKey($area->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedArea->sorting_center_id === null || (int) $lockedArea->sorting_center_id === $operator->id, 403, 'This routing area belongs to another sorting center.');
            abort_unless(! Order::query()->where('destination_area_id', $lockedArea->id)
                ->whereNotNull('sorting_center_id')
                ->where('sorting_center_id', '!=', $operator->id)
                ->exists(), 403, 'This area has parcels assigned to another sorting center and cannot be claimed.');
            $lockedArea->forceFill(['sorting_center_id' => $operator->id])->save();
        });
    }
}
