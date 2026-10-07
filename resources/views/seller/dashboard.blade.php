@extends('layouts.seller')

@section('title', 'Seller Dashboard - EZiCart')

@section('content')
<div class="dash-header">
    <div>
        <h1 class="dash-title">Welcome back, {{ auth()->user()->first_name }} 👋</h1>
        <p class="dash-subtitle">Here is a real-time summary of your store performance and orders.</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="{{ route('seller.products.create') }}" class="dash-btn-primary" style="text-decoration: none;">
            + Add New Product
        </a>
    </div>
</div>

@if(session('success'))
    <div class="dash-alert-success">✅ {{ session('success') }}</div>
@endif

<!-- Metric Stat Cards -->
<div class="dash-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="dash-stat-card">
        <div class="dash-stat-label">Net Sales</div>
        <div class="dash-stat-value" style="color: var(--ez-primary);">₱{{ number_format($stats['total_sales'], 2) }}</div>
        <div class="dash-stat-subtext success">Real-time fulfilled revenue</div>
    </div>

    <div class="dash-stat-card">
        <div class="dash-stat-label">Estimated Profit</div>
        <div class="dash-stat-value success">₱{{ number_format($stats['estimated_profit'], 2) }}</div>
        <div class="dash-stat-subtext">After platform commission</div>
    </div>

    <div class="dash-stat-card">
        <div class="dash-stat-label">Pending Orders</div>
        <div class="dash-stat-value primary">{{ $stats['pending_orders'] }}</div>
        <div class="dash-stat-subtext">Requires packing & waybill</div>
    </div>

    <div class="dash-stat-card">
        <div class="dash-stat-label">Total Orders</div>
        <div class="dash-stat-value">{{ $stats['total_orders'] }}</div>
        <div class="dash-stat-subtext">All-time store orders</div>
    </div>

    <div class="dash-stat-card">
        <div class="dash-stat-label">Active Products</div>
        <div class="dash-stat-value">{{ $stats['total_products'] }}</div>
        <div class="dash-stat-subtext">{{ $stats['pending_products'] }} pending moderation</div>
    </div>

    <div class="dash-stat-card">
        <div class="dash-stat-label">Store Rating</div>
        <div class="dash-stat-value warning">★ {{ number_format($stats['rating'], 1) }}</div>
        <div class="dash-stat-subtext">{{ $stats['review_count'] ?? 0 }} customer ratings</div>
    </div>
</div>

<!-- Recent Orders Section -->
<div class="dash-panel">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <div>
            <h2 class="dash-table-title">Recent Store Orders</h2>
            <p class="dash-table-subtitle">Latest orders placed by buyers awaiting dispatch or completion.</p>
        </div>
        <a href="{{ route('seller.orders.index') }}" class="dash-btn-sm" style="background: white; border: 1px solid var(--slate-300); color: var(--slate-700); text-decoration: none;">
            View All Orders →
        </a>
    </div>

    @if($recentOrders->isEmpty())
        <div class="dash-empty-box" style="text-align: center; padding: 2.5rem 1rem; color: var(--slate-500);">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🛍️</div>
            <p>No customer orders placed yet. Products live in marketplace will appear here.</p>
        </div>
    @else
        <div class="dash-table-container">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Buyer</th>
                        <th>Items</th>
                        <th>Net Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentOrders as $order)
                        <tr>
                            <td class="font-mono"><strong>{{ $order->order_number }}</strong></td>
                            <td>{{ $order->buyer->first_name ?? 'Buyer' }} {{ $order->buyer->last_name ?? '' }}</td>
                            <td>
                                @foreach($order->items->take(2) as $item)
                                    <div>{{ $item->product_name }} &times; {{ $item->quantity }}</div>
                                @endforeach
                                @if($order->items->count() > 2)
                                    <span style="font-size: 0.75rem; color: var(--slate-400);">+{{ $order->items->count() - 2 }} more</span>
                                @endif
                            </td>
                            <td>
                                <strong>₱{{ number_format($order->subtotal - $order->commission_fee, 2) }}</strong>
                            </td>
                            <td>
                                <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                                    {{ str_replace('_', ' ', $order->status) }}
                                </span>
                            </td>
                            <td>{{ $order->created_at->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('seller.orders.show', $order->id) }}" class="dash-btn-sm" style="background: white; border: 1px solid var(--slate-300); color: var(--slate-700); text-decoration: none;">
                                    Manage →
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection