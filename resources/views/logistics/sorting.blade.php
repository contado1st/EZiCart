@extends('layouts.logistics')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Sorting queue</h1>
            <p>Sort received parcels into their destination delivery zones for dispatch staging.</p>
        </div>
        <div class="courier-actions">
            <span class="ops-status ops-status--pink">{{ $parcels->total() }} awaiting sort</span>
        </div>
    </header>

    <section class="ops-panel">
        <div class="ops-heading">
            <div>
                <h2>Received parcels staging</h2>
                <p class="ops-muted">Destination areas are normalized by municipality and province for rider route mapping.</p>
            </div>
        </div>

        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Parcel</th>
                        <th>Recipient & address</th>
                        <th>Hub received</th>
                        <th>Resolved routing zone</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($parcels as $order)
                        <tr>
                            <td class="ops-mono">
                                <strong>{{ $order->order_number }}</strong>
                                @if ($order->parcel_code)
                                    <div class="ops-muted">EZP:{{ $order->parcel_code }}</div>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $order->recipient_name }}</strong>
                                <div class="ops-muted">{{ $order->barangay }}, {{ $order->municipality }}, {{ $order->province }}</div>
                                <div class="ops-muted">{{ $order->recipient_contact }}</div>
                            </td>
                            <td>
                                <div>{{ $order->received_at?->format('d M Y H:i') ?? '—' }}</div>
                                <div class="ops-muted">{{ $order->received_at?->diffForHumans() }}</div>
                            </td>
                            <td>
                                <div class="logistics-sort-flow">
                                    <span class="logistics-area">
                                        📍 {{ $order->municipality }}, {{ $order->province }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <form class="ops-form" method="POST" action="{{ route('logistics.orders.sort', $order) }}">
                                    @csrf
                                    <button class="ops-btn ops-btn--primary" type="submit">Confirm area and sort</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="ops-empty">
                                    <strong>Sorting queue clear</strong>
                                    <p>All received parcels have been sorted and staged for dispatch.</p>
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
