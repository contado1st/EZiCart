@extends('layouts.app')
@section('content')
<main class="web-operations"><header><h1>Vouchers</h1><p>Save a voucher, then enter its code at checkout. Recipient limits count unique buyers; redemption limits are separate.</p></header>
@include('components.feature-feedback')
<nav class="web-actions" aria-label="Voucher views"><a class="feature-button" href="{{ route('buyer.vouchers.index') }}" @if($view === 'available') aria-current="page" @endif>Available and upcoming</a><a class="feature-button" href="{{ route('buyer.vouchers.index', ['view' => 'saved']) }}" @if($view === 'saved') aria-current="page" @endif>Saved vouchers and history</a></nav>
@forelse($vouchers as $voucher)
    @php($reason = app(\App\Services\VoucherEligibilityService::class)->reason($voucher))
    @php($claim = $claims[$voucher->id] ?? null)
    <section class="dash-panel"><h2>{{ $voucher->code }} · {{ $voucher->seller?->business_name ?: 'EZiCart platform' }}</h2>
        <p>{{ $voucher->type === 'percent' ? $voucher->value.'% off' : '₱'.number_format($voucher->value, 2).' off' }} · Minimum spend ₱{{ number_format($voucher->min_spend, 2) }}</p>
        <p>{{ $voucher->claimed_count }} claimed · Maximum recipients: {{ $voucher->recipient_limit ?? 'Unlimited' }} · Remaining: {{ $voucher->recipient_limit !== null ? max(0, $voucher->recipient_limit - $voucher->claimed_count) : 'Unlimited' }}</p>
        <p>Starts: {{ $voucher->starts_at?->format('d M Y H:i T') ?: 'Immediately' }} · Expires: {{ $voucher->expires_at?->format('d M Y H:i T') ?: 'No scheduled expiry' }}</p>
        @if($reason)<p role="status"><strong>Unavailable:</strong> {{ $reason['message'] }}</p>@endif
        @if($claim)
            <p><strong>Saved to your account</strong> · Used {{ $claim->redemption_count }} / {{ $voucher->per_buyer_limit ?? 'Unlimited' }} times</p>
            @if(!$reason && $voucher->per_buyer_limit !== null && $claim->redemption_count >= $voucher->per_buyer_limit)<p>Your per-buyer usage limit has been reached.</p>@elseif(!$reason)<p>Check eligible spend at checkout. Pending cancellation keeps usage reserved until approved.</p>@endif
        @elseif($voucher->recipient_limit !== null && $voucher->claimed_count >= $voucher->recipient_limit)<p>Fully claimed</p>
        @elseif(!$reason)<form method="POST" action="{{ route('buyer.vouchers.claim', $voucher) }}">@csrf<button class="dash-btn-primary" type="submit">Claim voucher</button></form>@endif
    </section>
@empty<section class="dash-panel"><p>{{ $view === 'saved' ? 'No saved vouchers yet.' : 'No active vouchers are available.' }}</p></section>@endforelse
{{ $vouchers->links() }}</main>
@endsection
