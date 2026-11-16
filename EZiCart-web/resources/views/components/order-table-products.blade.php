@props(['items'])
@if($items->isNotEmpty())
<div class="order-table-products">
    @foreach($items->take(2) as $item)
        <x-product-table-item :name="$item->product_name" :image="$item->purchased_image_path" missing-label="Purchase image not recorded"><small>{{ $item->variation_info }}{{ $item->variation_info ? ' · ' : '' }}Qty {{ $item->quantity }}</small></x-product-table-item>
    @endforeach
    @if($items->count() > 2)
        <details><summary>{{ $items->count() - 2 }} more products</summary><div class="order-table-products">
            @foreach($items->slice(2) as $item)<x-product-table-item :name="$item->product_name" :image="$item->purchased_image_path" missing-label="Purchase image not recorded"><small>{{ $item->variation_info }}{{ $item->variation_info ? ' · ' : '' }}Qty {{ $item->quantity }}</small></x-product-table-item>@endforeach
        </div></details>
    @endif
</div>
@endif
