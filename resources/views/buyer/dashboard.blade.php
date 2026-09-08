@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endpush

@section('content')
<div class="dash-wrapper">
    <!-- Sidebar -->
    <aside class="dash-sidebar">
        <div>
            <div class="dash-profile-badge">
                <span class="dash-role-tag">Buyer Portal</span>
                <h2 class="dash-profile-title">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h2>
                <p class="dash-profile-subtitle">{{ auth()->user()->email }}</p>
            </div>

            <nav class="dash-nav">
                <a href="{{ route('buyer.dashboard') }}" class="dash-nav-item active">
                    🛍️ My Orders
                </a>
                <a href="{{ route('home') }}" class="dash-nav-item">
                    🛒 Browse Marketplace
                </a>
            </nav>
        </div>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="dash-logout-btn">
                🚪 Logout
            </button>
        </form>
    </aside>

    <!-- Main Workspace -->
    <main class="dash-main">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Order History & Tracking</h1>
                <p class="dash-subtitle">Monitor parcel movement from preparation to doorstep delivery.</p>
            </div>
            <a href="{{ route('home') }}" class="dash-btn-primary">
                + Shop More Items
            </a>
        </div>

        @if(session('success'))
            <div class="dash-alert-success">✅ {{ session('success') }}</div>
        @endif

        <!-- Order Navigation Tabs -->
        <div class="order-tabs-bar">
            <a href="{{ route('buyer.dashboard') }}" class="order-tab-link {{ !request('status') ? 'active' : '' }}">
                All Orders ({{ $counts['all'] }})
            </a>
            <a href="{{ route('buyer.dashboard', ['status' => 'PLACED']) }}" class="order-tab-link {{ request('status') === 'PLACED' ? 'active' : '' }}">
                To Ship ({{ $counts['to_ship'] }})
            </a>
            <a href="{{ route('buyer.dashboard', ['status' => 'OUT_FOR_DELIVERY']) }}" class="order-tab-link {{ request('status') === 'OUT_FOR_DELIVERY' ? 'active' : '' }}">
                To Receive ({{ $counts['to_receive'] }})
            </a>
            <a href="{{ route('buyer.dashboard', ['status' => 'COMPLETED']) }}" class="order-tab-link {{ request('status') === 'COMPLETED' ? 'active' : '' }}">
                Completed ({{ $counts['completed'] }})
            </a>
        </div>

        <!-- Orders Listing -->
        @forelse($orders as $order)
            <div class="order-card">
                <div class="order-card-header">
                    <div>
                        <div class="order-number">{{ $order->order_number }}</div>
                        <div class="order-date">
                            Store: <strong>{{ $order->seller->business_name ?? 'EZiCart Merchant' }}</strong> &bull; {{ $order->created_at->format('M d, Y h:i A') }}
                        </div>
                    </div>
                    <div>
                        <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                            {{ str_replace('_', ' ', $order->status) }}
                        </span>
                    </div>
                </div>

                <div class="order-item-listing">
                    @foreach($order->items as $item)
                        <div class="order-item-row">
                            <div>
                                <strong>{{ $item->product_name }}</strong>
                                <span style="color: var(--dash-text-muted); font-size: 0.75rem; margin-left: 0.5rem;">x{{ $item->quantity }}</span>
                            </div>
                            <div style="font-weight: 700;">
                                ₱{{ number_format($item->item_total, 2) }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="order-card-footer">
                    <div class="order-total-block">
                        Payment: <strong>{{ $order->payment_method }}</strong> &bull; Total: <span class="order-total-price">₱{{ number_format($order->total_amount, 2) }}</span>
                    </div>

                    <div>
                        @if($order->status === 'DELIVERED')
                            <form action="{{ route('buyer.orders.confirm', $order->id) }}" method="POST" style="display: inline;">
                                @csrf
                                <button type="submit" class="dash-btn-sm dash-btn-primary" onclick="return confirm('Confirm receipt of your order?')">
                                    Confirm Order Received
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="dash-empty-box">
                📦 No orders match this status criteria.
            </div>
        @endforelse

        <div style="margin-top: 1.5rem;">
            {{ $orders->links() }}
        </div>
    </main>
</div>
@endsection