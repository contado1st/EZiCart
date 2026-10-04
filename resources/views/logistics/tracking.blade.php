@extends('layouts.logistics')
@section('workspace')
    <header class="ops-heading">
        <div>
            <div class="ops-eyebrow">Active parcel control</div>
            <h1>Delivery tracking</h1>
            <p>Current stage, rider ownership, elapsed time and persisted parcel events.</p>
        </div>
    </header>
    <section class="ops-panel">
        <form class="ops-form" method="GET" action="{{ route('logistics.tracking') }}">
            <div class="ops-field"><label>Order number</label><input name="search" value="{{ request('search') }}"></div>
            <div class="ops-field"><label>Status</label><select name="status">
                    <option value="">All active</option>
                    @foreach (['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>
                            {{ str_replace('_', ' ', $status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ops-field"><label>Rider</label><select name="rider">
                    <option value="">All riders</option>
                    @foreach ($riders as $rider)
                        <option value="{{ $rider->id }}" @selected((string) request('rider') === (string) $rider->id)>{{ $rider->first_name }}
                            {{ $rider->last_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ops-field"><label>Area</label><select name="area">
                    <option value="">All areas</option>
                    @foreach ($areas as $area)
                        <option @selected(request('area') === $area)>{{ $area }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ops-field"><label>Updated date</label><input type="date" name="date"
                    value="{{ request('date') }}"></div><button class="ops-btn ops-btn--primary">Apply filters</button>
        </form>
    </section>
    @foreach ($orders as $order)
        <section class="ops-panel">
            <div class="ops-heading">
                <div>
                    <div class="ops-eyebrow ops-mono">{{ $order->order_number }}</div>
                    <h2>{{ $order->recipient_name }} · {{ $order->municipality }}</h2>
                    <p>{{ str_replace('_', ' ', $order->status) }} ·
                        {{ $order->deliveryCourier?->first_name ?? 'No delivery rider' }} · updated
                        {{ $order->updated_at->diffForHumans() }}</p>
                </div><span
                    class="ops-status {{ $order->status === 'DELIVERY_FAILED' ? 'ops-status--amber' : '' }}">{{ str_replace('_', ' ', $order->status) }}</span>
                @if ($order->status === 'DELIVERY_FAILED')
                    <form method="POST" action="{{ route('logistics.orders.return', $order) }}"
                        onsubmit="return confirm('Move this parcel to return handling?')">@csrf<button
                            class="ops-btn ops-btn--danger">Return to sender</button></form>
                @endif
                @if ($order->status === 'RETURN_IN_TRANSIT')
                    <span class="ops-muted">Awaiting seller receipt confirmation</span>
                @endif
                @if ($order->messageParticipants()->contains('id', auth()->id()))
                    <a class="ops-btn" href="{{ route('logistics.orders.messages.show', $order) }}">Order messages</a>
                @endif
            </div>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Location</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->trackingEvents as $event)
                            <tr>
                                <td>{{ str_replace('_', ' ', $event->event_type) }}</td>
                                <td class="ops-mono">{{ $event->created_at->format('d M Y H:i') }}</td>
                                <td>{{ $event->actor ? $event->actor->first_name . ' ' . $event->actor->last_name : 'System' }}
                                </td>
                                <td>{{ $event->location ?? '—' }}</td>
                                <td>{{ $event->notes ?? '—' }}</td>
                        </tr>@empty<tr>
                                <td colspan="5" class="ops-muted">No recorded events yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
    @if ($orders->isEmpty())
        <div class="ops-empty">No parcels match the selected filters.</div>
    @endif
    <div class="ops-pagination">{{ $orders->links() }}</div>
@endsection
