@props(['item'])
<small>PHP {{ number_format($item->unit_price, 2) }} per unit
    @if($item->pricing_snapshot['price_kind'] ?? null) · {{ $item->pricing_snapshot['price_kind'] }} @endif
</small>
