@props(['item'])
@if($item->purchased_image_path)
    <x-product-thumbnail :path="$item->purchased_image_path" :label="$item->product_name.' at purchase'" missing-label="Purchase image not recorded" />
@else
    <x-product-thumbnail :label="$item->product_name" missing-label="Purchase image not recorded" />
@endif
