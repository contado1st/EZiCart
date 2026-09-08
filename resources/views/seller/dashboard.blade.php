@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
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
                <a href="#" class="dash-nav-item">
                    🛍️ Order Management
                </a>
                <a href="#" class="dash-nav-item">
                    📈 Generate Reports
                </a>
                <a href="#" class="dash-nav-item">
                    💬 Chat & Messaging
                </a>
                <a href="#" class="dash-nav-item">
                    ⚙️ Account Settings
                </a>
            </nav>
        </div>

        <!-- Logout Form -->
        <form action="{{ route('logout') }}" method="POST" style="margin-top: 2rem;">
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
                <div class="dash-stat-label">Total Sales</div>
                <div class="dash-stat-value">₱{{ number_format($stats['total_sales'], 2) }}</div>
                <div class="dash-stat-subtext success">Updated real-time</div>
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
                <h3 class="dash-panel-title">Recent Orders & Dispatch</h3>
                <div class="dash-empty-box">
                    📦 No active orders yet. Orders from buyers will appear here for packing, waybill generation, and rider pickup scheduling.
                </div>
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
                    <button type="button" class="dash-action-btn">
                        🏷️ Generate Vouchers
                    </button>
                    <button type="button" class="dash-action-btn">
                        📅 Financial Report (Date Picker)
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>
@endsection