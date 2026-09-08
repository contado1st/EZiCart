@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
@endpush

@section('content')
<div class="home-container">
    <!-- Hero Banner -->
    <div class="hero-banner">
        <h1 class="hero-title">Welcome to EZiCart 🛒</h1>
        <p class="hero-subtitle">Your marketplace for local products and fast delivery.</p>
    </div>

    <!-- Categories Section -->
    <section>
        <h2 class="section-title">Browse Categories</h2>
        <div class="category-grid">
            @foreach($categories as $category)
                <div class="category-card">
                    <div class="category-icon">{{ $category['icon'] }}</div>
                    <div class="category-name">{{ $category['name'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Fresh Picks Section -->
    <section>
        <h2 class="section-title">Fresh Picks</h2>
        <div class="product-grid">
            @foreach($freshPicks as $product)
                <div class="product-card">
                    <div>
                        <div class="product-image-placeholder">📦</div>
                        <span class="product-category-label">{{ $product['category'] }}</span>
                        <h3 class="product-name">{{ $product['name'] }}</h3>
                    </div>
                    <div class="product-footer">
                        <span class="product-price">₱{{ number_format($product['price'], 2) }}</span>
                        <button class="btn-cart">Add to Cart</button>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection