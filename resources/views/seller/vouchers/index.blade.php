@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/seller-vouchers.css') }}">
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
                <a href="{{ route('seller.vouchers.index') }}" class="dash-nav-item active">🎟️ Promotional Vouchers</a>
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
                <h1 class="dash-title">Promotional Vouchers & Discounts</h1>
                <p class="dash-subtitle">Create discount vouchers to promote items and drive customer orders.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="dash-alert-success">✅ {{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="dash-alert-success" style="border-left-color: var(--dash-danger); background-color: var(--dash-danger-bg); color: var(--dash-danger);">
                ⚠️ {{ session('error') }}
            </div>
        @endif

        <div class="vouchers-grid">
            <!-- 1. Create Voucher Form -->
            <div class="voucher-form-card">
                <h2 class="voucher-form-title">Create New Voucher</h2>

                <form action="{{ route('seller.vouchers.store') }}" method="POST">
                    @csrf

                    <div class="voucher-form-group">
                        <label class="voucher-form-label">Voucher Code *</label>
                        <input type="text" name="code" value="{{ old('code') }}" placeholder="e.g. SUMMER20" class="voucher-input" required>
                    </div>

                    <div class="voucher-form-group">
                        <label class="voucher-form-label">Discount Type *</label>
                        <select name="type" class="voucher-select" required>
                            <option value="fixed" {{ old('type') === 'fixed' ? 'selected' : '' }}>Fixed Amount (₱ Off)</option>
                            <option value="percent" {{ old('type') === 'percent' ? 'selected' : '' }}>Percentage (% Off)</option>
                        </select>
                    </div>

                    <div class="voucher-form-group">
                        <label class="voucher-form-label">Discount Value *</label>
                        <input type="number" step="0.01" name="value" value="{{ old('value') }}" placeholder="e.g. 50.00 or 15" class="voucher-input" required>
                    </div>

                    <div class="voucher-form-group">
                        <label class="voucher-form-label">Minimum Spend (₱)</label>
                        <input type="number" step="0.01" name="min_spend" value="{{ old('min_spend', '0.00') }}" class="voucher-input">
                    </div>

                    <div class="voucher-form-group">
                        <label class="voucher-form-label">Total Usage Limit</label>
                        <input type="number" name="usage_limit" value="{{ old('usage_limit') }}" placeholder="Leave blank for unlimited" class="voucher-input">
                    </div>

                    <div class="voucher-form-group">
                        <label class="voucher-form-label">Expiration Date</label>
                        <input type="date" name="expires_at" value="{{ old('expires_at') }}" class="voucher-input">
                    </div>

                    <button type="submit" class="dash-btn-primary" style="width: 100%; margin-top: 0.5rem; justify-content: center;">
                        ➕ Create Voucher
                    </button>
                </form>
            </div>

            <!-- 2. Active Vouchers Table -->
            <div class="dash-panel">
                <h2 class="courier-section-title">My Store Vouchers ({{ $vouchers->total() }})</h2>

                <div class="dash-table-wrapper" style="margin-top: 1rem;">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Discount</th>
                                <th>Min. Spend</th>
                                <th>Redeemed</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($vouchers as $voucher)
                                <tr>
                                    <td><span class="voucher-code-tag">{{ $voucher->code }}</span></td>
                                    <td>
                                        <strong>
                                            {{ $voucher->type === 'percent' ? $voucher->value . '%' : '₱' . number_format($voucher->value, 2) }}
                                        </strong>
                                    </td>
                                    <td>₱{{ number_format($voucher->min_spend, 2) }}</td>
                                    <td>
                                        {{ $voucher->used_count }} / {{ $voucher->usage_limit ?? '∞' }}
                                    </td>
                                    <td>
                                        <span class="voucher-status-pill {{ $voucher->is_active ? 'voucher-active' : 'voucher-inactive' }}">
                                            {{ $voucher->is_active ? 'Active' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="voucher-actions">
                                            <form action="{{ route('seller.vouchers.toggle', $voucher->id) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="dash-btn-sm" style="border: 1px solid var(--dash-border); background: #fff;">
                                                    {{ $voucher->is_active ? 'Disable' : 'Enable' }}
                                                </button>
                                            </form>
                                            <form action="{{ route('seller.vouchers.destroy', $voucher->id) }}" method="POST" onsubmit="return confirm('Delete this voucher permanently?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dash-btn-sm dash-btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="dash-table-empty">No promotional vouchers created yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 1rem;">
                    {{ $vouchers->links() }}
                </div>
            </div>
        </div>
    </main>
</div>
@endsection