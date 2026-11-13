@extends('layouts.app')
@push('styles') @vite('resources/css/shared/dashboard.css') @endpush
@section('content')
<main class="container workflow-admin-page"><h1>Platform vouchers</h1>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <form method="POST" action="{{ route('admin.vouchers.store') }}" class="dash-panel">@csrf
        <label>Code<input name="code" required maxlength="50" value="{{ old('code') }}"></label>
        <label>Discount type<select name="type" required><option value="fixed">Fixed amount</option><option value="percent">Percentage</option></select></label>
        <label>Value<input name="value" type="number" min="0.01" step="0.01" required value="{{ old('value') }}"></label>
        <label>Minimum eligible spend<input name="min_spend" type="number" min="0" step="0.01" required value="{{ old('min_spend', 0) }}"></label>
        <label>Maximum discount (optional)<input name="max_discount" type="number" min="0.01" step="0.01" value="{{ old('max_discount') }}"></label>
        <label>Funded redemption limit<input name="usage_limit" type="number" min="1" max="1000000" required value="{{ old('usage_limit') }}"></label>
        <label>Discount applies to<select name="discount_target"><option value="merchandise">Merchandise</option><option value="shipping">Shipping subsidy</option></select></label><p>Activation reserves the maximum budget from the shared platform treasury. Percentage offers require a maximum discount.</p><a href="{{ route('finance.wallet') }}">View treasury funding</a>
        <label>Expiry (optional)<input name="expires_at" type="datetime-local" value="{{ old('expires_at') }}"></label>
        <label>Starts (optional)<input name="starts_at" type="datetime-local" value="{{ old('starts_at') }}"></label><p>Times use {{ config('app.timezone') }}. Blank start means immediately.</p>
        <label>Maximum unique buyers<input name="recipient_limit" type="number" min="1" max="1000000" value="{{ old('recipient_limit') }}"></label>
        <label>Uses per buyer<input name="per_buyer_limit" type="number" min="1" max="1000" value="{{ old('per_buyer_limit', 1) }}"></label>
        @include('components.voucher-budget-preview')
        <button class="dash-btn-sm" type="submit">Create platform voucher</button>
    </form>
    @forelse($vouchers as $voucher)<article class="dash-panel"><h2>{{ $voucher->code }}</h2><p>{{ $voucher->type }}: {{ $voucher->value }} · {{ $voucher->used_count }} uses · {{ $voucher->is_active ? 'Active' : 'Disabled' }}</p>
        <x-voucher-funding-summary :voucher="$voucher" />
        <p>Starts: {{ $voucher->starts_at?->format('d M Y H:i T') ?: 'Immediately' }} · Expires: {{ $voucher->expires_at?->format('d M Y H:i T') ?: 'No scheduled expiry' }}</p>
        <form method="POST" action="{{ route('admin.vouchers.toggle', $voucher) }}" data-decision="admin.vouchers.toggle" data-decision-title="Change platform voucher availability?" data-decision-message="This changes whether buyers can use the selected platform voucher." data-decision-label="Change availability" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf @method('PATCH')<button class="dash-btn-sm" type="submit">{{ $voucher->is_active ? 'Disable' : 'Enable' }} voucher</button></form>
    </article>@empty<p>No platform vouchers.</p>@endforelse{{ $vouchers->links() }}
</main>
@endsection
