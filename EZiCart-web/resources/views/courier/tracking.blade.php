@extends('layouts.courier')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>My active parcels</h1>
            <p>Pickups, hub transfers, and active doorstep deliveries assigned to your rider account.</p>
        </div>
        <div class="courier-actions">
            <a class="ops-btn ops-btn--primary" href="{{ route('courier.dashboard') }}">Back to dispatch board</a>
        </div>
    </header>

    @forelse($orders as $order)
        <section class="ops-panel courier-delivery">
            <div class="ops-heading">
                <div>
                    <p class="ops-mono">Order {{ $order->order_number }}</p>
                    <h2>{{ $order->municipality }} · {{ str_replace('_', ' ', $order->status) }}</h2>
                    <p class="ops-muted">Recipient: {{ $order->recipient_name }} · {{ $order->delivery_area }}</p>
                </div>
                <div class="courier-tracking-state">
                    <span class="ops-status {{ $order->status === 'DELIVERY_FAILED' ? 'ops-status--danger' : ($order->status === 'RETURN_IN_TRANSIT' ? 'ops-status--amber' : 'ops-status--pink') }}">
                        {{ str_replace('_', ' ', $order->status) }}
                    </span>
                    <span class="ops-muted">Updated {{ $order->updated_at->diffForHumans() }}</span>
                </div>
            </div>

            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Time</th>
                            <th>Location</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->trackingEvents as $event)
                            <tr>
                                <td data-label="Event">{{ str_replace('_', ' ', $event->event_type) }}</td>
                                <td data-label="Time" class="ops-mono">{{ $event->created_at->format('d M H:i') }}</td>
                                <td data-label="Location">{{ $event->location ?? '—' }}</td>
                                <td data-label="Details">{{ $event->notes ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="ops-empty">No tracking updates recorded yet.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($order->status !== 'RETURN_IN_TRANSIT')
                <div class="courier-actions ops-alert--compact">
                    <a class="ops-btn ops-btn--primary" href="{{ route('courier.orders.show', $order) }}">Open parcel details</a>
                    <a class="ops-btn" href="{{ route('courier.orders.messages.show', $order) }}">Order messages</a>
                </div>
            @endif
        </section>
    @empty
        <div class="ops-empty">
            <strong>No active parcels</strong>
            <p>You have no pickups or deliveries in progress right now.</p>
        </div>
    @endforelse

    <div class="ops-pagination">{{ $orders->links() }}</div>
@endsection
