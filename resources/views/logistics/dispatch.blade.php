@extends('layouts.logistics')
@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Rider dispatch</div>
            <h1>Assign delivery parcels</h1>
            <p>Riders must be approved and match the destination area when an area is set on their profile.</p>
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
                            <td>{{ $order->delivery_area }}<div class="ops-muted">{{ $order->barangay }},
                                    {{ $order->municipality }}</div>
                            </td>
                            <td>{{ $order->deliveryCourier ? $order->deliveryCourier->first_name . ' ' . $order->deliveryCourier->last_name : 'Unassigned' }}
                            </td>
                            <td>
                                <form class="ops-form" method="POST"
                                    action="{{ route('logistics.orders.assignRider', $order) }}">@csrf<div
                                        class="ops-field"><label for="rider-{{ $order->id }}">Approved rider ·
                                            workload</label><select id="rider-{{ $order->id }}"
                                            name="delivery_courier_id" required>
                                            <option value="">Select rider</option>
                                            @foreach ($riders as $rider)
                                                @if (blank($rider->assigned_area) || $rider->assigned_area === $order->delivery_area)
                                                    <option value="{{ $rider->id }}" @selected($order->delivery_courier_id === $rider->id)>
                                                        {{ $rider->first_name }} {{ $rider->last_name }} ·
                                                        {{ $rider->active_deliveries_count }} active ·
                                                        {{ $rider->assigned_area ?? 'Any area' }}</option>
                                                @endif
                                            @endforeach
                                        </select></div><button class="ops-btn ops-btn--primary"
                                        type="submit">{{ $order->delivery_courier_id ? 'Reassign' : 'Assign' }}</button>
                                </form>
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
