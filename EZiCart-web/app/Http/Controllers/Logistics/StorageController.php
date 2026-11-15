<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Order;
use App\Models\ParcelTransfer;
use App\Services\ParcelIncidentService;
use App\Services\QrCodeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class StorageController extends Controller
{
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
            ->paginate(25, ['storage_locations.*', 'active_placements.active_count', 'active_placements.oldest_placement_at'], 'locations_page')->withQueryString();

        $placements = DB::table('parcel_placements')
            ->join('storage_locations', 'storage_locations.id', '=', 'parcel_placements.location_id')
            ->join('orders', 'orders.id', '=', 'parcel_placements.order_id')
            ->where('storage_locations.hub_id', $operator->id)
            ->whereNull('parcel_placements.removed_at')
            ->orderBy('parcel_placements.placed_at')
            ->orderBy('parcel_placements.id')
            ->paginate(25, [
                'parcel_placements.id', 'parcel_placements.order_id', 'parcel_placements.placed_at',
                'storage_locations.code as location_code', 'storage_locations.label as location_label',
                'orders.order_number', 'orders.parcel_code',
            ], 'placements_page')->withQueryString();
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
            ->paginate(25)->withQueryString();

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
                ParcelIncidentService::assertClear($order);
                $location = DB::table('storage_locations')->where('hub_id', $operator->id)->where('code', $locationCode)->lockForUpdate()->first();
                abort_unless($location !== null && $location->is_active, 422, 'The storage location is unknown or inactive.');
                $orderId = $order->id;
                $locationId = $location->id;
                abort_unless((int) $order->sorting_center_id === $operator->id, 403, 'This parcel belongs to another sorting center.');
                abort_if(ParcelTransfer::query()->where('active_order_id', $order->id)->where('status', 'IN_TRANSIT')->exists(), 422, 'A transfer parcel cannot be stored at its former hub while in transit.');
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
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            ParcelIncidentService::assertClear($lockedOrder);
            abort_unless((int) $lockedOrder->sorting_center_id === $operator->id, 403, 'This parcel belongs to another sorting center.');
            abort_if(ParcelTransfer::query()->where('active_order_id', $order->id)->where('status', 'IN_TRANSIT')->exists(), 422, 'This parcel is in transit between hubs.');
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
}
