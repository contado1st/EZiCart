@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
@endpush

@section('content')
    <div class="dash-wrapper">
        <aside class="dash-sidebar">
            <div>
                <div class="dash-profile-badge">
                    <span class="dash-role-tag">Seller Portal</span>
                    <h2 class="dash-profile-title">{{ auth()->user()->business_name ?? 'My Store' }}</h2>
                    <p class="dash-profile-subtitle">Order management</p>
                </div>
                <nav class="dash-nav">
                    <a href="{{ route('seller.dashboard') }}" class="dash-nav-item">Dashboard</a>
                    <a href="{{ route('seller.products.index') }}" class="dash-nav-item">Inventory</a>
                    <a href="{{ route('seller.orders.index') }}" class="dash-nav-item active">Orders</a>
                </nav>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="dash-logout-btn">Logout</button>
            </form>
        </aside>

        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Order {{ $order->order_number }}</h1>
                    <p class="dash-subtitle">Placed {{ $order->created_at->format('M d, Y h:i A') }}</p>
                </div>
                <a href="{{ route('seller.orders.index') }}" class="dash-btn-sm dash-btn-outline">Back to orders</a>
            </div>

            <section class="dash-panel">
                <div class="order-card-header">
                    <div>
                        <span class="order-number">{{ $order->order_number }}</span>
                        <div class="order-date">Payment: {{ $order->payment_method }}</div>
                    </div>
                    <span
                        class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">{{ str_replace('_', ' ', $order->status) }}</span>
                </div>

                <h2 class="courier-section-title">Recipient</h2>
                <p><strong>{{ $order->recipient_name }}</strong> · {{ $order->recipient_contact }}</p>
                <p>{{ $order->street_address }}, {{ $order->barangay }}, {{ $order->municipality }},
                    {{ $order->province }}</p>

                <h2 class="courier-section-title">Items</h2>
                @foreach ($order->items as $item)
                    <div class="order-item-row">
                        <div>
                            <strong>{{ $item->product_name }}</strong>
                            @if ($item->variation_info)
                                <span>({{ $item->variation_info }})</span>
                            @endif
                            <span>&times; {{ $item->quantity }}</span>
                        </div>
                        <div>{{ number_format($item->item_total, 2) }}</div>
                    </div>
                @endforeach

                <h2 class="courier-section-title">Order summary</h2>
                @if ($order->payment_method !== 'COD' && in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true))
                    <p role="status">Fulfillment is on hold because payment verification for {{ $order->payment_method }} is not configured yet.</p>
                @endif
                <p>Subtotal: {{ number_format($order->subtotal, 2) }}</p>
                @if ($order->discount_amount)
                    <p>Discount: −{{ number_format($order->discount_amount, 2) }} ({{ $order->voucher_code }})</p>
                @endif
                <p>Platform commission: {{ number_format($order->commission_fee, 2) }}</p>
                <p>Shipping: {{ number_format($order->shipping_fee, 2) }}</p>
                <p><strong>Total: {{ number_format($order->total_amount, 2) }}</strong></p>
                @if ($order->notes)
                    <p>Order notes: {{ $order->notes }}</p>
                @endif

                <div class="order-card-footer">
                    <a href="{{ route('seller.orders.waybill', $order->id) }}" class="dash-btn-sm dash-btn-outline">View
                        waybill</a>
                    <a href="{{ route('seller.orders.messages.show', $order) }}" class="dash-btn-sm dash-btn-outline">Message buyer</a>
                    @if ($order->payment_method !== 'COD' && in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true))
                        <span>Fulfillment is on hold until payment verification is configured.</span>
                    @elseif ($order->status === 'PLACED')
                        <form action="{{ route('seller.orders.updateStatus', $order->id) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="CONFIRMED">
                            <button type="submit" class="dash-btn-sm dash-btn-primary">Accept order</button>
                        </form>
                    @elseif($order->status === 'CONFIRMED')
                        <form action="{{ route('seller.orders.updateStatus', $order->id) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="PREPARING">
                            <button type="submit" class="dash-btn-sm dash-btn-primary">Start preparing</button>
                        </form>
                    @elseif($order->status === 'PREPARING')
                        <form action="{{ route('seller.orders.updateStatus', $order->id) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="READY_FOR_PICKUP">
                            <button type="submit" class="dash-btn-sm dash-btn-primary">Mark ready for pickup</button>
                        </form>
                    @elseif($order->status === 'READY_FOR_PICKUP' && ! $order->pickup_requested_at)
                        <form action="{{ route('seller.orders.schedulePickup', $order) }}" method="POST" class="order-schedule-form">
                            @csrf
                            <label for="pickup-scheduled-for">Pickup date and time</label>
                            <input id="pickup-scheduled-for" type="datetime-local" name="pickup_scheduled_for" min="{{ now()->addMinutes(15)->format('Y-m-d\\TH:i') }}" required>
                            <label for="pickup-window">Pickup window</label>
                            <select id="pickup-window" name="pickup_window" required>
                                <option value="">Choose a window</option>
                                <option value="Morning (8 AM–12 PM)">Morning (8 AM–12 PM)</option>
                                <option value="Afternoon (12 PM–5 PM)">Afternoon (12 PM–5 PM)</option>
                                <option value="Evening (5 PM–8 PM)">Evening (5 PM–8 PM)</option>
                            </select>
                            <label for="pickup-notes">Pickup instructions (optional)</label>
                            <textarea id="pickup-notes" name="pickup_notes" maxlength="1000"></textarea>
                            <button type="submit" class="dash-btn-sm dash-btn-primary">Request pickup</button>
                        </form>
                    @elseif($order->status === 'READY_FOR_PICKUP' && $order->pickup_requested_at && ! $order->pickup_arrived_at)
                        <p>Pickup requested for {{ $order->pickup_scheduled_for?->format('M d, Y h:i A') }} · {{ $order->pickup_window }}. Waiting for Logistics to assign a rider.</p>
                    @elseif($order->status === 'READY_FOR_PICKUP' && $order->pickup_arrived_at && ! $order->seller_handover_at)
                        <form action="{{ route('seller.orders.confirmHandover', $order) }}" method="POST">
                            @csrf
                            <label for="handover-rider-badge">Scan assigned rider badge QR/code</label>
                            <input id="handover-rider-badge" name="rider_badge" data-qr-input required maxlength="100" placeholder="EZR:…" autocomplete="off">
                            <input type="hidden" name="method" value="manual">
                            <button type="button" data-qr-start>Use camera</button><button type="button" data-qr-stop hidden>Stop camera</button>
                            <video data-qr-video playsinline hidden></video><p data-qr-status role="status">Camera, handheld scanner, or manual code entry is supported.</p>
                            <button type="submit" class="dash-btn-sm dash-btn-primary">Confirm rider handover</button>
                        </form>
                    @elseif($order->status === 'RETURN_IN_TRANSIT')
                        @if ($order->return_handed_to_seller_at)
                            <form action="{{ route('seller.orders.confirmReturn', $order) }}" method="POST">
                                @csrf
                                <label for="return-rider-badge">Scan return rider badge QR/code</label>
                                <input id="return-rider-badge" name="rider_badge" data-qr-input required maxlength="100" placeholder="EZR:…" autocomplete="off">
                                <input type="hidden" name="method" value="manual">
                                <button type="button" data-qr-start>Use camera</button><button type="button" data-qr-stop hidden>Stop camera</button>
                                <video data-qr-video playsinline hidden></video><p data-qr-status role="status">Camera, handheld scanner, or manual entry.</p>
                                <button type="submit" class="dash-btn-sm dash-btn-primary">Confirm scanned return received</button>
                            </form>
                        @else
                            <span class="text-muted-small">Awaiting courier handoff</span>
                        @endif
                    @endif
                    @if (in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true) && $order->picked_up_at === null && $order->seller_handover_at === null)
                        <form action="{{ route('seller.orders.cancel', $order) }}" method="POST" class="order-schedule-form">
                            @csrf
                            <label for="seller-cancel-reason">Reason for cancelling</label>
                            <textarea id="seller-cancel-reason" name="reason" maxlength="500" required></textarea>
                            <button type="submit" class="dash-btn-sm dash-btn-outline">Cancel order</button>
                        </form>
                    @endif
                </div>
            </section>
        </main>
    </div>
@endsection
