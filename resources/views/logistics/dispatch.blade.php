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
                                    <span class="ops-muted">Awaiting seller receipt confirmation</span>
                                @else
                                @php($suggestedRider = $suggestedRiders[$order->id] ?? null)
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
                                        </select></div><button class="ops-btn ops-btn--primary"
                                        type="submit">{{ $order->delivery_courier_id ? 'Reassign' : 'Assign' }}</button>
                                </form>
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
