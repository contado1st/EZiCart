@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
    @vite('resources/css/shared/reports.css')
@endpush

@section('content')
    <div class="dash-wrapper">
        <!-- Sidebar -->
        <aside class="dash-sidebar">
            <div>
                <div class="dash-profile-badge">
                    <span class="dash-role-tag dash-role-admin">E-Commerce Admin</span>
                    <h2 class="dash-profile-title">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h2>
                </div>
                <nav class="dash-nav">
                    <a href="{{ route('admin.dashboard') }}" class="dash-nav-item"><x-shop-icon name="diagram" /> Platform Overview</a>
                    <a href="{{ route('admin.registrations.index') }}" class="dash-nav-item"><x-shop-icon name="shield" /> User Approvals</a>
                    <a href="{{ route('admin.reports.index') }}" class="dash-nav-item active"><x-shop-icon name="invoice" /> Financial & Commission</a>
                </nav>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="dash-logout-btn"><x-shop-icon name="arrowback" /> Logout</button>
            </form>
        </aside>

        <!-- Main Workspace -->
        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Platform Commission & Sales Summary</h1>
                    <p class="dash-subtitle">Review completed order values, verified cash receipts, and recorded external settlements.</p>
                </div>
            </div>

            <!-- Date Range Filter Bar -->
            <div class="report-filter-bar">
                <form action="{{ route('admin.reports.index') }}" method="GET" class="report-filter-form">
                    <div class="report-filter-group">
                        <label class="report-filter-label">From:</label>
                        <input type="date" name="from_date" value="{{ $fromDate->format('Y-m-d') }}"
                            class="report-date-input" required>
                    </div>
                    <div class="report-filter-group">
                        <label class="report-filter-label">To:</label>
                        <input type="date" name="to_date" value="{{ $toDate->format('Y-m-d') }}"
                            class="report-date-input" required>
                    </div>
                    <button type="submit" class="dash-btn-sm dash-btn-primary">Generate Report</button>
                </form>

                <div class="report-actions-group">
                    <button onclick="window.print()" class="report-print-btn"><x-shop-icon name="print" /> Print Statement</button>
                </div>
            </div>

            <!-- Metric Summary Cards -->
            <div class="report-summary-banner">
                <div class="report-summary-card">
                    <div class="report-summary-title">Completed order value</div>
                    <div class="report-summary-value">₱{{ number_format($stats['total_gmv'], 2) }}</div>
                </div>

                <div class="report-summary-card">
                    <div class="report-summary-title">Product value before discounts</div>
                    <div class="report-summary-value">₱{{ number_format($stats['product_sales'], 2) }}</div>
                </div>

                <div class="report-summary-card">
                    <div class="report-summary-title">Verified gross commission</div>
                    <div class="report-summary-value report-summary-profit">
                        ₱{{ number_format($stats['total_commission'], 2) }}</div>
                </div>

                <div class="report-summary-card">
                    <div class="report-summary-title">Settlement Rate</div>
                    <div class="report-summary-value">{{ $stats['completed_count'] }} / {{ $stats['total_orders'] }}</div>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="dash-panel report-table-section">
                <p>Verified remittances: ₱{{ number_format($stats['verified_remittances'], 2) }} · Executed refunds: ₱{{ number_format($stats['executed_refunds'], 2) }} · Settled payouts: ₱{{ number_format($stats['settled_payouts'], 2) }} · Funded platform promotions: ₱{{ number_format($stats['funded_promotions'], 2) }}.</p>
                <p>Gross commission reflects currently completed, non-refunded orders with remittances verified during the selected dates. Promotion funding is an additional platform cost.</p>
                <h2 class="report-table-title">Booked order commissions ({{ $completedOrders->total() }})</h2>

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
                                    <td><strong
                                            class="report-summary-profit">₱{{ number_format($order->commission_fee, 2) }}</strong>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="dash-table-empty">No transactions found for the specified
                                        period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $completedOrders->links() }}
                </div>
            </div>
        </main>
    </div>
@endsection
