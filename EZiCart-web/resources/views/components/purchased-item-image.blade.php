@props(['item'])
@php
    $purchasePath = $item->purchased_image_path;
    $catalogPath = $item->relationLoaded('product') ? $item->getRelation('product')?->image_path : null;
    $displayPath = $purchasePath ?: $catalogPath;
    $imageLabel = $item->product_name.($purchasePath ? ' at purchase' : ($catalogPath ? ' — current catalog image' : ''));
@endphp
<x-product-thumbnail :path="$displayPath" :label="$imageLabel" missing-label="Purchase image not recorded" />
