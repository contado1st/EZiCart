@extends('layouts.logistics')
@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Parcel intake · area routing</div>
            <h1>Sorting queue</h1>
            <p>The parcel area is resolved from its normalized province and municipality.</p>
        </div>
    </header>
    <section class="ops-panel">
        <h2>Received parcels <span class="ops-muted">{{ $parcels->total() }} waiting</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Recipient</th>
                        <th>Destination</th>
                        <th>Received</th>
                        <th>Resolved area</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($parcels as $order)
                        <tr>
                            <td class="ops-mono">{{ $order->order_number }}</td>
                            <td>{{ $order->recipient_name }}<div class="ops-muted">{{ $order->recipient_contact }}</div>
                            </td>
                            <td>{{ $order->barangay }}, {{ $order->municipality }}, {{ $order->province }}</td>
                            <td>{{ $order->received_at?->format('d M H:i') ?? '—' }}</td>
                            <td>
                                <form class="ops-form" method="POST" action="{{ route('logistics.orders.sort', $order) }}">
                                    @csrf
                                    <div class="ops-muted">{{ $order->municipality }}, {{ $order->province }}</div>
                                    <button class="ops-btn ops-btn--primary" type="submit">Resolve area and sort</button>
                                </form>
                            </td>
                        </tr>
                    @empty<tr>
                            <td colspan="5">
                                <div class="ops-empty">All received parcels have been sorted.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $parcels->links() }}</div>
    </section>
@endsection
