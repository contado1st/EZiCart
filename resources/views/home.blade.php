@extends('layouts.app')

@section('content')

<!-- Hero Banner -->
<section class="hero-card">
    <div class="hero-left">
        <span class="section-tag">A Better Marketplace</span>
        <h1 class="hero-title">
            Pick well.<br>Live easy.
        </h1>
        <p class="hero-text">
            From daily essentials to special finds, discover shops selected for your real life.
        </p>
        <div class="hero-actions">
            <a href="#shop" class="btn-hero-solid">
                Shop the Collection
            </a>
            <a href="#" class="btn-hero-outline">
                Start Selling
            </a>
        </div>
    </div>

    <div class="hero-right">
        <span class="hero-watermark">EZ</span>
    </div>
</section>

<!-- Shop by Category Section -->
<section>
    <div class="section-header">
        <div>
            <span class="section-tag">Browse Your Way</span>
            <h2 class="section-title">Shop by category</h2>
        </div>
        <a href="#" class="section-link">View all categories &rarr;</a>
    </div>

    <div class="category-grid">
        @foreach($categories as $category)
            <a href="#" class="category-card">
                <div class="category-icon-wrapper">
                    {{ $category['icon'] }}
                </div>
                <h3 class="category-name">{{ $category['name'] }}</h3>
                <span class="category-subtext">Explore finds</span>
            </a>
        @endforeach
    </div>
</section>

<!-- Fresh Picks Section -->
<section id="shop">
    <div class="section-header">
        <div>
            <span class="section-tag">Just In</span>
            <h2 class="section-title">Fresh picks for you</h2>
        </div>
        <a href="#" class="section-link">Explore all &rarr;</a>
    </div>

    <div class="product-grid">
        @foreach($freshPicks as $product)
            <div class="product-card">
                <div class="product-image-wrapper">
                    <span class="product-image-placeholder">Product Image</span>
                </div>
                <div class="product-info">
                    <span class="product-badge">
                        {{ $product['category'] }}
                    </span>
                    <h3 class="product-title">
                        {{ $product['name'] }}
                    </h3>
                    <p class="product-price">
                        ₱{{ number_format($product['price'], 2) }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>
</section>

@endsection