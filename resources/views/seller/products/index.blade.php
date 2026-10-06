@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
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

            @if (session('success'))
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
                            <th class="u-extracted-13cbe03b9a">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td>
                                    <div class="u-extracted-2f29807bdf">
                                        @if ($product->image_path)
                                            <img src="{{ asset('storage/' . $product->image_path) }}"
                                                alt="{{ $product->name }}" class="u-extracted-aac99b4b41">
                                        @else
                                            <div class="u-extracted-c70ac6556c">
                                                📦</div>
                                        @endif
                                        <div>
                                            <div class="u-extracted-8fa6b099d3">
                                                {{ $product->name }}</div>
                                            <div class="u-extracted-cf0c302441">
                                                SKU-{{ str_pad($product->id, 5, '0', STR_PAD_LEFT) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="dash-badge dash-badge-category">{{ $product->category }}</span></td>
                                <td class="u-extracted-6b1af052d3">₱{{ number_format($product->price, 2) }}</td>
                                <td>
                                    <span
                                        class="seller-stock {{ $product->stock < 10 ? 'seller-stock--low' : 'seller-stock--ok' }}">
                                        {{ $product->stock }} units
                                    </span>
                                </td>
                                <td>
                                    @if ($product->is_archived)
                                        <span class="dash-badge dash-badge-archived">Archived</span>
                                    @elseif($product->compliance_status !== 'approved')
                                        <span
                                            class="dash-badge dash-badge-archived">{{ str_replace('_', ' ', ucfirst($product->compliance_status)) }}</span>
                                        @if ($product->compliance_note)
                                            <div class="text-muted-small">{{ $product->compliance_note }}</div>
                                        @endif
                                    @else
                                        <span class="dash-badge dash-badge-active">Active</span>
                                    @endif
                                </td>
                                <td class="u-extracted-13cbe03b9a">
                                    <div class="u-extracted-af0aae5706">
                                        <a href="{{ route('seller.products.edit', $product->id) }}"
                                            class="dash-btn-sm dash-btn-outline">Edit</a>
                                        <form action="{{ route('seller.products.destroy', $product->id) }}" method="POST"
                                            onsubmit="return confirm('Remove this product from your inventory?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dash-btn-sm dash-btn-danger">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="u-extracted-a3521f1d48">
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
