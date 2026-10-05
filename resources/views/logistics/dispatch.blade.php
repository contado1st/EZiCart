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
                                @if ($order->status === 'DELIVERY_FAILED')
                                    <div class="ops-muted">{{ $order->deliveryAttempts->count() }} attempts recorded. Schedule the next attempt or return the parcel.</div>
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
                                    @if ($order->status === 'DELIVERY_FAILED')
                                        <div class="ops-field"><label for="retry-schedule-{{ $order->id }}">Retry date and time</label>
                                            <input id="retry-schedule-{{ $order->id }}" type="datetime-local" name="scheduled_at" min="{{ now()->addMinutes(15)->format('Y-m-d\\TH:i') }}" required>
                                        </div>
                                    @endif
                                    <button class="ops-btn ops-btn--primary"
                                        type="submit">{{ $order->delivery_courier_id ? 'Reassign' : 'Assign' }}</button>
                                </form>
                                @endif
                                @if ($order->status === 'ASSIGNED_TO_RIDER')
                                    @if ($order->hub_released_at)
                                        <div class="ops-muted">Hub release recorded {{ $order->hub_released_at->format('d M Y H:i') }}.</div>
                                    @else
                                        <form method="POST" action="{{ route('logistics.orders.releaseToRider', $order) }}">
                                            @csrf
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
