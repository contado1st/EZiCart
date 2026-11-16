@props(['path' => null, 'src' => null, 'label' => 'Product', 'missingLabel' => 'Image unavailable'])
@pushOnce('styles', 'product-table-media')
    <link rel="stylesheet" href="{{ asset('css/product-table-media.css') }}?v={{ filemtime(public_path('css/product-table-media.css')) }}">
    <script src="{{ asset('js/product-table-media.js') }}?v={{ filemtime(public_path('js/product-table-media.js')) }}" defer></script>
@endPushOnce
@php($imageUrl = $src ?: ($path ? asset('storage/'.$path) : null))
<span {{ $attributes->class(['product-table-thumbnail']) }}>
    <span class="product-table-image-fallback" role="img" aria-label="{{ $missingLabel }}: {{ $label }}" title="{{ $missingLabel }}" @if($imageUrl) aria-hidden="true" @endif><x-shop-icon name="package1" /></span>
    @if($imageUrl)<img src="{{ $imageUrl }}" alt="{{ $label }}" width="48" height="48" loading="lazy" decoding="async" data-product-table-image>@endif
</span>
