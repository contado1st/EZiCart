@extends('layouts.logistics')
@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Parcel intake</div>
            <h1>Scan and receive</h1>
            <p>Only parcels picked up by a rider are eligible for hub handoff.</p>
        </div>
    </header>
    <section class="ops-panel">
        <h2>Find a parcel</h2>
        <form class="ops-form" action="{{ route('logistics.scan') }}" method="POST">@csrf<div class="ops-field"><label
                    for="reference">Order number / waybill reference</label><input id="reference" name="reference"
                    value="{{ old('reference', request('search')) }}" required autocomplete="off" placeholder="EZC-…"></div>
            <button class="ops-btn ops-btn--primary" type="submit">Verify and receive</button>
        </form>
        <p class="ops-muted">Barcode scanners can type into this field and submit. Unknown or duplicate references are
            rejected.</p>
    </section>
    <section class="ops-panel">
        <h2>Inbound parcels <span class="ops-muted">{{ $parcels->total() }} awaiting receipt</span></h2>
        <form class="ops-form" method="GET" action="{{ route('logistics.intake') }}">
            <div class="ops-field"><label for="search">Search order number</label><input id="search" name="search"
                    value="{{ request('search') }}"></div><button class="ops-btn" type="submit">Search</button>
        </form>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Seller</th>
                        <th>Pickup rider</th>
                        <th>Destination</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($parcels as $order)
                        <tr>
                            <td class="ops-mono">{{ $order->order_number }}</td>
                            <td>{{ $order->seller?->business_name ?? $order->seller?->first_name }}</td>
                            <td>{{ $order->pickupCourier?->first_name ?? '—' }} {{ $order->pickupCourier?->last_name }}</td>
                            <td>{{ $order->municipality }}, {{ $order->province }}</td>
                            <td>
                                <form method="POST" action="{{ route('logistics.orders.receive', $order) }}">@csrf<button
                                        class="ops-btn ops-btn--primary" type="submit">Confirm hub handoff</button></form>
                            </td>
                        </tr>
                    @empty<tr>
                            <td colspan="5">
                                <div class="ops-empty">No inbound parcels are waiting for intake.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $parcels->links() }}</div>
    </section>
@endsection
