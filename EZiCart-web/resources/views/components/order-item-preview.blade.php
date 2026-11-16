@props(['item'])
<div {{ $attributes->class(['product-table-item', 'order-item-preview']) }}>
    <x-purchased-item-image :item="$item" />
    <div class="product-table-copy">
        <div class="product-table-name">{{ $item->product_name }}</div>
        @if($item->variation_info)<small>{{ $item->variation_info }}</small>@endif
        <small>Qty {{ $item->quantity }}</small>
        @if(!$item->purchased_image_path && $item->relationLoaded('product') && $item->getRelation('product')?->image_path)
            <small class="order-item-image-note">Current catalog photo · purchase photo not recorded</small>
        @endif
        {{ $slot }}
    </div>
</div>
