@extends('layouts.app')

@section('workspace-primary-route', 'seller.products.create')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
@endpush

@section('content')
    <div class="dash-wrapper">

        <!-- Sidebar Navigation -->
<!-- Main Workspace -->
        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Orders and store performance</h1>
                    <p class="dash-subtitle">Review pending orders, prepare parcels and keep your inventory up to date.</p>
                </div>
                <div class="workspace-page-actions">
                    <a href="{{ route('seller.products.create') }}" class="dash-btn-primary">
                        <x-shop-icon name="checklist" /> Add product
                    </a>
                </div>
            </div>

            <!-- Metric Stat Cards -->
            <div class="dash-stats-grid">
                <div class="dash-stat-card">
                    <span class="workspace-stat-icon" aria-hidden="true"><x-shop-icon name="money" /></span>
                    <div class="dash-stat-label">Accrued seller earnings</div>
                    <div class="dash-stat-value">₱{{ number_format($stats['total_sales'], 2) }}</div>
                    <div class="dash-stat-subtext success">After discounts and commission; not settled cash</div>
                </div>

                <div class="dash-stat-card">
                    <span class="workspace-stat-icon" aria-hidden="true"><x-shop-icon name="cart1" /></span>
                    <div class="dash-stat-label">Pending Orders</div>
                    <div class="dash-stat-value primary">{{ $stats['pending_orders'] }}</div>
                    <div class="dash-stat-subtext">Requires packing & waybill</div>
                </div>

                <div class="dash-stat-card">
                    <span class="workspace-stat-icon" aria-hidden="true"><x-shop-icon name="warning" /></span>
                    <div class="dash-stat-label">Low Stock Alerts</div>
                    <div class="dash-stat-value danger">{{ $stats['low_stock'] }}</div>
                    <div class="dash-stat-subtext danger">Items need restock (&lt; 10 units)</div>
                </div>

                <div class="dash-stat-card">
                    <span class="workspace-stat-icon" aria-hidden="true"><x-shop-icon name="star" /></span>
                    <div class="dash-stat-label">Store Rating</div>
                    <div class="dash-stat-value warning">{{ $stats['rating'] }}</div>
                    <div class="dash-stat-subtext">{{ $stats['review_count'] }} customer reviews</div>
                </div>
            </div>

            <!-- Workflow Panels -->
            <x-workspace-tabs :tabs="[
                ['id' => 'seller-orders', 'label' => 'Orders and dispatch', 'count' => $recentOrders->total()],
                ['id' => 'seller-tools', 'label' => 'Store overview and tools'],
            ]" />
            <div class="dash-workflow-grid">
                <div class="dash-panel" id="seller-orders">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                        <h3 class="dash-panel-title" style="margin-bottom: 0;">Recent Orders & Dispatch</h3>
                        <a href="{{ route('seller.orders.index') }}" class="dash-btn-sm dash-btn-outline">View All &rarr;</a>
                    </div>

                    @forelse($recentOrders as $order)
                        <div class="order-card" style="margin-bottom: 1rem; border-radius: var(--dash-radius-inner); padding: 1.1rem 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                                <div>
                                    <a href="{{ route('seller.orders.show', $order) }}" class="order-number">{{ $order->order_number }}</a>
                                    <div class="order-date" style="font-size: 0.75rem; color: var(--dash-text-muted); margin-top: 2px;">
                                        {{ $order->recipient_name }} &bull; {{ $order->items->count() }} item(s)</div>
                                </div>
                                <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                    <span
                                        class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                                        {{ str_replace('_', ' ', $order->operational_status) }}
                                    </span>
                                    <div style="font-weight: 700; font-variant-numeric: tabular-nums; font-size: 0.9375rem; color: var(--dash-text-main);">
                                        ₱{{ number_format($order->total_amount, 2) }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="dash-empty-box">
                            <x-shop-icon name="package1" /> No active orders yet. Orders from buyers will appear here for packing, waybill generation,
                            and rider pickup scheduling.
                        </div>
                    @endforelse
                    {{ $recentOrders->links() }}
                </div>

                <div class="dash-panel" id="seller-tools">
                    <x-workspace-distribution :values="$orderStatuses" label="Orders by status" description="All your orders at a glance." />
                    <h3 class="dash-panel-title">Seller Actions</h3>
                    <div class="dash-action-list">
                        <a href="{{ route('seller.products.create') }}" class="dash-action-btn">
                            <x-shop-icon name="checklist" /> Add Product / Set Discounts
                        </a>
                        <a href="{{ route('seller.products.index') }}" class="dash-action-btn">
                            <x-shop-icon name="checklist" /> View All Inventory & Stock
                        </a>
                        <a href="{{ route('seller.orders.index') }}" class="dash-action-btn">
                            <x-shop-icon name="cart1" /> Process Orders & Print Waybills
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
@endsection
