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
use App\Services\OrderAreaService;
use App\Services\OrderTransitionService;
use App\Services\QrCodeService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        $stats = Cache::remember(
            'logistics.dashboard.stats.'.today()->toDateString(),
            now()->addSeconds(max(1, (int) config('logistics.dashboard_cache_seconds', 20))),
            fn (): array => [
                'inbound' => Order::where('status', 'PICKED_UP')->count(),
                'at_center' => Order::where('status', 'AT_SORTING_CENTER')->count(),
                'sorted' => Order::where('status', 'SORTED')->count(),
                'assigned' => Order::where('status', 'ASSIGNED_TO_RIDER')->count(),
                'out' => Order::where('status', 'OUT_FOR_DELIVERY')->count(),
                'delivered_today' => Order::whereIn('status', ['DELIVERED', 'COMPLETED'])->whereBetween('delivered_at', [$todayStart, $tomorrowStart])->count(),
                'failed' => Order::where('status', 'DELIVERY_FAILED')->count(),
                'returned' => Order::whereIn('status', ['RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'])->count(),
                'active_riders' => User::where('role', 'courier')->where('status', 'approved')->count(),
                'available_riders' => User::where('role', 'courier')->where('status', 'approved')->whereDoesntHave('finalDeliveries', fn (Builder $query) => $query->activeCourierWorkload())->count(),
            ],
        );

        $queues = [
            'inbound' => Order::with(['seller', 'pickupCourier'])->where('status', 'PICKED_UP')->latest()->limit(8)->get(),
            'sorting' => Order::with('buyer')->where('status', 'AT_SORTING_CENTER')->latest()->limit(8)->get(),
            'dispatch' => Order::with(['buyer', 'deliveryCourier'])->where('status', 'SORTED')->latest()->limit(8)->get(),
            'failed' => Order::with(['buyer', 'deliveryCourier'])->where('status', 'DELIVERY_FAILED')->latest()->limit(5)->get(),
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
        $parcels = Order::with(['seller', 'pickupCourier'])
            ->where('status', 'PICKED_UP')
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $query->whereRaw("order_number LIKE ? ESCAPE '!'", [$this->orderNumberSearchPattern($request->string('search')->toString())]);
            })
            ->latest()->paginate(15)->withQueryString();

        return view('logistics.intake', compact('parcels'));
    }

    public function pickupRequests(): View
    {
        $orders = Order::query()->with(['seller', 'items', 'pickupCourier'])
            ->where('status', 'READY_FOR_PICKUP')
            ->where('payment_method', 'COD')
            ->whereNotNull('pickup_requested_at')
            ->whereNull('pickup_claimed_at')
            ->latest()->paginate(20);
        $riders = User::query()->where('role', 'courier')->where('status', 'approved')->orderBy('first_name')->get();

        return view('logistics.pickup-requests', compact('orders', 'riders'));
    }

    public function assignPickup(Request $request, Order $order, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        $validated = $request->validate(['pickup_courier_id' => ['required', 'integer', 'exists:users,id']]);
        $operator = $this->authenticatedUser();

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

    public function scan(Request $request, OrderTransitionService $transitions): RedirectResponse
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
            $response = $this->receiveParcel($order, $transitions, $validated['method'] ?? 'manual');
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

    public function receiveParcel(Order $order, OrderTransitionService $transitions, string $method = 'manual'): RedirectResponse
    {
        $center = $this->authenticatedUser();

        $transitions->transition(
            $order,
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

        return back()->with('success', "Parcel {$order->order_number} received at the hub.");
    }

    public function sorting(): View
    {
        $parcels = Order::where('status', 'AT_SORTING_CENTER')
            ->where(fn (Builder $query) => $query->whereNull('sorting_center_id')->orWhere('sorting_center_id', $this->authenticatedUser()->id))
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
        $locations->each(function (object $location) use ($qrCodes): void {
            $location->qr_svg = $qrCodes->svg('EZL:'.$location->code, 120);
        });

        $areas = Area::query()->where('is_active', true)->orderBy('name')->get();

        return view('logistics.storage', compact('locations', 'placements', 'areas'));
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
        $area = $areas->resolve($order);
        $transitions->transition($order, $this->authenticatedUser(), OrderStatus::Sorted, 'sorted', $area->name, "Sorted to destination area {$area->code}.", [
            'destination_area_id' => $area->id,
            'delivery_area' => $area->name,
            'sorted_at' => now(),
        ]);

        return back()->with('success', "Parcel {$order->order_number} sorted to {$area->name}.");
    }

    public function dispatch(): View
    {
        $parcels = Order::whereIn('status', ['SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT'])
            ->where(fn (Builder $query) => $query->whereNull('sorting_center_id')->orWhere('sorting_center_id', $this->authenticatedUser()->id))
            ->with(['buyer', 'deliveryCourier', 'destinationArea', 'deliveryAttempts'])->latest()->paginate(15);
        $pageAreaIds = $parcels->getCollection()->pluck('destination_area_id')->filter()->unique()->all();
        $riders = User::query()->where('role', 'courier')->where('status', 'approved')
            ->whereHas('serviceAreas', fn (Builder $query) => $query->whereIn('areas.id', $pageAreaIds))
            ->with('serviceAreas')->withCount([
                'finalDeliveries as active_deliveries_count' => fn (Builder $query) => $query->activeCourierWorkload(false),
                'finalDeliveries as failed_deliveries_count' => fn (Builder $query) => $query->where('status', 'DELIVERY_FAILED'),
                'finalDeliveries as capacity_load_count' => fn (Builder $query) => $query->activeCourierWorkload(),
            ])->orderBy('first_name')->get();
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
            $previousRiderId = $lockedOrder->delivery_courier_id
                ?? DB::table('delivery_assignments')->where('order_id', $lockedOrder->id)->orderByDesc('assigned_at')->value('rider_id');
            abort_unless(in_array($lockedOrder->status, ['SORTED', 'ASSIGNED_TO_RIDER'], true), 422, 'A failed parcel must be scanned into a hub Returns or Exception location before it can be assigned again.');
            if ($lockedOrder->status === OrderStatus::AssignedToRider->value) {
                abort_unless($lockedOrder->hub_released_at === null, 422, 'This parcel cannot be reassigned after Logistics records the hub handoff.');
            }
            $rider = User::whereKey($validated['delivery_courier_id'])->lockForUpdate()->firstOrFail();
            abort_unless($rider->role === 'courier' && $rider->status === 'approved', 422, 'Choose an approved rider.');
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

    public function releaseToRider(Order $order, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        $operator = $this->authenticatedUser();

        return DB::transaction(function () use ($order, $operator, $notifications): RedirectResponse {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedOrder->sorting_center_id === null || (int) $lockedOrder->sorting_center_id === $operator->id, 403, 'This parcel belongs to another sorting center.');
            abort_unless($lockedOrder->status === OrderStatus::AssignedToRider->value, 422, 'Assign this parcel before releasing it to a rider.');
            abort_unless($lockedOrder->payment_method === 'COD', 422, 'This parcel is on hold until payment verification is configured.');
            abort_unless($lockedOrder->delivery_courier_id !== null && $lockedOrder->hub_released_at === null, 422, 'This parcel has already been released or has no assigned rider.');

            $rider = User::query()->whereKey($lockedOrder->delivery_courier_id)->lockForUpdate()->firstOrFail();
            abort_unless($rider->role === 'courier' && $rider->status === 'approved', 422, 'Only an approved rider can receive a hub release.');

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

    public function recoverReleasedParcel(Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $operator = $this->authenticatedUser();
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

    public function riders(): View
    {
        $riders = User::where('role', 'courier')->with('serviceAreas')->withCount([
            'finalDeliveries as active_deliveries_count' => fn (Builder $query) => $query->activeCourierWorkload(false),
            'finalDeliveries as completed_deliveries_count' => fn (Builder $query) => $query->whereIn('status', ['DELIVERED', 'COMPLETED']),
            'finalDeliveries as failed_deliveries_count' => fn (Builder $query) => $query->where('status', 'DELIVERY_FAILED'),
        ])->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'suspended' THEN 2 ELSE 3 END")->paginate(20);

        $areas = Area::query()->where('is_active', true)->orderBy('name')->get();

        return view('logistics.riders', compact('riders', 'areas'));
    }

    public function areas(): View
    {
        $areas = Area::query()
            ->with('municipalities')
            ->withCount(['riders', 'orders'])
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
        $area = Area::query()->findOrFail($validated['area_id']);
        abort_unless($area->is_active, 422, 'Select an active routing area.');

        $areaMunicipality->update(['area_id' => $area->id]);

        return back()->with('success', "{$areaMunicipality->municipality}, {$areaMunicipality->province} now routes to {$area->name}.");
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
        $validated = $request->validate([
            'area_ids' => ['nullable', 'array'],
            'area_ids.*' => ['integer', 'distinct', 'exists:areas,id'],
        ]);
        $areaIds = array_values(array_unique($validated['area_ids'] ?? []));
        $activeIds = Area::query()->where('is_active', true)->whereKey($areaIds)->pluck('id')->all();
        abort_unless(count($activeIds) === count($areaIds), 422, 'Select active service areas only.');

        DB::transaction(function () use ($user, $activeIds): void {
            DB::table('area_user')->where('user_id', $user->id)->update(['is_active' => false, 'is_primary' => false, 'updated_at' => now()]);
            foreach ($activeIds as $index => $areaId) {
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
        DB::transaction(function () use ($user, $notifications): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedUser->role === 'courier' && $lockedUser->status === 'pending', 404);

            $lockedUser->forceFill(['status' => 'approved'])->save();
            $notifications->send($lockedUser, new AccountStatusNotification('approved'));
        });

        return back()->with('success', "Courier {$user->first_name} {$user->last_name} approved.");
    }

    public function rejectRider(User $user, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        DB::transaction(function () use ($user, $notifications): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedUser->role === 'courier' && $lockedUser->status === 'pending', 404);

            $lockedUser->forceFill(['status' => 'rejected'])->save();
            $notifications->send($lockedUser, new AccountStatusNotification('rejected'));
        });

        return back()->with('success', 'Courier application rejected.');
    }

    public function returnParcel(Order $order, OrderTransitionService $transitions): RedirectResponse
    {
        $operator = $this->authenticatedUser();
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

    public function suspendRider(Request $request, User $user, TransactionAwareNotificationSender $notifications): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($user, $notifications, $validated, $request): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedUser->role === 'courier' && $lockedUser->status === 'approved', 404);

            $lockedUser->forceFill([
                'status' => 'suspended',
                'suspension_previous_status' => 'approved',
                'suspension_source' => 'logistics',
                'suspension_reason' => $validated['reason'],
            ])->save();

            $activeParcels = Order::query()
                ->where(function (Builder $query) use ($lockedUser): void {
                    $query->where(fn (Builder $deliveryQuery) => $deliveryQuery->where('delivery_courier_id', $lockedUser->id)
                        ->whereIn('status', ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT']))
                        ->orWhere(fn (Builder $pickupQuery) => $pickupQuery->where('pickup_courier_id', $lockedUser->id)
                            ->where('status', 'READY_FOR_PICKUP')->whereNotNull('pickup_claimed_at')->whereNull('seller_handover_at'));
                })
                ->lockForUpdate()
                ->get();

            foreach ($activeParcels as $parcel) {
                DB::table('logistics_exceptions')->insert([
                    'order_id' => $parcel->id,
                    'type' => $parcel->pickup_courier_id === $lockedUser->id ? 'SUSPENDED_RIDER_PICKUP_ACCEPTED' : 'RIDER_SUSPENDED_WITH_ACTIVE_PARCEL',
                    'reason' => $validated['reason'],
                    'opened_by' => $request->user()->id,
                    'previous_rider_id' => $lockedUser->id,
                    'new_rider_id' => null,
                    'status' => 'OPEN',
                    'resolution' => null,
                    'resolved_by' => null,
                    'resolved_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $notifications->send($lockedUser, new AccountStatusNotification('suspended'));
        });

        return back()->with('success', 'Rider suspended. Any active parcels are now listed in the Logistics exception queue.');
    }

    public function reactivateRider(User $user): RedirectResponse
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $lockedUser->role === 'courier'
                    && $lockedUser->status === 'suspended'
                    && $lockedUser->suspension_previous_status === 'approved'
                    && $lockedUser->suspension_source === 'logistics',
                404,
            );

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
            'area' => ['nullable', 'integer', 'exists:areas,id'],
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $orders = Order::with(['deliveryCourier', 'pickupCourier', 'trackingEvents.actor'])
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
        $riders = User::where('role', 'courier')->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $areas = Area::query()->orderBy('name')->get(['id', 'name']);

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
        $base = Order::query()->whereBetween('created_at', [$from, $to]);
        $volume = (clone $base)->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupBy('day')->orderBy('day')->get();
        $areaCounts = Order::query()
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
            'received' => Order::whereBetween('received_at', [$from, $to])->count(),
            'sorted' => Order::whereBetween('sorted_at', [$from, $to])->count(),
            'dispatched' => Order::whereBetween('assigned_at', [$from, $to])->count(),
            'delivered' => Order::whereBetween('delivered_at', [$from, $to])->count(),
            'failed' => Order::whereBetween('failed_at', [$from, $to])->count(),
            'backlog' => Order::whereIn('status', ['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])->count(),
        ];

        return view('logistics.reports', compact('stats', 'volume', 'areaCounts', 'from', 'to'));
    }

    private function orderNumberSearchPattern(string $search): string
    {
        return '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
    }
}
