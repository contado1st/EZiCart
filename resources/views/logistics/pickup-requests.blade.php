@extends('layouts.logistics')
@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Seller pickup queue</div>
            <h1>Review pickup requests</h1>
            <p>Assign an approved rider, or reassign a pickup while the current rider has not accepted it.</p>
        </div>
    </header>
    <section class="ops-panel">
        <h2>Unclaimed pickup requests <span class="ops-muted">{{ $orders->total() }}</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead><tr><th>Order</th><th>Seller pickup point</th><th>Contents</th><th>Assign rider</th></tr></thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="ops-mono">{{ $order->order_number }}
                                <div class="ops-muted">{{ $order->pickup_scheduled_for?->format('M d, Y h:i A') }} · {{ $order->pickup_window }}</div>
                                @if ($order->pickup_notes)<div class="ops-muted">{{ $order->pickup_notes }}</div>@endif
                            </td>
                            <td>{{ $order->seller?->business_name ?? $order->seller?->first_name }}
                                <div class="ops-muted">{{ $order->seller?->street_address }}, {{ $order->seller?->municipality }}</div>
                            </td>
                            <td>{{ $order->items->count() }} item(s)</td>
                            <td>
                                @if ($order->pickupCourier)
                                    <p class="ops-muted">Currently assigned: {{ $order->pickupCourier->first_name }} {{ $order->pickupCourier->last_name }} ({{ ucfirst($order->pickupCourier->status) }})</p>
                                @endif
                                <form class="ops-form" method="POST" action="{{ route('logistics.orders.assignPickup', $order) }}">
                                    @csrf
                                    <div class="ops-field"><label for="pickup-rider-{{ $order->id }}">{{ $order->pickupCourier ? 'Replacement rider' : 'Approved rider' }}</label>
                                        <select id="pickup-rider-{{ $order->id }}" name="pickup_courier_id" required>
                                            <option value="">Select rider</option>
                                            @foreach ($riders as $rider)
                                                <option value="{{ $rider->id }}">{{ $rider->first_name }} {{ $rider->last_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button class="ops-btn ops-btn--primary" type="submit">{{ $order->pickupCourier ? 'Reassign pickup' : 'Assign pickup' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="ops-empty">No seller pickup requests are waiting.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $orders->links() }}</div>
    </section>
@endsection
