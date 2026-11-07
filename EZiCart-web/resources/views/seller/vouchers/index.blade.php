@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
    @vite('resources/css/seller/seller-vouchers.css')
@endpush

@section('content')
    <div class="dash-wrapper">
        <!-- Sidebar -->
<!-- Main Workspace -->
        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Promotional Vouchers & Discounts</h1>
                    <p class="dash-subtitle">Create discount vouchers to promote items and drive customer orders.</p>
                </div>
            </div>

            @if (session('success'))
                <div class="dash-alert-success"><x-shop-icon name="checklist" /> {{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="dash-alert-success u-extracted-ee8231be38">
                    <x-shop-icon name="warning" /> {{ session('error') }}
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
                            <input type="text" name="code" value="{{ old('code') }}" placeholder="e.g. SUMMER20"
                                class="voucher-input" required>
                        </div>

                        <div class="voucher-form-group">
                            <label class="voucher-form-label">Discount Type *</label>
                            <select name="type" class="voucher-select" required>
                                <option value="fixed" {{ old('type') === 'fixed' ? 'selected' : '' }}>Fixed Amount (₱ Off)
                                </option>
                                <option value="percent" {{ old('type') === 'percent' ? 'selected' : '' }}>Percentage (%
                                    Off)</option>
                            </select>
                        </div>

                        <div class="voucher-form-group">
                            <label class="voucher-form-label">Discount Value *</label>
                            <input type="number" step="0.01" name="value" value="{{ old('value') }}"
                                placeholder="e.g. 50.00 or 15" class="voucher-input" required>
                        </div>

                        <div class="voucher-form-group">
                            <label class="voucher-form-label">Minimum Spend (₱)</label>
                            <input type="number" step="0.01" name="min_spend" value="{{ old('min_spend', '0.00') }}"
                                class="voucher-input">
                        </div>

                        <div class="voucher-form-group">
                            <label class="voucher-form-label">Total Usage Limit</label>
                            <input type="number" name="usage_limit" value="{{ old('usage_limit') }}"
                                placeholder="Leave blank for unlimited" class="voucher-input">
                        </div>

                        <div class="voucher-form-group">
                            <label class="voucher-form-label">Expiration Date</label>
                            <input type="date" name="expires_at" value="{{ old('expires_at') }}" class="voucher-input">
                        </div>
                        <div class="voucher-form-group"><label class="voucher-form-label" for="voucher-recipients">Maximum unique buyers</label><input id="voucher-recipients" class="voucher-input" type="number" name="recipient_limit" min="1" max="1000000" value="{{ old('recipient_limit') }}" placeholder="Blank for unlimited recipients"></div>
                        <div class="voucher-form-group"><label class="voucher-form-label" for="voucher-per-buyer">Uses per buyer</label><input id="voucher-per-buyer" class="voucher-input" type="number" name="per_buyer_limit" min="1" max="1000" value="{{ old('per_buyer_limit', 1) }}"></div>
                        @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif

                        <button type="submit" class="dash-btn-primary u-extracted-92762f464d">
                            <x-shop-icon name="checklist" /> Create Voucher
                        </button>
                    </form>
                </div>

                <!-- 2. Active Vouchers Table -->
                <div class="dash-panel">
                    <h2 class="courier-section-title">My Store Vouchers ({{ $vouchers->total() }})</h2>

                    <div class="dash-table-wrapper u-extracted-dab43fb936">
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
                                            <p class="web-muted">Unique buyers: {{ $voucher->claimed_count }} / {{ $voucher->recipient_limit ?? 'Unlimited' }}<br>Remaining: {{ $voucher->recipient_limit !== null ? max(0, $voucher->recipient_limit - $voucher->claimed_count) : 'Unlimited' }}</p>
                                        </td>
                                        <td>
                                            <span
                                                class="voucher-status-pill {{ $voucher->is_active ? 'voucher-active' : 'voucher-inactive' }}">
                                                {{ $voucher->is_active ? 'Active' : 'Disabled' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="voucher-actions">
                                                <form action="{{ route('seller.vouchers.toggle', $voucher->id) }}"
                                                    method="POST" data-decision="seller.vouchers.toggle" data-decision-title="Change voucher availability?" data-decision-message="This changes whether buyers can use the selected voucher." data-decision-label="Change availability" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="dash-btn-sm u-extracted-e013d740bb">
                                                        {{ $voucher->is_active ? 'Disable' : 'Enable' }}
                                                    </button>
                                                </form>
                                                <form action="{{ route('seller.vouchers.destroy', $voucher->id) }}"
                                                    method="POST" data-decision="seller.vouchers.destroy" data-decision-title="Delete this voucher?" data-decision-message="This permanently deletes the selected voucher." data-decision-label="Delete voucher" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="dash-btn-sm dash-btn-danger">Delete</button>
                                                </form>
                                            </div>
                                            <details class="web-section"><summary>Edit recipient limits</summary><form class="web-form" method="POST" action="{{ route('seller.vouchers.limits', $voucher) }}">@csrf @method('PATCH')<label for="limit-{{ $voucher->id }}">Maximum buyers<input id="limit-{{ $voucher->id }}" type="number" name="recipient_limit" min="{{ max(1, $voucher->claimed_count) }}" max="1000000" value="{{ $voucher->recipient_limit }}"></label><label for="buyer-limit-{{ $voucher->id }}">Uses per buyer<input id="buyer-limit-{{ $voucher->id }}" type="number" name="per_buyer_limit" min="1" max="1000" value="{{ $voucher->per_buyer_limit }}"></label><label for="expiry-{{ $voucher->id }}">Expires<input id="expiry-{{ $voucher->id }}" type="datetime-local" name="expires_at" value="{{ $voucher->expires_at?->format('Y-m-d\TH:i') }}"></label><button class="dash-btn-sm" type="submit">Save limits</button></form></details>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="dash-table-empty">No promotional vouchers created yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="u-extracted-dab43fb936">
                        {{ $vouchers->links() }}
                    </div>
                </div>
            </div>
        </main>
    </div>
@endsection
