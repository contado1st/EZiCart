@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
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
                <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">{{ str_replace('_', ' ', $order->status) }}</span>
            </div>

            <h2 class="courier-section-title">Recipient</h2>
            <p><strong>{{ $order->recipient_name }}</strong> · {{ $order->recipient_contact }}</p>
            <p>{{ $order->street_address }}, {{ $order->barangay }}, {{ $order->municipality }}, {{ $order->province }}</p>

            <h2 class="courier-section-title">Items</h2>
            @foreach($order->items as $item)
                <div class="order-item-row">
                    <div>
                        <strong>{{ $item->product_name }}</strong>
                        @if($item->variation_info)
                            <span>({{ $item->variation_info }})</span>
                        @endif
                        <span>&times; {{ $item->quantity }}</span>
                    </div>
                    <div>{{ number_format($item->item_total, 2) }}</div>
                </div>
            @endforeach

            <h2 class="courier-section-title">Order summary</h2>
            <p>Subtotal: {{ number_format($order->subtotal, 2) }}</p>
            @if($order->discount_amount)
                <p>Discount: −{{ number_format($order->discount_amount, 2) }} ({{ $order->voucher_code }})</p>
            @endif
            <p>Platform commission: {{ number_format($order->commission_fee, 2) }}</p>
            <p>Shipping: {{ number_format($order->shipping_fee, 2) }}</p>
            <p><strong>Total: {{ number_format($order->total_amount, 2) }}</strong></p>
            @if($order->notes)
                <p>Order notes: {{ $order->notes }}</p>
            @endif

            <div class="order-card-footer">
                <a href="{{ route('seller.orders.waybill', $order->id) }}" class="dash-btn-sm dash-btn-outline">View waybill</a>
                @if($order->status === 'PLACED')
                    <form action="{{ route('seller.orders.updateStatus', $order->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="PREPARING">
                        <button type="submit" class="dash-btn-sm dash-btn-primary">Confirm and pack</button>
                    </form>
                @elseif(in_array($order->status, ['PREPARING', 'CONFIRMED']))
                    <form action="{{ route('seller.orders.updateStatus', $order->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="READY_FOR_PICKUP">
                        <button type="submit" class="dash-btn-sm dash-btn-primary">Mark ready for pickup</button>
                    </form>
                @endif
            </div>
        </section>
    </main>
</div>
@endsection