@extends('layouts.logistics')
@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Rider dispatch</div>
            <h1>Assign delivery parcels</h1>
            <p>Suggestions match rider service area, active capacity and failed-delivery workload.</p>
        </div>
    </header>
    <section class="ops-panel">
        <form class="ops-form" method="GET" action="{{ route('logistics.dispatch') }}">
            <div class="ops-field"><label for="dispatch-rider-search">Find an approved rider serving these parcels</label><input id="dispatch-rider-search" name="rider_search" value="{{ request('rider_search') }}" maxlength="100" placeholder="Search first or last name"></div>
            <button class="ops-btn" type="submit">Search riders</button>
            @if (request()->filled('rider_search'))<a class="ops-btn" href="{{ route('logistics.dispatch') }}">Clear search</a>@endif
            <p class="ops-muted">Showing up to 50 eligible riders, ranked by active workload and failed-delivery count.</p>
        </form>
    </section>
    <section class="ops-panel">
        <h2>Batch release</h2>
        <p class="ops-muted">Select assigned parcels for one rider, scan that rider’s badge, then scan every selected parcel label. The selected and scanned lists must match exactly.</p>
        <form id="batch-release-form" class="ops-form" method="POST" action="{{ route('logistics.dispatch.releaseBatch') }}">
            @csrf
            <div class="ops-field"><label for="batch-rider-badge">Assigned rider badge QR/code</label><input id="batch-rider-badge" name="rider_badge" data-qr-input required maxlength="100" placeholder="EZR:…" autocomplete="off"></div>
            <button type="button" data-qr-start>Scan rider badge with camera</button><button type="button" data-qr-stop hidden>Stop badge camera</button>
            <video data-qr-video playsinline hidden></video><p data-qr-status role="status">Scan the assigned rider’s badge or enter it with a handheld scanner.</p>
            <input type="hidden" name="method" value="manual">
            <div class="ops-field"><label for="batch-parcel-references">Parcel label QR/codes (one per line)</label><textarea id="batch-parcel-references" name="parcel_references_text" data-qr-batch required rows="4" placeholder="EZP:…"></textarea></div>
            <button type="button" data-qr-batch-start>Scan parcel labels with camera</button><button type="button" data-qr-batch-stop hidden>Stop camera</button>
            <video data-qr-batch-video playsinline hidden></video><p data-qr-batch-status role="status">Scan each selected parcel once, or enter/paste codes one per line.</p>
            <button class="ops-btn ops-btn--primary" type="submit">Validate manifest and release selected parcels</button>
        </form>
    </section>
    <section class="ops-panel">
        <h2>Sorted and assigned parcels <span class="ops-muted">{{ $parcels->total() }}</span></h2>
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
                                @if ($order->status === 'ASSIGNED_TO_RIDER' && ! $order->hub_released_at && $order->deliveryCourier?->status === 'approved' && $order->payment_method === 'COD')
                                    <input type="checkbox" form="batch-release-form" name="order_ids[]" value="{{ $order->id }}" aria-label="Select {{ $order->order_number }} for batch release">
                                @endif
                            </td>
                            <td class="ops-mono">{{ $order->order_number }}<div><span
                                        class="ops-status">{{ str_replace('_', ' ', $order->status) }}</span></div>
                            </td>
                            <td>{{ $order->destinationArea?->name ?? $order->delivery_area }}<div class="ops-muted">{{ $order->barangay }},
                                    {{ $order->municipality }}</div><div class="ops-muted">{{ $order->destinationArea?->name ?? 'Area unresolved' }}</div>
                            </td>
                            <td>{{ $order->deliveryCourier ? $order->deliveryCourier->first_name . ' ' . $order->deliveryCourier->last_name : 'Unassigned' }}
                            </td>
                            <td>
                                @if ($order->status === 'RETURN_IN_TRANSIT')
                                    <span class="ops-muted">{{ $order->return_handed_to_seller_at ? 'Seller receipt confirmation pending' : 'Awaiting courier to record seller handoff' }}</span>
                                @elseif ($order->status === 'DELIVERY_FAILED' || ($order->status === 'OUT_FOR_DELIVERY' && $order->deliveryCourier?->status === 'suspended'))
                                    <div class="ops-muted">{{ $order->deliveryAttempts->count() }} attempts recorded. Scan the parcel into a Returns or Exception location before retry or seller return.</div>
                                    <form class="ops-form" method="POST" action="{{ route('logistics.scan') }}">@csrf
                                        <input type="hidden" name="reference" value="EZP:{{ $order->parcel_code }}">
                                        <input type="hidden" name="method" value="manual">
                                        <div class="ops-field"><label for="return-location-{{ $order->id }}">Returns / Exception location QR or code</label>
                                            <input id="return-location-{{ $order->id }}" name="location_reference" placeholder="EZL:…" required></div>
                                        <button class="ops-btn ops-btn--primary">Confirm physical return to hub</button>
                                    </form>
                                @else
                                @if ($order->status === 'ASSIGNED_TO_RIDER' && $order->hub_released_at)
                                    <div class="ops-muted">Hub handoff is complete. Confirm physical recovery before dispatching this parcel to another rider.</div>
                                    <form method="POST" action="{{ route('logistics.orders.recoverReleasedParcel', $order) }}"
                                        onsubmit="return confirm('Confirm that Logistics physically received this parcel back from the assigned rider?')">
                                        @csrf
                                        <button class="ops-btn ops-btn--primary" type="submit">Record parcel recovered at hub</button>
                                    </form>
                                @else
                                @php($suggestedRider = $suggestedRiders[$order->id] ?? null)
                                @if ($order->status === 'SORTED' && $order->deliveryAttempts->where('outcome', 'failed')->count() >= max(1, (int) config('logistics.maximum_delivery_attempts', 3)))
                                    <div class="ops-muted">Maximum delivery attempts reached. Assign a courier, confirm hub handoff, then start a seller return.</div>
                                @endif
                                @if ($suggestedRider)
                                    <div class="ops-muted">Suggested: {{ $suggestedRider->first_name }} {{ $suggestedRider->last_name }} ({{ $suggestedRider->capacity_load_count }}/{{ $maxActiveDeliveries }} parcels in progress)</div>
                                @else
                                    <div class="ops-muted">No rider matches this area and capacity right now.</div>
                                @endif
                                <form class="ops-form" method="POST"
                                    action="{{ route('logistics.orders.assignRider', $order) }}">@csrf<div
                                        class="ops-field"><label for="rider-{{ $order->id }}">Approved rider ·
                                            workload</label><select id="rider-{{ $order->id }}"
                                            name="delivery_courier_id" required>
                                            <option value="">Select rider</option>
                                            @foreach ($riders as $rider)
                                                @if ($rider->serviceAreas->contains('id', $order->destination_area_id) && ($rider->capacity_load_count < $maxActiveDeliveries || $order->delivery_courier_id === $rider->id))
                                                    <option value="{{ $rider->id }}" @selected($order->delivery_courier_id === $rider->id)>
                                                        {{ $rider->first_name }} {{ $rider->last_name }} ·
                                                        {{ $rider->active_deliveries_count }} active ·
                                                        {{ $rider->serviceAreas->pluck('name')->join(', ') }} area(s)</option>
                                                @endif
                                            @endforeach
                                        </select></div>
                                    <button class="ops-btn ops-btn--primary"
                                        type="submit">{{ $order->delivery_courier_id ? 'Reassign' : 'Assign' }}</button>
                                </form>
                                @endif
                                @if ($order->status === 'ASSIGNED_TO_RIDER')
                                    @if ($order->hub_released_at)
                                        <div class="ops-muted">Hub release recorded {{ $order->hub_released_at->format('d M Y H:i') }}.</div>
                                        <form method="POST" action="{{ route('logistics.orders.return', $order) }}">
                                            @csrf<button class="ops-btn ops-btn--danger" type="submit">Start seller return</button>
                                        </form>
                                    @else
                                        @php($assignedRider = $riders->firstWhere('id', $order->delivery_courier_id))
                                        @if ($assignedRider)
                                            <div class="ops-muted">Verify assigned rider: {{ $assignedRider->first_name }} {{ $assignedRider->last_name }}</div>
                                            <div aria-label="QR badge for assigned rider">{!! $assignedRider->badge_qr !!}</div>
                                        @endif
                                        <form method="POST" action="{{ route('logistics.orders.releaseToRider', $order) }}">
                                            @csrf
                                            <div class="ops-field"><label for="rider-badge-{{ $order->id }}">Scan assigned rider badge QR/code</label>
                                                <input id="rider-badge-{{ $order->id }}" name="rider_badge" required placeholder="EZR:…" autocomplete="off"></div>
                                            <div class="ops-field"><label for="parcel-reference-{{ $order->id }}">Scan parcel label QR/code</label><input id="parcel-reference-{{ $order->id }}" name="parcel_reference" required placeholder="EZP:..." autocomplete="off"></div>
                                            <input type="hidden" name="method" value="manual">
                                            <button class="ops-btn ops-btn--primary" type="submit">Confirm scanned hub handoff</button>
                                        </form>
                                    @endif
                                @endif
                                @endif
                            </td>
                        </tr>
                    @empty<tr>
                            <td colspan="5">
                                <div class="ops-empty">No sorted parcels are waiting for dispatch.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $parcels->links() }}</div>
    </section>
@endsection
