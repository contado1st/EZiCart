@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endpush

@section('content')
<div class="dash-wrapper">
    <!-- Sidebar Navigation -->
    <aside class="dash-sidebar">
        <div>
            <div class="dash-profile-badge">
                <span class="dash-role-tag">Seller Portal</span>
                <h2 class="dash-profile-title">{{ auth()->user()->business_name ?? 'My Store' }}</h2>
            </div>
            <nav class="dash-nav">
                <a href="{{ route('seller.dashboard') }}" class="dash-nav-item">
                    📊 Dashboard Overview
                </a>
                <a href="{{ route('seller.products.index') }}" class="dash-nav-item">
                    📦 Inventory Management
                </a>
                <a href="{{ route('seller.orders.index') }}" class="dash-nav-item active">
                    🛍️ Order Management
                </a>
            </nav>
        </div>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="dash-logout-btn">🚪 Logout</button>
        </form>
    </aside>

    <!-- Main Workspace -->
    <main class="dash-main">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Order Fulfillment & Dispatch</h1>
                <p class="dash-subtitle">Accept incoming buyer orders, pack packages, and generate waybills.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="dash-alert-success">✅ {{ session('success') }}</div>
        @endif

        <!-- Filter Tabs -->
        <div class="order-tabs-bar">
            <a href="{{ route('seller.orders.index') }}" class="order-tab-link {{ !request('status') ? 'active' : '' }}">
                All Orders ({{ $counts['all'] }})
            </a>
            <a href="{{ route('seller.orders.index', ['status' => 'PLACED']) }}" class="order-tab-link {{ request('status') === 'PLACED' ? 'active' : '' }}">
                New Placed ({{ $counts['placed'] }})
            </a>
            <a href="{{ route('seller.orders.index', ['status' => 'PREPARING']) }}" class="order-tab-link {{ request('status') === 'PREPARING' ? 'active' : '' }}">
                In Packing ({{ $counts['preparing'] }})
            </a>
            <a href="{{ route('seller.orders.index', ['status' => 'READY_FOR_PICKUP']) }}" class="order-tab-link {{ request('status') === 'READY_FOR_PICKUP' ? 'active' : '' }}">
                Ready for Pickup ({{ $counts['ready'] }})
            </a>
            <a href="{{ route('seller.orders.index', ['status' => 'COMPLETED']) }}" class="order-tab-link {{ request('status') === 'COMPLETED' ? 'active' : '' }}">
                Completed ({{ $counts['completed'] }})
            </a>
        </div>

        <!-- Orders Feed -->
        @forelse($orders as $order)
            <div class="order-card">
                <div class="order-card-header">
                    <div>
                        <span class="order-number">{{ $order->order_number }}</span>
                        <div class="order-date">
                            Ordered on {{ $order->created_at->format('M d, Y h:i A') }}
                        </div>
                    </div>
                    <div>
                        <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                            {{ str_replace('_', ' ', $order->status) }}
                        </span>
                    </div>
                </div>

                <!-- Recipient Delivery Address -->
                <div class="order-recipient-box">
                    Deliver to: <span class="order-recipient-name">{{ $order->recipient_name }}</span> ({{ $order->recipient_contact }}) &bull;
                    {{ $order->street_address }}, {{ $order->barangay }}, {{ $order->municipality }}, {{ $order->province }}
                </div>

                <!-- Item Line Listings -->
                <div class="order-item-listing">
                    @foreach($order->items as $item)
                        <div class="order-item-row">
                            <div>
                                <strong>{{ $item->product_name }}</strong>
                                <span style="color: var(--dash-text-muted); font-size: 0.75rem; margin-left: 0.5rem;">x{{ $item->quantity }}</span>
                            </div>
                            <div>₱{{ number_format($item->item_total, 2) }}</div>
                        </div>
                    @endforeach
                </div>

                <!-- Footer Summary & Actions -->
                <div class="order-card-footer">
                    <div class="order-total-block">
                        Payment: <strong>{{ $order->payment_method }}</strong> &bull;
                        Net Earnings: <strong style="color: var(--dash-success);">₱{{ number_format($order->subtotal - $order->commission_fee, 2) }}</strong>
                        <span style="font-size: 0.75rem; color: var(--dash-text-muted);">(10% platform fee deducted)</span>
                    </div>

                    <div class="order-seller-action-row">
                        <a href="{{ route('seller.orders.waybill', $order->id) }}" target="_blank" class="dash-btn-sm dash-btn-outline">
                            🖨️ Waybill
                        </a>

                        @if($order->status === 'PLACED')
                            <form action="{{ route('seller.orders.updateStatus', $order->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="PREPARING">
                                <button type="submit" class="dash-btn-sm dash-btn-primary">
                                    Confirm & Pack Order
                                </button>
                            </form>
                        @elseif($order->status === 'PREPARING' || $order->status === 'CONFIRMED')
                            <form action="{{ route('seller.orders.updateStatus', $order->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="READY_FOR_PICKUP">
                                <button type="submit" class="dash-btn-sm dash-btn-primary">
                                    Mark Ready for Pickup
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="dash-empty-box">
                📦 No customer orders found under this status.
            </div>
        @endforelse

        <div style="margin-top: 1.5rem;">
            {{ $orders->links() }}
        </div>
    </main>
</div>
@endsection