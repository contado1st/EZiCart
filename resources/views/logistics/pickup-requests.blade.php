@extends('layouts.logistics')

@section('workspace')
    <header class="ops-heading">
        <div>
            <h1>Seller pickup requests</h1>
            <p>Review new seller pickup requests, dispatch approved couriers, or reassign unclaimed routes.</p>
        </div>
        <div class="courier-actions">
            <span class="ops-status ops-status--pink">{{ $orders->total() }} pending pickup</span>
        </div>
    </header>

    <section class="ops-panel">
        <div class="ops-heading">
            <div>
                <h2>Pickup requests queue</h2>
                <p class="ops-muted">Approved riders serving the seller's area are eligible for pickup dispatch.</p>
            </div>
        </div>

        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order & schedule</th>
                        <th>Seller pickup point</th>
                        <th>Parcel items</th>
                        <th>Rider assignment</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="ops-mono">
                                <strong>{{ $order->order_number }}</strong>
                                <div class="ops-muted">
                                    {{ $order->pickup_scheduled_for?->format('d M Y, h:i A') ?? 'Immediate dispatch' }}
                                    @if ($order->pickup_window)
                                        · {{ $order->pickup_window }}
                                    @endif
                                </div>
                                @if ($order->pickup_notes)
                                    <div class="ops-muted">Note: {{ $order->pickup_notes }}</div>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $order->seller?->business_name ?? $order->seller?->first_name }}</strong>
                                <div class="ops-muted">
                                    {{ $order->seller?->street_address }}, {{ $order->seller?->municipality }}
                                </div>
                                <div class="ops-muted">{{ $order->seller?->contact_no }}</div>
                            </td>
                            <td>
                                <strong>{{ $order->items->count() }} item(s)</strong>
                                <div class="ops-muted">₱{{ number_format($order->total_amount, 2) }} (COD)</div>
                            </td>
                            <td>
                                @if ($order->pickupCourier)
                                    <div class="ops-muted">
                                        Current: <strong>{{ $order->pickupCourier->first_name }} {{ $order->pickupCourier->last_name }}</strong>
                                        ({{ ucfirst($order->pickupCourier->status) }})
                                    </div>
                                @endif
                                <form class="ops-form" method="POST" action="{{ route('logistics.orders.assignPickup', $order) }}">
                                    @csrf
                                    <div class="ops-field">
                                        <label for="pickup-rider-{{ $order->id }}">
                                            {{ $order->pickupCourier ? 'Replacement rider' : 'Select approved rider' }}
                                        </label>
                                        <select id="pickup-rider-{{ $order->id }}" name="pickup_courier_id" required>
                                            <option value="">Select rider</option>
                                            @foreach ($riders as $rider)
                                                <option value="{{ $rider->id }}">
                                                    {{ $rider->first_name }} {{ $rider->last_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button class="ops-btn ops-btn--primary" type="submit" @disabled($riders->isEmpty())>
                                        {{ $order->pickupCourier ? 'Reassign pickup' : 'Assign pickup' }}
                                    </button>
                                    @if ($riders->isEmpty())
                                        <p class="ops-muted">No approved riders match this area currently.</p>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="ops-empty">
                                    <strong>No pickup requests</strong>
                                    <p>New seller pickup requests will appear here for rider assignment.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ops-pagination">{{ $orders->links() }}</div>
    </section>
@endsection
