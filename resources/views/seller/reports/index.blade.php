@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}">
@endpush

@section('content')
<div class="dash-wrapper">
    <!-- Sidebar -->
    <aside class="dash-sidebar">
        <div>
            <div class="dash-profile-badge">
                <span class="dash-role-tag">Seller Portal</span>
                <h2 class="dash-profile-title">{{ auth()->user()->business_name ?? 'My Store' }}</h2>
                <p class="dash-profile-subtitle">{{ auth()->user()->line_of_business ?? 'Merchant' }}</p>
            </div>

            <nav class="dash-nav">
                <a href="{{ route('seller.dashboard') }}" class="dash-nav-item">📊 Dashboard</a>
                <a href="{{ route('seller.products.index') }}" class="dash-nav-item">📦 Products</a>
                <a href="{{ route('seller.orders.index') }}" class="dash-nav-item">📑 Orders & Waybills</a>
                <a href="{{ route('seller.vouchers.index') }}" class="dash-nav-item">🎟️ Promotional Vouchers</a>
                <a href="{{ route('seller.reports.index') }}" class="dash-nav-item active">📈 Financial Reports</a>
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
                <h1 class="dash-title">Financial & Profit Report</h1>
                <p class="dash-subtitle">Track sales revenue, 10% platform commission, and calculated net profit.</p>
            </div>
        </div>

        <!-- Date Range Filter Bar -->
        <div class="report-filter-bar">
            <form action="{{ route('seller.reports.index') }}" method="GET" class="report-filter-form">
                <div class="report-filter-group">
                    <label class="report-filter-label">From:</label>
                    <input type="date" name="from_date" value="{{ $fromDate->format('Y-m-d') }}" class="report-date-input" required>
                </div>
                <div class="report-filter-group">
                    <label class="report-filter-label">To:</label>
                    <input type="date" name="to_date" value="{{ $toDate->format('Y-m-d') }}" class="report-date-input" required>
                </div>
                <button type="submit" class="dash-btn-sm dash-btn-primary">Filter Records</button>
            </form>

            <div class="report-actions-group">
                <button onclick="window.print()" class="report-print-btn">🖨️ Print Statement</button>
            </div>
        </div>

        <!-- Metric Summary Cards -->
        <div class="report-summary-banner">
            <div class="report-summary-card">
                <div class="report-summary-title">Gross Sales</div>
                <div class="report-summary-value">₱{{ number_format($stats['gross_sales'], 2) }}</div>
            </div>

            <div class="report-summary-card">
                <div class="report-summary-title">Platform Fee (10%)</div>
                <div class="report-summary-value report-summary-deduction">-₱{{ number_format($stats['platform_commission'], 2) }}</div>
            </div>

            <div class="report-summary-card">
                <div class="report-summary-title">Net Merchant Profit</div>
                <div class="report-summary-value report-summary-profit">₱{{ number_format($stats['net_profit'], 2) }}</div>
            </div>

            <div class="report-summary-card">
                <div class="report-summary-title">Fulfilled Orders</div>
                <div class="report-summary-value">{{ $stats['completed_count'] }} / {{ $stats['total_orders_placed'] }}</div>
            </div>
        </div>

        <!-- Completed Orders Table -->
        <div class="dash-panel report-table-section">
            <h2 class="report-table-title">Settled Transactions ({{ $completedOrders->count() }})</h2>

            <div class="dash-table-wrapper">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Order No.</th>
                            <th>Date Completed</th>
                            <th>Subtotal</th>
                            <th>Discounts</th>
                            <th>10% Commission</th>
                            <th>Net Payout</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($completedOrders as $order)
                            <tr>
                                <td><strong>{{ $order->order_number }}</strong></td>
                                <td>{{ $order->updated_at->format('M d, Y h:i A') }}</td>
                                <td>₱{{ number_format($order->subtotal, 2) }}</td>
                                <td>₱{{ number_format($order->discount_amount, 2) }}</td>
                                <td>-₱{{ number_format($order->commission_fee, 2) }}</td>
                                <td><strong class="report-summary-profit">₱{{ number_format($order->subtotal - $order->commission_fee, 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="dash-table-empty">No completed transactions recorded within this timeframe.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
@endsection