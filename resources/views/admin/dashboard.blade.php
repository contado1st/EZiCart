@extends('layouts.admin')

@section('title', 'Platform Operations & Financials - EZiCart Admin')

@section('content')
<div class="dash-header">
    <div>
        <h1 class="dash-title">Platform Operations & Financials</h1>
        <p class="dash-subtitle">System-wide monitoring of marketplace transactions, commission revenue, and verifications.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <a href="{{ route('admin.products.index') }}" class="dash-btn-primary" style="background: var(--slate-800); border-color: var(--slate-800); text-decoration: none;">
            Review Products ({{ $stats['pending_products'] ?? 0 }})
        </a>
        <a href="{{ route('admin.registrations.index') }}" class="dash-btn-primary" style="text-decoration: none;">
            Verify Users ({{ $stats['pending_users'] }})
        </a>
    </div>
</div>

@if(session('success'))
    <div class="dash-alert-success">✅ {{ session('success') }}</div>
@endif

<!-- Real Platform KPI Cards (Requirement 28) -->
<div class="dash-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="dash-stat-card">
        <div class="dash-stat-label">Platform 10% Commission</div>
        <div class="dash-stat-value success">₱{{ number_format($stats['total_commission'], 2) }}</div>
        <div class="dash-stat-subtext success">Calculated on completed orders</div>
    </div>

    <div class="dash-stat-card">
        <div class="dash-stat-label">Platform Gross Sales</div>
        <div class="dash-stat-value primary">₱{{ number_format($stats['platform_sales'], 2) }}</div>
        <div class="dash-stat-subtext">Total GMV merchandise volume</div>
    </div>

    <div class="dash-stat-card">
        <div class="dash-stat-label">Total Buyers</div>
        <div class="dash-stat-value">{{ $stats['total_buyers'] }}</div>
        <div class="dash-stat-subtext">Registered consumer accounts</div>
    </div>

    <div class="dash-stat-card">
        <div class="dash-stat-label">Total Sellers</div>
        <div class="dash-stat-value">{{ $stats['total_sellers'] }}</div>
        <div class="dash-stat-subtext">Verified merchant businesses</div>
    </div>

    <div class="dash-stat-card">
        <div class="dash-stat-label">Total Products</div>
        <div class="dash-stat-value">{{ $stats['total_products'] }}</div>
        <div class="dash-stat-subtext">Catalog listings in database</div>
    </div>

    <div class="dash-stat-card">
        <div class="dash-stat-label">Total Orders</div>
        <div class="dash-stat-value">{{ $stats['total_orders'] }}</div>
        <div class="dash-stat-subtext">All-time order transactions</div>
    </div>

    <div class="dash-stat-card">
        <div class="dash-stat-label">Pending Registrations</div>
        <div class="dash-stat-value warning">{{ $stats['pending_users'] }}</div>
        <div class="dash-stat-subtext">Awaiting admin review</div>
    </div>

    <div class="dash-stat-card">
        <div class="dash-stat-label">Pending Products</div>
        <div class="dash-stat-value" style="color: var(--ez-primary);">{{ $stats['pending_products'] ?? 0 }}</div>
        <div class="dash-stat-subtext">Awaiting catalog approval</div>
    </div>

    <div class="dash-stat-card">
        <div class="dash-stat-label">Pending Complaints</div>
        <div class="dash-stat-value danger">{{ $stats['pending_disputes'] ?? 0 }}</div>
        <div class="dash-stat-subtext">Open customer dispute cases</div>
    </div>
</div>

<!-- Workflow Grid -->
<div class="dash-workflow-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <!-- Left: System Transactions -->
    <div class="dash-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 class="dash-panel-title" style="margin: 0;">Recent Platform Orders</h3>
            <a href="{{ route('admin.reports.index') }}" class="dash-action-link" style="font-size: 0.85rem; color: var(--ez-primary); font-weight: 700; text-decoration: none;">View Reports →</a>
        </div>
        
        <div class="dash-table-wrapper">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Store</th>
                        <th>Buyer</th>
                        <th>Total</th>
                        <th>10% Fee</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        <tr>
                            <td class="dash-text-bold font-mono">{{ $order->order_number }}</td>
                            <td>{{ $order->seller->business_name ?? 'Store' }}</td>
                            <td>{{ $order->recipient_name }}</td>
                            <td class="dash-text-bold">₱{{ number_format($order->total_amount, 2) }}</td>
                            <td style="color: #059669; font-weight: 700;">₱{{ number_format($order->commission_fee, 2) }}</td>
                            <td>
                                <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                                    {{ str_replace('_', ' ', $order->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="dash-table-empty">
                                No transactions recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right: Quick Verification Queue -->
    <div class="dash-panel">
        <div class="dash-panel-header-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 class="dash-panel-title dash-panel-title-compact" style="margin: 0;">Pending Approvals</h3>
            <a href="{{ route('admin.registrations.index') }}" class="dash-action-link" style="font-size: 0.85rem; color: var(--ez-primary); font-weight: 700; text-decoration: none;">View All →</a>
        </div>

        @forelse($pendingUsers as $pUser)
            <div class="dash-approval-card" style="border: 1px solid var(--slate-200); border-radius: 8px; padding: 0.85rem; margin-bottom: 0.75rem; background: var(--slate-50);">
                <div class="dash-approval-row" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div class="dash-approval-name" style="font-weight: 700; font-size: 0.9rem;">{{ $pUser->first_name }} {{ $pUser->last_name }}</div>
                        <div class="dash-approval-email" style="font-size: 0.8rem; color: var(--slate-500);">{{ $pUser->email }}</div>
                        <span class="dash-badge dash-badge-category dash-approval-badge" style="display: inline-block; margin-top: 0.25rem;">{{ ucfirst($pUser->role) }}</span>
                    </div>
                    <div class="dash-approval-actions" style="display: flex; gap: 0.35rem;">
                        <form action="{{ route('admin.registrations.approve', $pUser->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="dash-btn-sm dash-btn-success">Approve</button>
                        </form>
                        <form action="{{ route('admin.registrations.reject', $pUser->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="dash-btn-sm dash-btn-danger">Reject</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="dash-empty-box" style="padding: 1.5rem; text-align: center; color: var(--slate-500); font-size: 0.85rem;">
                ✅ All user registration requests have been addressed.
            </div>
        @endforelse
    </div>
</div>
@endsection