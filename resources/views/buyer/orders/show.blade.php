@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
    @vite('resources/css/shared/disputes.css')
@endpush

@section('content')
    <div class="dash-wrapper">
        <aside class="dash-sidebar">
            <div>
                <div class="dash-profile-badge">
                    <span class="dash-role-tag">Buyer Portal</span>
                    <h2 class="dash-profile-title">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h2>
                    <p class="dash-profile-subtitle">Order details</p>
                </div>
                <nav class="dash-nav">
                    <a href="{{ route('buyer.dashboard') }}" class="dash-nav-item active">My Orders</a>
                    <a href="{{ route('cart.index') }}" class="dash-nav-item">View Cart</a>
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
                    <p class="dash-subtitle">Placed {{ $order->created_at->format('M d, Y h:i A') }} with
                        {{ $order->seller->business_name ?? 'Merchant' }}</p>
                </div>
                <a href="{{ route('buyer.dashboard') }}" class="dash-btn-sm dash-btn-outline">Back to orders</a>
            </div>

            <section class="dash-panel">
                <div class="order-card-header">
                    <div>
                        <span class="order-number">{{ $order->order_number }}</span>
                        <div class="order-date">{{ $order->payment_method }}</div>
                    </div>
                    <span
                        class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">{{ str_replace('_', ' ', $order->status) }}</span>
                </div>

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

                <h2 class="courier-section-title">Delivery</h2>
                <p><strong>{{ $order->recipient_name }}</strong> · {{ $order->recipient_contact }}</p>
                <p>{{ $order->street_address }}, {{ $order->barangay }}, {{ $order->municipality }},
                    {{ $order->province }}</p>
                @if ($order->notes)
                    <p>Delivery notes: {{ $order->notes }}</p>
                @endif

                <h2 class="courier-section-title">Payment summary</h2>
                <p>Items: {{ number_format($order->subtotal, 2) }}</p>
                @if ($order->discount_amount)
                    <p>Discount: −{{ number_format($order->discount_amount, 2) }} ({{ $order->voucher_code }})</p>
                @endif
                <p>Shipping: {{ number_format($order->shipping_fee, 2) }}</p>
                <p><strong>Total: {{ number_format($order->total_amount, 2) }}</strong></p>

                <h2 class="courier-section-title">Tracking history</h2>
                @forelse($order->trackingEvents as $event)
                    <p><strong>{{ str_replace('_', ' ', $event->event_type) }}</strong> ·
                        {{ $event->created_at->format('M d, Y h:i A') }} · {{ $event->location ?? '—' }} @if ($event->actor)
                            · {{ match ($event->actor->role) { 'admin' => 'Platform support', 'sorting_center' => 'Logistics', 'courier' => 'Courier', 'seller' => 'Seller', default => 'EZiCart' } }}
                        @endif
                    </p>
                @empty
                    <p>Tracking events will appear as the order moves through fulfillment.</p>
                @endforelse

                <h2 class="courier-section-title">Delivery attempts and proof</h2>
                @forelse ($order->deliveryAttempts as $attempt)
                    <p>Attempt {{ $attempt->attempt_no }} · {{ ucfirst($attempt->outcome) }}
                        @if ($attempt->reason) · {{ str_replace('_', ' ', $attempt->reason) }} @endif
                        · {{ $attempt->attempted_at?->format('M d, Y h:i A') ?? 'Time unavailable' }}
                        @if ($attempt->proof_path)
                            · <a href="{{ route('delivery-attempts.proof', $attempt) }}">Download proof</a>
                        @endif
                    </p>
                @empty
                    <p>No delivery attempts are recorded yet.</p>
                @endforelse

                <div class="order-card-footer">
                    <a href="{{ route('buyer.orders.messages.show', $order) }}" class="dash-btn-sm dash-btn-outline">Message seller</a>
                    @if ($order->dispute)
                        <span class="status-pill">Dispute: {{ str_replace('_', ' ', $order->dispute->status) }}</span>
                    @elseif(in_array($order->status, ['DELIVERED', 'COMPLETED']))
                        <a href="{{ route('buyer.orders.dispute.create', $order->id) }}" class="dash-btn-sm">File a
                            dispute</a>
                    @endif

                    @if ($order->status === 'DELIVERED')
                        <form action="{{ route('buyer.orders.confirm', $order->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="dash-btn-sm dash-btn-success">Confirm receipt</button>
                        </form>
                    @endif
                </div>
            </section>
        </main>
    </div>
@endsection
