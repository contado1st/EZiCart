@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endpush

@section('content')
<div class="dash-wrapper">
    <!-- Sidebar Navigation -->
    <aside class="dash-sidebar">
        <div>
            <div class="dash-profile-badge">
                <span class="dash-role-tag">Seller Portal</span>
                <h2 class="dash-profile-title">{{ auth()->user()->business_name ?? 'My Store' }}</h2>
            </div>
            <nav class="dash-nav">
                <a href="{{ route('seller.dashboard') }}" class="dash-nav-item">
                    📊 Dashboard Overview
                </a>
                <a href="{{ route('seller.products.index') }}" class="dash-nav-item active">
                    📦 Inventory Management
                </a>
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
                <h1 class="dash-title">Inventory Management</h1>
                <p class="dash-subtitle">Manage products, update stock levels, and monitor prices.</p>
            </div>
            <a href="{{ route('seller.products.create') }}" class="dash-btn-primary">+ Add New Product</a>
        </div>

        @if(session('success'))
            <div class="dash-alert-success">✅ {{ session('success') }}</div>
        @endif

        <div class="dash-table-wrapper">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    @if($product->image_path)
                                        <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}" style="width: 44px; height: 44px; object-fit: cover; border-radius: 0.375rem;">
                                    @else
                                        <div style="width: 44px; height: 44px; background-color: var(--dash-bg); border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">📦</div>
                                    @endif
                                    <div>
                                        <div style="font-weight: 700; color: var(--dash-text-main);">{{ $product->name }}</div>
                                        <div style="font-size: 0.75rem; color: var(--dash-text-muted);">SKU-{{ str_pad($product->id, 5, '0', STR_PAD_LEFT) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="dash-badge dash-badge-category">{{ $product->category }}</span></td>
                            <td>
                                @if($product->has_discount)
                                    <div>
                                        <span style="font-weight: 800; color: var(--ez-primary);">₱{{ number_format($product->discounted_price, 2) }}</span>
                                        <span style="text-decoration: line-through; font-size: 0.75rem; color: var(--dash-text-muted); margin-left: 0.25rem;">₱{{ number_format($product->price, 2) }}</span>
                                    </div>
                                    <span style="background: #fef2f2; color: #dc2626; padding: 1px 5px; border-radius: 3px; font-size: 0.68rem; font-weight: 700;">
                                        {{ $product->discount_type === 'percent' ? $product->discount_value . '% OFF' : '₱' . number_format($product->discount_value, 2) . ' OFF' }}
                                    </span>
                                @else
                                    <span style="font-weight: 800;">₱{{ number_format($product->price, 2) }}</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-weight: 700; color: {{ $product->stock < 10 ? 'var(--dash-danger)' : 'var(--dash-success)' }};">
                                    {{ $product->stock }} units
                                </span>
                            </td>
                            <td>
                                @if($product->status === 'approved')
                                    <span class="dash-badge dash-badge-active" style="background: #dcfce7; color: #15803d;">Approved</span>
                                    @if($product->is_archived)
                                        <span class="dash-badge dash-badge-archived" style="margin-top: 0.25rem; display: inline-block;">Archived</span>
                                    @endif
                                @elseif($product->status === 'rejected')
                                    <span class="dash-badge" style="background: #fee2e2; color: #b91c1c;">Rejected</span>
                                    @if($product->rejection_reason)
                                        <div style="font-size: 0.75rem; color: #b91c1c; margin-top: 0.35rem; background: #fff5f5; padding: 0.3rem 0.5rem; border-radius: 4px; border-left: 2px solid #ef4444; max-width: 220px; line-height: 1.3;">
                                            <strong>Reason:</strong> {{ $product->rejection_reason }}
                                        </div>
                                    @endif
                                @else
                                    <span class="dash-badge" style="background: #fef3c7; color: #b45309;">Pending</span>
                                    <div style="font-size: 0.7rem; color: #b45309; margin-top: 0.2rem;">Under Admin Review</div>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                                    <a href="{{ route('seller.products.edit', $product->id) }}" class="dash-btn-sm dash-btn-outline">Edit</a>
                                    <form action="{{ route('seller.products.destroy', $product->id) }}" method="POST" onsubmit="return confirm('Delete this product permanently?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dash-btn-sm dash-btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 3rem; text-align: center; color: var(--dash-text-muted);">
                                No products found. Start adding inventory to your store!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</div>
@endsection