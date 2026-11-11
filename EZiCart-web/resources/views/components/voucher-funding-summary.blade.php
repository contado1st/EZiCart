@props(['voucher'])
@if($voucher->budget)
    <p>{{ $voucher->discount_target === 'shipping' ? 'Shipping subsidy' : 'Merchandise discount' }} · {{ $voucher->seller_id === null ? 'Platform treasury' : 'Seller-funded' }}<br>Reserved PHP {{ number_format($voucher->budget->reserveAccount->balance_cents / 100, 2) }} · Consumed PHP {{ number_format($voucher->budget->consumed_cents / 100, 2) }} · {{ $voucher->budget->status }}</p>
@else
    <p>Historical unreserved offer. New activations require a bounded funded budget.</p>
@endif
