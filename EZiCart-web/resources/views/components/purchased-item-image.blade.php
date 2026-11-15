@props(['item'])
@if($item->purchased_image_path)
    <img src="{{ asset('storage/'.$item->purchased_image_path) }}" alt="{{ $item->product_name }} at purchase" width="56" height="56" loading="lazy">
@else
    <small>Purchase image not recorded</small>
@endif
