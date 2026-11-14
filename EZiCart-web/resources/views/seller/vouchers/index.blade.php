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
                    <h2 class="voucher-form-title">Create New Voucher</h2><p>After the first claim, new campaigns allow only expiry extensions and higher total usage limits. Added usage reserves added funds.</p><a href="{{ route('seller.sales.index') }}">Manage scheduled product sales</a>

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
                                min="1" max="1000000" required placeholder="Maximum funded redemptions" class="voucher-input">
                        </div>
                        <div class="voucher-form-group"><label class="voucher-form-label">Discount applies to<select name="discount_target" class="voucher-select"><option value="merchandise">Merchandise</option><option value="shipping">Shipping subsidy</option></select></label></div>
                        <div class="voucher-form-group"><label class="voucher-form-label">Maximum discount per redemption PHP (required for percentages)<input type="number" name="max_discount" min="0.01" step="0.01" class="voucher-input" value="{{ old('max_discount') }}"></label><p>Activation reserves your maximum campaign budget. <a href="{{ route('finance.wallet') }}">View available promotional funds</a>.</p></div>

                        <div class="voucher-form-group">
                            <label class="voucher-form-label" for="voucher-start">Starts (optional)</label><input id="voucher-start" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="voucher-input"><small>Times use {{ config('app.timezone') }}. Blank starts immediately; funds are reserved now.</small>
                        </div>
                        <div class="voucher-form-group">
                            <label class="voucher-form-label">Expiration Date</label>
                            <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="voucher-input">
                        </div>
                        <div class="voucher-form-group"><label class="voucher-form-label" for="voucher-recipients">Maximum unique buyers</label><input id="voucher-recipients" class="voucher-input" type="number" name="recipient_limit" min="1" max="1000000" value="{{ old('recipient_limit') }}" placeholder="Blank for unlimited recipients"></div>
                        <div class="voucher-form-group"><label class="voucher-form-label" for="voucher-per-buyer">Uses per buyer</label><input id="voucher-per-buyer" class="voucher-input" type="number" name="per_buyer_limit" min="1" max="1000" value="{{ old('per_buyer_limit', 1) }}"></div>
                        @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
                        <div class="voucher-form-group"><label class="voucher-form-label">Visibility<select name="visibility" class="voucher-select"><option value="public">Public / claimable</option><option value="code_only" @selected(old('visibility') === 'code_only')>Code only (hidden from public lists)</option></select></label></div>
                        <div class="voucher-form-group"><label class="voucher-form-label">Product scope<select name="product_scoped" class="voucher-select"><option value="0">All products from my shop</option><option value="1" @selected(old('product_scoped') == 1)>Selected products only</option></select></label>
                        <label class="voucher-form-label">Eligible products (select up to 100)<select name="product_ids[]" class="voucher-select" multiple size="5">@foreach($voucherProducts as $voucherProduct)<option value="{{ $voucherProduct->id }}" @selected(in_array($voucherProduct->id, old('product_ids', [])))>{{ $voucherProduct->name }}</option>@endforeach</select></label><p>Minimum spend counts only selected merchandise after sale discounts. Shipping vouchers use the all-products scope.</p></div>
                        @include('components.voucher-budget-preview')

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
                                            {{ $voucher->used_count }} / {{ $voucher->usage_limit ?? '∞' }}<x-voucher-funding-summary :voucher="$voucher" />
                                            <p class="web-muted">Unique buyers: {{ $voucher->claimed_count }} / {{ $voucher->recipient_limit ?? 'Unlimited' }}<br>Remaining: {{ $voucher->recipient_limit !== null ? max(0, $voucher->recipient_limit - $voucher->claimed_count) : 'Unlimited' }}</p>
                                        </td>
                                        <td>
                                            <span
                                                class="voucher-status-pill {{ $voucher->is_active ? 'voucher-active' : 'voucher-inactive' }}">
                                                {{ $voucher->is_active ? 'Active' : 'Disabled' }}
                                            </span>
                                            @php($voucherReason = app(\App\Services\VoucherEligibilityService::class)->reason($voucher))
                                            <small>{{ $voucherReason['message'] ?? 'Available now' }}</small><small>Starts: {{ $voucher->starts_at?->format('d M Y H:i T') ?: 'Immediately' }}</small>
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
                                                    method="POST" data-decision="seller.vouchers.destroy" data-decision-title="End this voucher?" data-decision-message="This stops new use and releases unused campaign funds. Existing order discounts and claimed-offer history are retained." data-decision-label="End offer" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="dash-btn-sm dash-btn-danger">End offer</button>
                                                </form>
                                            </div>
                                            <small>{{ $voucher->visibility === 'code_only' ? 'Code only · hidden' : 'Public' }} · {{ $voucher->product_scoped ? 'Selected products' : 'All shop products' }}</small>
                                            <details class="web-section"><summary>Edit expiry / limits</summary><form class="web-form" method="POST" action="{{ route('seller.vouchers.limits', $voucher) }}">@csrf @method('PATCH')
                                                @if($voucher->claimed_count < 1 || $voucher->terms_version < 2)
                                                <label>Maximum buyers<input type="number" name="recipient_limit" min="{{ max(1, $voucher->claimed_count) }}" max="1000000" value="{{ $voucher->recipient_limit }}"></label>
                                                <label>Uses per buyer<input type="number" name="per_buyer_limit" min="1" max="1000" value="{{ $voucher->per_buyer_limit }}"></label>
                                                @else<p>Claimed offer: discount, minimum spend, product scope, start time and recipient limits are locked.</p>@endif
                                                <label>Expires ({{ config('app.timezone') }})<input type="datetime-local" name="expires_at" step="1" value="{{ $voucher->expires_at?->format('Y-m-dTH:i:s') }}"></label>
                                                @if($voucher->usage_limit !== null)<label>Total redemptions<input type="number" name="usage_limit" min="{{ max(1, $voucher->usage_limit) }}" max="1000000" value="{{ $voucher->usage_limit }}" required></label>@else<p>This legacy offer has no total usage limit. Create a funded offer to add a bounded campaign budget.</p>@endif
                                                <p>A higher total limit reserves the additional campaign budget from your available funds. Insufficient funds leave everything unchanged.</p>
                                                <button class="dash-btn-sm" type="submit">Save limits</button>
                                            </form></details>
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
