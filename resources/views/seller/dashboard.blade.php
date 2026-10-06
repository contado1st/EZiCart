@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
@endpush

@section('content')
    <div class="dash-wrapper">

        <!-- Sidebar Navigation -->
        <aside class="dash-sidebar">
            <div>
                <!-- Store Profile Header -->
                <div class="dash-profile-badge">
                    <span class="dash-role-tag">Seller Portal</span>
                    <h2 class="dash-profile-title">{{ auth()->user()->business_name ?? 'My Store' }}</h2>
                    <p class="dash-profile-subtitle">{{ auth()->user()->line_of_business ?? 'General Retail' }}</p>
                </div>

                <!-- Navigation Links -->
                <nav class="dash-nav">
                    <a href="{{ route('seller.dashboard') }}" class="dash-nav-item active">
                        📊 Dashboard Overview
                    </a>
                    <a href="{{ route('seller.products.index') }}" class="dash-nav-item">
                        📦 Inventory Management
                    </a>
                    <a href="{{ route('seller.orders.index') }}" class="dash-nav-item">
                        🛍️ Order Management
                    </a>
                </nav>
            </div>

            <!-- Logout Form -->
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
                    <h1 class="dash-title">Welcome back, {{ auth()->user()->first_name }} 👋</h1>
                    <p class="dash-subtitle">Here is what is happening with your store today.</p>
                </div>
                <div>
                    <a href="{{ route('seller.products.create') }}" class="dash-btn-primary">
                        + Add New Product
                    </a>
                </div>
            </div>

            <!-- Metric Stat Cards -->
            <div class="dash-stats-grid">
                <div class="dash-stat-card">
                    <div class="dash-stat-label">Net Sales</div>
                    <div class="dash-stat-value">₱{{ number_format($stats['total_sales'], 2) }}</div>
                    <div class="dash-stat-subtext success">Real-time fulfilled revenue</div>
                </div>

                <div class="dash-stat-card">
                    <div class="dash-stat-label">Pending Orders</div>
                    <div class="dash-stat-value primary">{{ $stats['pending_orders'] }}</div>
                    <div class="dash-stat-subtext">Requires packing & waybill</div>
                </div>

                <div class="dash-stat-card">
                    <div class="dash-stat-label">Low Stock Alerts</div>
                    <div class="dash-stat-value danger">{{ $stats['low_stock'] }}</div>
                    <div class="dash-stat-subtext danger">Items need restock (&lt; 10 units)</div>
                </div>

                <div class="dash-stat-card">
                    <div class="dash-stat-label">Store Rating</div>
                    <div class="dash-stat-value warning">⭐ {{ $stats['rating'] }}</div>
                    <div class="dash-stat-subtext">Based on customer feedback</div>
                </div>
            </div>

            <!-- Workflow Panels -->
            <div class="dash-workflow-grid">
                <div class="dash-panel">
                    <div class="u-extracted-7bdbd5a954">
                        <h3 class="dash-panel-title u-extracted-648149cea2">Recent Orders & Dispatch</h3>
                        <a href="{{ route('seller.orders.index') }}" class="u-extracted-b0fe66c15d">View
                            All →</a>
                    </div>

                    @forelse($recentOrders as $order)
                        <div class="order-card u-extracted-a3d1500119">
                            <div class="u-extracted-c2bcea0858">
                                <div>
                                    <span class="u-extracted-a0a031578b">{{ $order->order_number }}</span>
                                    <div class="u-extracted-cf0c302441">
                                        {{ $order->recipient_name }} &bull; {{ $order->items->count() }} item(s)</div>
                                </div>
                                <div class="u-extracted-13cbe03b9a">
                                    <span
                                        class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                                        {{ str_replace('_', ' ', $order->status) }}
                                    </span>
                                    <div class="u-extracted-102e7d3885">
                                        ₱{{ number_format($order->total_amount, 2) }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="dash-empty-box">
                            📦 No active orders yet. Orders from buyers will appear here for packing, waybill generation,
                            and rider pickup scheduling.
                        </div>
                    @endforelse
                </div>

                <div class="dash-panel">
                    <h3 class="dash-panel-title">Seller Actions</h3>
                    <div class="dash-action-list">
                        <a href="{{ route('seller.products.create') }}" class="dash-action-btn">
                            ➕ Add Product / Set Discounts
                        </a>
                        <a href="{{ route('seller.products.index') }}" class="dash-action-btn">
                            📋 View All Inventory & Stock
                        </a>
                        <a href="{{ route('seller.orders.index') }}" class="dash-action-btn">
                            🛍️ Process Orders & Print Waybills
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
@endsection
