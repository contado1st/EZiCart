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
                <span class="dash-role-tag dash-role-admin">Super Admin</span>
                <h2 class="dash-profile-title">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h2>
                <p class="dash-profile-subtitle">Platform Governance</p>
            </div>

            <nav class="dash-nav">
                <a href="{{ route('admin.dashboard') }}" class="dash-nav-item active">
                    📊 Platform Overview
                </a>
                <a href="{{ route('admin.registrations.index') }}" class="dash-nav-item">
                    🛡️ User Approvals
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

    <!-- Main Content Area -->
    <main class="dash-main">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Platform Operations & Financials</h1>
                <p class="dash-subtitle">System-wide monitoring of marketplace transactions, commission revenue, and verifications.</p>
            </div>
            <div>
                <a href="{{ route('admin.registrations.index') }}" class="dash-btn-primary">
                    Verify Users ({{ $stats['pending_users'] }})
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="dash-alert-success">✅ {{ session('success') }}</div>
        @endif

        <!-- Financial & Logistics KPI Cards -->
        <div class="dash-stats-grid">
            <div class="dash-stat-card">
                <div class="dash-stat-label">Platform Revenue</div>
                <div class="dash-stat-value success">₱{{ number_format($stats['total_commission'], 2) }}</div>
                <div class="dash-stat-subtext success">10% commission on fulfilled orders</div>
            </div>

            <div class="dash-stat-card">
                <div class="dash-stat-label">Gross Merchandise Value</div>
                <div class="dash-stat-value primary">₱{{ number_format($stats['gmv'], 2) }}</div>
                <div class="dash-stat-subtext">Total transaction volume</div>
            </div>

            <div class="dash-stat-card">
                <div class="dash-stat-label">Pending Verifications</div>
                <div class="dash-stat-value warning">{{ $stats['pending_users'] }}</div>
                <div class="dash-stat-subtext">Sellers and couriers awaiting review</div>
            </div>

            <div class="dash-stat-card">
                <div class="dash-stat-label">Parcels in Transit</div>
                <div class="dash-stat-value">{{ $stats['active_parcels'] }}</div>
                <div class="dash-stat-subtext">Active courier deliveries</div>
            </div>
        </div>

        <!-- Workflow Grid -->
        <div class="dash-workflow-grid">
            <!-- Left: System Transactions -->
            <div class="dash-panel">
                <h3 class="dash-panel-title">Recent Platform Orders</h3>
                
                <div class="dash-table-wrapper">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Store</th>
                                <th>Buyer</th>
                                <th>Total</th>
                                <th>Commission</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                <tr>
                                    <td class="dash-text-bold">{{ $order->order_number }}</td>
                                    <td>{{ $order->seller->business_name ?? 'Store' }}</td>
                                    <td>{{ $order->recipient_name }}</td>
                                    <td class="dash-text-bold">₱{{ number_format($order->total_amount, 2) }}</td>
                                    <td class="dash-text-success-bold">₱{{ number_format($order->commission_fee, 2) }}</td>
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
                <div class="dash-panel-header-row">
                    <h3 class="dash-panel-title dash-panel-title-compact">Pending Approvals</h3>
                    <a href="{{ route('admin.registrations.index') }}" class="dash-action-link">View All →</a>
                </div>

                @forelse($pendingUsers as $pUser)
                    <div class="dash-approval-card">
                        <div class="dash-approval-row">
                            <div>
                                <div class="dash-approval-name">{{ $pUser->first_name }} {{ $pUser->last_name }}</div>
                                <div class="dash-approval-email">{{ $pUser->email }}</div>
                                <span class="dash-badge dash-badge-category dash-approval-badge">{{ ucfirst($pUser->role) }}</span>
                            </div>
                            <div class="dash-approval-actions">
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
                    <div class="dash-empty-box">
                        ✅ All user registration requests have been addressed.
                    </div>
                @endforelse
            </div>
        </div>
    </main>
</div>
@endsection