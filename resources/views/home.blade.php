@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
@endpush

@section('content')
<div class="container market-container">

    <!-- Categories Navigation Bar -->
    <section class="market-category-section">
        <div class="section-header">
            <h2 class="section-title">Shop by Category</h2>
            @if(request('category') || request('search'))
                <a href="{{ route('home') }}" class="section-link category-clear-btn">Clear Filters ✕</a>
            @endif
        </div>

        <div class="category-grid">
            @foreach($categories as $cat)
                <a href="{{ route('home', ['category' => $cat['name']]) }}" 
                   class="category-card {{ request('category') === $cat['name'] ? 'market-category-active' : '' }}">
                    <div class="category-icon-wrapper">{{ $cat['icon'] }}</div>
                    <span class="category-name">{{ $cat['name'] }}</span>
                    <span class="category-subtext">Explore items</span>
                </a>
            @endforeach
        </div>
    </section>

    <!-- Marketplace Products Grid -->
    <section>
        <div class="section-header">
            <div class="market-product-meta-header">
                <h2 class="section-title">
                    {{ request('category') ? request('category') : (request('search') ? 'Search Results' : 'Featured Marketplace Products') }}
                </h2>
                <p class="market-item-count">
                    {{ $products->total() }} items available right now
                </p>
            </div>
        </div>

        @if($products->isEmpty())
            <div class="market-empty-box">
                <div class="market-empty-icon">🔍</div>
                <h3 class="market-empty-title">No products found</h3>
                <p class="market-empty-text">
                    Try searching with another keyword or explore a different category.
                </p>
                <a href="{{ route('home') }}" class="btn-primary market-empty-action">
                    View All Products
                </a>
            </div>
        @else
            <div class="product-grid">
                @foreach($products as $product)
                    <div class="product-card">
                        <div class="product-image-wrapper">
                            @if($product->image_path)
                                <img src="{{ asset('storage/' . $product->image_path) }}" 
                                     alt="{{ $product->name }}" 
                                     class="market-img-tag">
                            @else
                                <span class="product-image-placeholder">📦 No Image</span>
                            @endif
                        </div>

                        <div class="product-info">
                            <div class="market-card-badge-row">
                                <span class="product-badge">{{ $product->category }}</span>
                                <span class="market-stock-count">{{ $product->stock }} left</span>
                            </div>

                            <a href="{{ route('product.show', $product->id) }}" class="market-title-link">
                                <h3 class="product-title">{{ $product->name }}</h3>
                            </a>

                            <div class="market-store-tag">
                                Store: <span class="market-store-name">{{ $product->seller->business_name ?? 'EZiCart Merchant' }}</span>
                            </div>

                            <div class="market-card-action-row">
                                <span class="product-price">₱{{ number_format($product->price, 2) }}</span>
                                <a href="{{ route('product.show', $product->id) }}" class="btn-primary market-btn-inspect">
                                    View Item
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="market-pagination-wrap">
                {{ $products->links() }}
            </div>
        @endif
    </section>
</div>
@endsection