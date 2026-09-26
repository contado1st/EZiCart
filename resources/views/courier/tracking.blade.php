@extends('layouts.courier')
@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Private rider tracking</div>
            <h1>My active parcels</h1>
            <p>This view is limited to pickups and deliveries assigned to your account.</p>
        </div>
    </header>
    @forelse($orders as $order)
        <section class="ops-panel">
            <div class="ops-heading">
                <div>
                    <div class="ops-eyebrow ops-mono">{{ $order->order_number }}</div>
                    <h2>{{ $order->municipality }} · {{ str_replace('_', ' ', $order->status) }}</h2>
                </div><span class="ops-status">{{ $order->updated_at->diffForHumans() }}</span>
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
                                <td>{{ str_replace('_', ' ', $event->event_type) }}</td>
                                <td class="ops-mono">{{ $event->created_at->format('d M H:i') }}</td>
                                <td>{{ $event->location ?? '—' }}</td>
                                <td>{{ $event->notes ?? '—' }}</td>
                        </tr>@empty<tr>
                                <td colspan="4">No tracking events recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
    </section>@empty<div class="ops-empty">You have no active parcels.</div>
    @endforelse
    <div class="ops-pagination">{{ $orders->links() }}</div>
@endsection
