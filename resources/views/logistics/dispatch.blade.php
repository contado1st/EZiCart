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
        <h2>Sorted and assigned parcels <span class="ops-muted">{{ $parcels->total() }}</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Destination</th>
                        <th>Assigned rider</th>
                        <th>Assign / reassign</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($parcels as $order)
                        <tr>
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
                                            <div class="ops-field"><label for="rider-badge-{{ $order->id }}">Rider badge QR/code (optional manual handoff fallback)</label>
                                                <input id="rider-badge-{{ $order->id }}" name="rider_badge" placeholder="EZR:…" autocomplete="off"></div>
                                            <input type="hidden" name="method" value="manual">
                                            <button class="ops-btn ops-btn--primary" type="submit">Confirm hub handoff to rider</button>
                                        </form>
                                    @endif
                                @endif
                                @endif
                            </td>
                        </tr>
                    @empty<tr>
                            <td colspan="4">
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
