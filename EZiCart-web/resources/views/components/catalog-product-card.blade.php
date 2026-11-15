@props(['product'])

@pushOnce('styles', 'catalog-product-card-styles')
    <link rel="stylesheet" href="{{ asset('css/catalog-product-card.css') }}?v={{ filemtime(public_path('css/catalog-product-card.css')) }}">
@endPushOnce

<article {{ $attributes->class(['catalog-product', 'storefront-product-card']) }}>
    <a class="catalog-product-main" href="{{ route('product.show', $product) }}">
        <div class="catalog-product-image">
            @if ($product->image_path)
                <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" width="400" height="400" loading="lazy" decoding="async">
            @else
                <span class="catalog-image-placeholder"><x-shop-icon name="package1" /><span>Product image unavailable</span></span>
            @endif
        </div>
        <div class="catalog-product-content">
            <span class="catalog-product-category">{{ $product->category_label }}</span>
            <h2 title="{{ $product->name }}">{{ $product->name }}</h2>
            <div class="catalog-product-price"><x-product-price :product="$product" /></div>
            <div class="catalog-product-rating">
                @if ($product->catalog_review_count > 0)
                    <x-shop-icon name="star" /><span>{{ number_format($product->catalog_rating, 1) }}</span><small>({{ number_format($product->catalog_review_count) }})</small>
                @else
                    <span>No ratings yet</span>
                @endif
            </div>
            <div class="catalog-product-store">{{ $product->seller?->business_name ?: $product->seller?->first_name ?: 'EZiCart seller' }}</div>
            <div class="catalog-product-location">
                @if ($product->seller?->province)<x-shop-icon name="location" /><span>{{ $product->seller->province }}</span>@endif
            </div>
        </div>
    </a>
    @if ($product->seller?->role === 'seller' && $product->seller?->status === 'approved')
        <a class="catalog-shop-link" href="{{ route('shops.show', $product->seller) }}">Visit shop <x-shop-icon name="arrowback" /></a>
    @endif
</article>
