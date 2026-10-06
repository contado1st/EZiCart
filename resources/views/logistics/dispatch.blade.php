@extends('layouts.logistics')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Dispatch parcels</h1>
            <p>Assign approved couriers to sorted parcels and release physical manifests from the hub.</p>
        </div>
        <div class="courier-actions">
            <span class="ops-status ops-status--pink">{{ $parcels->total() }} dispatch queue</span>
        </div>
    </header>

    <section class="ops-panel ops-filter-panel" aria-label="Find a rider">
        <form class="ops-form" method="GET" action="{{ route('logistics.dispatch') }}">
            <div class="ops-field">
                <label for="dispatch-rider-search">Find an approved rider serving these parcels</label>
                <input id="dispatch-rider-search"
                    name="rider_search"
                    value="{{ request('rider_search') }}"
                    maxlength="100"
                    placeholder="Search by first or last name…">
            </div>
            <button class="ops-btn" type="submit">Search riders</button>
            @if (request()->filled('rider_search'))
                <a class="ops-btn" href="{{ route('logistics.dispatch') }}">Clear search</a>
            @endif
        </form>
        <p class="ops-muted">Showing up to 50 eligible riders, ranked by active workload and failed-delivery count.</p>
    </section>

    <section class="ops-panel ops-batch-release" aria-labelledby="batch-release-title">
        <div class="ops-heading">
            <div>
                <h2 id="batch-release-title">Release a rider manifest</h2>
                <p class="ops-muted">Select assigned parcels for one rider, scan that rider’s badge, then scan every selected parcel label. The selected and scanned lists must match exactly.</p>
            </div>
        </div>

        <form id="batch-release-form" class="ops-form" method="POST" action="{{ route('logistics.dispatch.releaseBatch') }}">
            @csrf
            <div class="ops-field">
                <label for="batch-rider-badge">Assigned rider badge QR/code</label>
                <input id="batch-rider-badge"
                    name="rider_badge"
                    data-qr-input
                    required
                    maxlength="100"
                    placeholder="EZR:…"
                    autocomplete="off">
            </div>

            <button class="ops-btn" type="button" data-qr-start>Scan rider badge</button>
            <button class="ops-btn ops-btn--danger" type="button" data-qr-stop hidden>Stop badge camera</button>
            <video data-qr-video playsinline hidden></video>
            <p class="ops-scan-status" data-qr-status role="status" aria-live="polite">Scan the assigned rider’s badge or enter it with a handheld scanner.</p>
            <input type="hidden" name="method" value="manual">

            <div class="ops-field">
                <label for="batch-parcel-references">Parcel label QR/codes (one per line)</label>
                <textarea id="batch-parcel-references"
                    name="parcel_references_text"
                    data-qr-batch
                    required
                    rows="4"
                    placeholder="EZP:…"></textarea>
            </div>

            <button class="ops-btn" type="button" data-qr-batch-start>Scan parcel labels</button>
            <button class="ops-btn ops-btn--danger" type="button" data-qr-batch-stop hidden>Stop camera</button>
            <video data-qr-batch-video playsinline hidden></video>
            <p class="ops-scan-status" data-qr-batch-status role="status" aria-live="polite">Scan each selected parcel once, or enter/paste codes one per line.</p>

            <button class="ops-btn ops-btn--primary" type="submit">Validate manifest and release selected parcels</button>
        </form>
    </section>

    <section class="ops-panel">
        <div class="ops-heading">
            <div>
                <h2>Sorted and assigned parcels</h2>
                <p class="ops-muted">{{ $parcels->total() }} parcels staged or assigned to couriers.</p>
            </div>
        </div>

        @if ($parcels->count() > 0 && $riders->isEmpty())
            <div class="ops-empty">
                <p>No eligible riders match the service areas and capacity of these parcels right now.</p>
            </div>
        @endif

        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Select batch</th>
                        <th>Order</th>
                        <th>Destination</th>
                        <th>Assigned rider</th>
                        <th>Assign / reassign</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($parcels as $order)
                        <tr>
                            <td>
                                @if (
                                    $order->status === 'ASSIGNED_TO_RIDER' &&
                                    !$order->hub_released_at &&
                                    $order->deliveryCourier?->status === 'approved' &&
                                    $order->payment_method === 'COD')
                                    <input type="checkbox"
                                        form="batch-release-form"
                                        name="order_ids[]"
                                        value="{{ $order->id }}"
                                        aria-label="Select {{ $order->order_number }} for batch release">
                                @endif
                            </td>
                            <td class="ops-mono">
                                <strong>{{ $order->order_number }}</strong>
                                <div>
                                    <span class="ops-status">{{ str_replace('_', ' ', $order->status) }}</span>
                                </div>
                            </td>
                            <td>
                                <strong>{{ $order->destinationArea?->name ?? $order->delivery_area }}</strong>
                                <div class="ops-muted">{{ $order->barangay }}, {{ $order->municipality }}</div>
                                <div class="ops-muted">{{ $order->destinationArea?->name ?? 'Area unresolved' }}</div>
                            </td>
                            <td>
                                @if ($order->deliveryCourier)
                                    <strong>{{ $order->deliveryCourier->first_name }} {{ $order->deliveryCourier->last_name }}</strong>
                                    <div class="ops-muted">{{ ucfirst($order->deliveryCourier->status) }}</div>
                                @else
                                    <span class="ops-muted">Unassigned</span>
                                @endif
                            </td>
                            <td>
                                @if ($order->status === 'RETURN_IN_TRANSIT')
                                    <span class="ops-muted">
                                        {{ $order->return_handed_to_seller_at ? 'Seller receipt confirmation pending' : 'Awaiting courier to record seller handoff' }}
                                    </span>
                                @elseif (
                                    $order->status === 'DELIVERY_FAILED' ||
                                    ($order->status === 'OUT_FOR_DELIVERY' && $order->deliveryCourier?->status === 'suspended'))
                                    <div class="ops-muted">
                                        {{ $order->deliveryAttempts->count() }} attempts recorded. Scan the parcel into a Returns or Exception location before retry or seller return.
                                    </div>
                                    <form class="ops-form" method="POST" action="{{ route('logistics.scan') }}">
                                        @csrf
                                        <input type="hidden" name="reference" value="EZP:{{ $order->parcel_code }}">
                                        <input type="hidden" name="method" value="manual">
                                        <div class="ops-field">
                                            <label for="return-location-{{ $order->id }}">Returns / Exception location QR or code</label>
                                            <input id="return-location-{{ $order->id }}"
                                                name="location_reference"
                                                placeholder="EZL:…"
                                                required>
                                        </div>
                                        <button class="ops-btn ops-btn--primary" type="submit">Confirm physical return to hub</button>
                                    </form>
                                @else
                                    @if ($order->status === 'ASSIGNED_TO_RIDER' && $order->hub_released_at)
                                        <div class="ops-muted">
                                            Hub handoff is complete. Confirm physical recovery before dispatching this parcel to another rider.
                                        </div>
                                        <form method="POST"
                                            action="{{ route('logistics.orders.recoverReleasedParcel', $order) }}"
                                            onsubmit="return confirm('Confirm that Logistics physically received this parcel back from the assigned rider?')">
                                            @csrf
                                            <button class="ops-btn ops-btn--primary" type="submit">Record parcel recovered at hub</button>
                                        </form>
                                    @else
                                        @php($suggestedRider = $suggestedRiders[$order->id] ?? null)
                                        @if (
                                            $order->status === 'SORTED' &&
                                            $order->deliveryAttempts->where('outcome', 'failed')->count() >=
                                                max(1, (int) config('logistics.maximum_delivery_attempts', 3)))
                                            <div class="ops-muted">
                                                Maximum delivery attempts reached. Assign a courier, confirm hub handoff, then start a seller return.
                                            </div>
                                        @endif
                                        @if ($suggestedRider)
                                            <div class="ops-muted">
                                                Suggested: {{ $suggestedRider->first_name }} {{ $suggestedRider->last_name }}
                                                ({{ $suggestedRider->capacity_load_count }}/{{ $maxActiveDeliveries }} parcels in progress)
                                            </div>
                                        @else
                                            <div class="ops-muted">No rider matches this area and capacity right now.</div>
                                        @endif
                                        <form class="ops-form" method="POST" action="{{ route('logistics.orders.assignRider', $order) }}">
                                            @csrf
                                            <div class="ops-field">
                                                <label for="rider-{{ $order->id }}">Approved rider · workload</label>
                                                <select id="rider-{{ $order->id }}" name="delivery_courier_id" required>
                                                    <option value="">Select rider</option>
                                                    @foreach ($riders as $rider)
                                                        @if (
                                                            $rider->serviceAreas->contains('id', $order->destination_area_id) &&
                                                            ($rider->capacity_load_count < $maxActiveDeliveries || $order->delivery_courier_id === $rider->id))
                                                            <option value="{{ $rider->id }}" @selected($order->delivery_courier_id === $rider->id)>
                                                                {{ $rider->first_name }} {{ $rider->last_name }} ·
                                                                {{ $rider->active_deliveries_count }} active ·
                                                                {{ $rider->serviceAreas->pluck('name')->join(', ') }} area(s)
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                            <button class="ops-btn ops-btn--primary" type="submit">
                                                {{ $order->delivery_courier_id ? 'Reassign' : 'Assign' }}
                                            </button>
                                        </form>
                                    @endif
                                    @if ($order->status === 'ASSIGNED_TO_RIDER')
                                        @if ($order->hub_released_at)
                                            <div class="ops-muted">
                                                Hub release recorded {{ $order->hub_released_at->format('d M Y H:i') }}.
                                            </div>
                                            <form method="POST" action="{{ route('logistics.orders.return', $order) }}">
                                                @csrf
                                                <button class="ops-btn ops-btn--danger" type="submit">Start seller return</button>
                                            </form>
                                        @else
                                            @php($assignedRider = $riders->firstWhere('id', $order->delivery_courier_id))
                                            @if ($assignedRider)
                                                <div class="ops-muted">
                                                    Verify assigned rider: {{ $assignedRider->first_name }} {{ $assignedRider->last_name }}
                                                </div>
                                                <div class="logistics-badge-qr" aria-label="QR badge for assigned rider">
                                                    {!! $assignedRider->badge_qr !!}
                                                </div>
                                            @endif
                                            <form method="POST" action="{{ route('logistics.orders.releaseToRider', $order) }}">
                                                @csrf
                                                <div class="ops-field">
                                                    <label for="rider-badge-{{ $order->id }}">Scan assigned rider badge QR/code</label>
                                                    <input id="rider-badge-{{ $order->id }}"
                                                        name="rider_badge"
                                                        required
                                                        placeholder="EZR:…"
                                                        autocomplete="off">
                                                </div>
                                                <div class="ops-field">
                                                    <label for="parcel-reference-{{ $order->id }}">Scan parcel label QR/code</label>
                                                    <input id="parcel-reference-{{ $order->id }}"
                                                        name="parcel_reference"
                                                        required
                                                        placeholder="EZP:…"
                                                        autocomplete="off">
                                                </div>
                                                <input type="hidden" name="method" value="manual">
                                                <button class="ops-btn ops-btn--primary" type="submit">Confirm scanned hub handoff</button>
                                            </form>
                                        @endif
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="ops-empty">
                                    <strong>No parcels staged</strong>
                                    <p>No sorted parcels are waiting for dispatch.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $parcels->links() }}</div>
    </section>
@endsection
