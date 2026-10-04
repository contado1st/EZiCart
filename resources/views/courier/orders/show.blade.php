@extends('layouts.courier')
@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Parcel {{ $order->order_number }}</div>
            <h1>Delivery details</h1>
            <p>{{ $order->recipient_name }} · {{ $order->recipient_contact }} ·
                {{ $order->delivery_area ?? $order->municipality }}</p>
        </div><span class="ops-status">{{ str_replace('_', ' ', $order->status) }}</span>
    </header>
    <section class="ops-grid">
        <div class="ops-panel">
            <h2>Drop-off</h2>
            <p>{{ $order->street_address }}, {{ $order->barangay }}, {{ $order->municipality }}, {{ $order->province }}</p>
            <p>Contact: {{ $order->recipient_contact }}</p>
            <p>Payment: {{ $order->payment_method }} · ₱{{ number_format($order->total_amount, 2) }}</p>
            <p>Order notes: {{ $order->notes ?? 'None' }}</p>
        </div>
        <div class="ops-panel">
            <h2>Parcel contents</h2>
            @forelse($order->items as $item)
            <p>{{ $item->product_name }} × {{ $item->quantity }}</p>@empty<p class="ops-muted">No item summary
                    recorded.</p>
            @endforelse
            <p class="ops-muted">
                Rider assignment: {{ $order->assigned_at?->format('d M Y H:i') ?? '—' }} · Out for delivery:
                {{ $order->out_for_delivery_at?->format('d M Y H:i') ?? 'Not started' }}</p>
        </div>
    </section>
    @if ($order->delivery_courier_id === auth()->id() && $order->status === 'ASSIGNED_TO_RIDER')
        <section class="ops-panel">
            <h2>Start delivery</h2>
            <form method="POST" action="{{ route('courier.orders.startDelivery', $order) }}">@csrf<button
                    class="ops-btn ops-btn--primary">Collect from hub and start</button></form>
        </section>
    @elseif($order->delivery_courier_id === auth()->id() && $order->status === 'OUT_FOR_DELIVERY')
        <section class="ops-panel">
            <h2>Delivery result</h2>
            <div class="ops-grid">
                <form class="ops-form" method="POST" action="{{ route('courier.orders.completeDelivery', $order) }}"
                    onsubmit="return confirm('Confirm this parcel was delivered?')">@csrf @method('PATCH')<div
                        class="ops-field"><label>Recipient confirmation</label><input name="recipient_confirmation"
                            maxlength="120" required></div>
                    @if ($order->payment_method === 'COD')
                        <div class="ops-field"><label>Cash collected
                                (₱{{ number_format($order->total_amount, 2) }})</label><input name="cod_collected_amount"
                                type="number" min="0" step="0.01" required></div>
                    @endif
                    <div class="ops-field">
                        <label>Notes</label><input name="delivery_notes" maxlength="1000">
                    </div><button class="ops-btn ops-btn--primary">Confirm delivered</button>
                </form>
                <form class="ops-form" method="POST" action="{{ route('courier.orders.failDelivery', $order) }}"
                    onsubmit="return confirm('Record a failed delivery attempt?')">@csrf @method('PATCH')<div
                        class="ops-field"><label>Failure reason</label><select name="failure_reason" required>
                            <option value="">Select reason</option>
                            <option value="recipient_unavailable">Recipient unavailable</option>
                            <option value="incorrect_address">Incorrect address</option>
                            <option value="recipient_refused">Recipient refused</option>
                            <option value="unreachable_contact">Unreachable contact</option>
                            <option value="access_issue">Access issue</option>
                            <option value="damaged_parcel">Damaged parcel</option>
                            <option value="other">Other</option>
                        </select></div>
                    <div class="ops-field"><label>Notes</label><input name="delivery_notes" maxlength="1000"></div><button
                        class="ops-btn ops-btn--danger">Record failure</button>
                </form>
            </div>
        </section>
    @endif
    <section class="ops-panel">
        <h2>Parcel tracking timeline</h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Time</th>
                        <th>Location</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->trackingEvents as $event)
                        <tr>
                            <td>{{ str_replace('_', ' ', $event->event_type) }}</td>
                            <td>{{ $event->created_at->format('d M Y H:i') }}</td>
                            <td>{{ $event->location ?? '—' }}</td>
                            <td>{{ $event->notes ?? '—' }}</td>
                    </tr>@empty<tr>
                            <td colspan="4">No tracking updates recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
