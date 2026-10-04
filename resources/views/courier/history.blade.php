@extends('layouts.courier')
@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Courier history</div>
            <h1>Completed and past work</h1>
            <p>Pickup and delivery records attached to your rider account.</p>
        </div>
    </header>
    <section class="ops-panel">
        <form class="ops-form" method="GET">
            <div class="ops-field"><label>Order number</label><input name="search" value="{{ request('search') }}"></div>
            <div class="ops-field"><label>Status</label><select name="status">
                    <option value="">All statuses</option>
                    @foreach (['DELIVERED', 'COMPLETED', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER', 'PICKED_UP'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>
                            {{ str_replace('_', ' ', $status) }}</option>
                    @endforeach
                </select>
            </div><button class="ops-btn ops-btn--primary">Search history</button>
        </form>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Stage</th>
                        <th>Destination</th>
                        <th>Activity</th>
                        <th>Failure / notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="ops-mono">{{ $order->order_number }}</td>
                            <td><span class="ops-status">{{ str_replace('_', ' ', $order->status) }}</span></td>
                            <td>{{ $order->delivery_area ?? $order->municipality }}</td>
                            <td>{{ $order->delivered_at?->format('d M Y H:i') ?? ($order->failed_at?->format('d M Y H:i') ?? ($order->picked_up_at?->format('d M Y H:i') ?? $order->updated_at->format('d M Y H:i'))) }}
                            </td>
                            <td>{{ $order->delivery_failure_reason ?? ($order->delivery_notes ?? '—') }}</td>
                    </tr>@empty<tr>
                            <td colspan="5">
                                <div class="ops-empty">No history matches those filters.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $orders->links() }}</div>
    </section>
@endsection
