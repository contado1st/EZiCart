<p class="web-section" data-voucher-budget data-available-cents="{{ $availableCents }}" role="status" aria-live="polite">Available campaign funds: PHP {{ number_format($availableCents / 100, 2) }}. Enter a discount cap and redemption limit to preview the required budget. Scheduled offers reserve funds immediately.</p>
@once
@push('styles')<script src="{{ asset('js/voucher-budget-preview.js') }}?v={{ filemtime(public_path('js/voucher-budget-preview.js')) }}" defer></script>@endpush
@endonce
