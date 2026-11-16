@extends('layouts.courier')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Completed and past work</h1>
            <p>Your pickup, delivery and hub-transfer records. Each order appears once; expand compensation to review its work components.</p>
        </div>
        <div class="courier-actions">
            <a class="ops-btn ops-btn--primary" href="{{ route('courier.dashboard') }}">Back to dispatch board</a>
        </div>
    </header>

    <section class="ops-panel">
        <p>Verified net settled credits on this page: <strong>PHP {{ number_format($pageSettledCents / 100, 2) }}</strong>. Estimates and held components are excluded. This page total is not your wallet balance.</p>
        <form class="ops-form" method="GET" action="{{ route('courier.history') }}" aria-label="Filter delivery history">
            <div class="ops-field">
                <label for="history-search">Order number</label>
                <input id="history-search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="e.g. ORD-10293">
            </div>
            <div class="ops-field">
                <label for="history-status">Status</label>
                <select id="history-status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($historyStatuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>
                            {{ str_replace('_', ' ', $status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button class="ops-btn ops-btn--primary" type="submit">Apply filters</button>
            @if (request()->filled('search') || request()->filled('status'))
                <a class="ops-btn" href="{{ route('courier.history') }}">Clear filters</a>
            @endif
        </form>

        @if (request()->filled('search') || request()->filled('status'))
            <ul class="ops-filter-chips" aria-label="Active filters">
                @if (request()->filled('search'))
                    <li>Order: <strong>{{ request('search') }}</strong></li>
                @endif
                @if (request()->filled('status'))
                    <li>Status: <strong>{{ str_replace('_', ' ', request('status')) }}</strong></li>
                @endif
            </ul>
        @endif

        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Parcel stage</th>
                        <th>Destination</th>
                        <th>Parcel activity</th>
                        <th>My attempt / notes</th>
                        <th>My compensation</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="ops-mono" data-label="Order">
                                <strong>{{ $order->order_number }}</strong><x-parcel-weight :order="$order" compact /><x-order-table-products :items="$order->items" />
                            </td>
                            <td data-label="Stage">
                                <span class="ops-status {{ $order->status === 'DELIVERY_FAILED' ? 'ops-status--danger' : (in_array($order->status, ['DELIVERED', 'COMPLETED', 'PICKED_UP'], true) ? 'ops-status--green' : 'ops-status--amber') }}">
                                    {{ str_replace('_', ' ', $order->operational_status) }}
                                </span>
                            </td>
                            <td data-label="Destination">{{ $order->delivery_area ?? $order->municipality }}</td>
                            <td data-label="Activity" class="ops-mono">
                                {{ $order->delivered_at?->format('d M Y H:i') ?? ($order->failed_at?->format('d M Y H:i') ?? ($order->picked_up_at?->format('d M Y H:i') ?? $order->updated_at->format('d M Y H:i'))) }}
                            </td>
                            <td data-label="Notes">
                                @php
                                    $ownAttempt = $order->deliveryAttempts->where('rider_id', auth()->id())->sortByDesc('id')->first();
                                    $ownsDelivery = (int) $order->delivery_courier_id === auth()->id();
                                    $ownReason = $ownAttempt?->reason ?: ($ownsDelivery ? $order->delivery_failure_reason : null);
                                    $ownNotes = $ownAttempt?->notes ?: ($ownsDelivery ? $order->delivery_notes : null);
                                @endphp
                                @if ($ownReason)
                                    <span class="courier-failed-reason-tag">
                                        {{ str_replace('_', ' ', ucfirst($ownReason)) }}
                                    </span>
                                @else
                                    {{ $ownNotes ?? '—' }}
                                @endif
                            </td>
                            <td data-label="My compensation">
                                <details><summary>View work &amp; compensation</summary><x-courier-compensation :summary="$workSummaries[$order->id]" /><x-delivery-retry-proposal :order="$order" :context="$retryContexts[$order->id]" /></details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="ops-empty">No delivery history matches those filters.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $orders->links() }}</div>
    </section>
@endsection
