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
                <span class="dash-role-tag dash-role-admin">Super Admin</span>
                <h2 class="dash-profile-title">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h2>
            </div>
            <nav class="dash-nav">
                <a href="{{ route('admin.dashboard') }}" class="dash-nav-item">📊 Platform Overview</a>
                <a href="{{ route('admin.registrations.index') }}" class="dash-nav-item">🛡️ User Approvals</a>
                <a href="{{ route('admin.reports.index') }}" class="dash-nav-item active">📑 Financial & Commission</a>
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
                <h1 class="dash-title">Platform Commission & Sales Summary</h1>
                <p class="dash-subtitle">Analyze platform-wide transaction volume and verify 10% commission fee receipts.</p>
            </div>
        </div>

        <!-- Date Range Filter Bar -->
        <div class="report-filter-bar">
            <form action="{{ route('admin.reports.index') }}" method="GET" class="report-filter-form">
                <div class="report-filter-group">
                    <label class="report-filter-label">From:</label>
                    <input type="date" name="from_date" value="{{ $fromDate->format('Y-m-d') }}" class="report-date-input" required>
                </div>
                <div class="report-filter-group">
                    <label class="report-filter-label">To:</label>
                    <input type="date" name="to_date" value="{{ $toDate->format('Y-m-d') }}" class="report-date-input" required>
                </div>
                <button type="submit" class="dash-btn-sm dash-btn-primary">Generate Report</button>
            </form>

            <div class="report-actions-group">
                <button onclick="window.print()" class="report-print-btn">🖨️ Print Statement</button>
            </div>
        </div>

        <!-- Metric Summary Cards -->
        <div class="report-summary-banner">
            <div class="report-summary-card">
                <div class="report-summary-title">Total GMV Settled</div>
                <div class="report-summary-value">₱{{ number_format($stats['total_gmv'], 2) }}</div>
            </div>

            <div class="report-summary-card">
                <div class="report-summary-title">Net Product Volume</div>
                <div class="report-summary-value">₱{{ number_format($stats['product_sales'], 2) }}</div>
            </div>

            <div class="report-summary-card">
                <div class="report-summary-title">Platform Revenue (10%)</div>
                <div class="report-summary-value report-summary-profit">₱{{ number_format($stats['total_commission'], 2) }}</div>
            </div>

            <div class="report-summary-card">
                <div class="report-summary-title">Settlement Rate</div>
                <div class="report-summary-value">{{ $stats['completed_count'] }} / {{ $stats['total_orders'] }}</div>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="dash-panel report-table-section">
            <h2 class="report-table-title">Commission Ledger ({{ $completedOrders->count() }})</h2>

            <div class="dash-table-wrapper">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Order No.</th>
                            <th>Merchant</th>
                            <th>Buyer</th>
                            <th>Gross Total</th>
                            <th>Discounts</th>
                            <th>10% Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($completedOrders as $order)
                            <tr>
                                <td><strong>{{ $order->order_number }}</strong></td>
                                <td>{{ $order->seller->business_name ?? 'Merchant' }}</td>
                                <td>{{ $order->buyer->first_name }} {{ $order->buyer->last_name }}</td>
                                <td>₱{{ number_format($order->total_amount, 2) }}</td>
                                <td>₱{{ number_format($order->discount_amount, 2) }}</td>
                                <td><strong class="report-summary-profit">₱{{ number_format($order->commission_fee, 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="dash-table-empty">No transactions found for the specified period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
@endsection