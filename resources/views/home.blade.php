@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
@endpush

@section('content')

    <!-- Hero Section with Grids, Blobs, and Gradients -->
    <section class="hero-section">
        <!-- Abstract Background Layers -->
        <div class="bg-grid-pattern"></div>
        <div class="shape-blob blob-1"></div>
        <div class="shape-blob blob-2"></div>

        <div class="landing-container hero-grid">
            <!-- Left: Copy & CTAs -->
            <div class="hero-content animate-fade-in-up">
                <h1>The marketplace you can <span>actually trust.</span></h1>
                <p>
                    EZiCart is an all-in-one online marketplace designed to make everyday shopping fast and simple. We offer a wide range of essential products across 8 major categories from pet care and electronics to fashion, home goods, sports gear, and beauty products bringing quality items directly to your doorstep.
                </p>
                <div class="hero-actions">
                    <a href="#browse-products" class="btn-hero-primary">Start Shopping</a>
                    <a href="{{ route('register.seller') }}" class="btn-hero-secondary">Become a Seller</a>
                </div>
                
                <!-- Trust Badges -->
                <div class="trust-badges">
                    <div class="trust-badge-item">
                        <span style="color: var(--brand-primary);">✓</span> Verified Permits
                    </div>
                    <div class="trust-badge-item">
                        <span style="color: var(--brand-primary);">✓</span> Secure Logistics
                    </div>
                    <div class="trust-badge-item">
                        <span style="color: var(--brand-primary);">✓</span> Buyer Protection
                    </div>
                </div>
            </div>

            <!-- Right: 3D Render Asset with Floating Animation -->
            <div class="hero-image-wrapper animate-float">
                <img src="{{ asset('images/hero_asset.jpg') }}" alt="Fast and secure local delivery">
            </div>
        </div>
    </section>

    <!-- Platform Ecosystem / Features -->
    <section class="ecosystem-section">
        <div class="landing-container">
            <div class="section-header animate-fade-in-up">
                <h2>A Secure Ecosystem</h2>
                <p>Our strictly enforced architecture guarantees a professional experience.</p>
            </div>
            
            <div class="feature-grid">
                <div class="feature-card animate-fade-in-up delay-100">
                    <div class="feature-icon">🛡️</div>
                    <h3>Verified Merchants</h3>
                    <p>No dummy accounts. Every seller undergoes strict manual verification requiring valid Government IDs and Business Permits.</p>
                </div>
                
                <div class="feature-card animate-fade-in-up delay-200">
                    <div class="feature-icon">🛵</div>
                    <h3>Dedicated Fleet</h3>
                    <p>Our integrated middle-mile logistics system ensures your items are tracked securely from merchant to doorstep.</p>
                </div>
                
                <div class="feature-card animate-fade-in-up delay-300">
                    <div class="feature-icon">⭐</div>
                    <h3>Authentic Reviews</h3>
                    <p>Our closed-loop feedback system means ratings can only be left by verified buyers after an order is fully completed.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Marketplace / Products Grid -->
    <section id="browse-products" class="market-section">
        <div class="landing-container">
            
            <!-- Dynamic Headers & Meta -->
            <div class="market-header-flex animate-fade-in-up">
                <div>
                    <h2 class="market-title">
                        {{ request('category') ? request('category') : (request('search') ? 'Search Results' : 'Explore Marketplace') }}
                    </h2>
                    <p class="market-meta">
                        {{ $products->total() }} verified items available right now
                    </p>
                </div>
                @if(request('category') || request('search'))
                    <a href="{{ route('home') }}" class="category-clear-btn">✕ Clear Filters</a>
                @endif
            </div>

            <!-- Categories Navigation -->
            <div class="category-pills animate-fade-in-up delay-100">
                @foreach($categories as $cat)
                    <a href="{{ route('home', ['category' => $cat['name']]) }}" 
                       class="category-pill {{ request('category') === $cat['name'] ? 'active' : '' }}">
                        <span>{{ $cat['icon'] }}</span>
                        <span>{{ $cat['name'] }}</span>
                    </a>
                @endforeach
            </div>

            <!-- Products Logic -->
            @if($products->isEmpty())
                <div class="empty-state animate-fade-in-up delay-200">
                    <div class="empty-icon">🔍</div>
                    <h3 class="empty-title">No products found</h3>
                    <p class="empty-text">Try searching with another keyword or explore a different category.</p>
                    <a href="{{ route('home') }}" class="btn-hero-primary">View All Products</a>
                </div>
            @else
                <div class="product-grid">
                    @foreach($products as $index => $product)
                        <!-- Calculating a staggered delay based on index for the grid items -->
                        <a href="{{ route('product.show', $product->id) }}" class="product-card animate-fade-in-up" style="animation-delay: {{ ($index % 4) * 100 + 200 }}ms;">
                            <div class="product-image-wrapper">
                                @if($product->image_path)
                                    <img src="{{ asset('storage/' . $product->image_path) }}" 
                                         alt="{{ $product->name }}" 
                                         class="product-image">
                                @else
                                    <span style="font-size: 3rem; opacity: 0.5;">📦</span>
                                @endif
                                <span class="product-badge-overlay">{{ $product->category }}</span>
                            </div>

                            <div class="product-info">
                                <h3 class="product-title">{{ $product->name }}</h3>
                                <p class="product-store">Sold by {{ $product->seller->business_name ?? 'EZiCart Merchant' }}</p>
                                
                                <div class="product-footer">
                                    <span class="product-price">₱{{ number_format($product->price, 2) }}</span>
                                    <span class="product-rating">
                                        <span class="star-icon">★</span> 
                                        {{ $product->average_rating > 0 ? $product->average_rating : 'New' }}
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="pagination-wrapper">
                    {{ $products->links() }}
                </div>
            @endif

        </div>
    </section>

    <!-- Join CTA -->
    <section class="cta-section">
        <div class="bg-grid-pattern" style="mask-image: linear-gradient(to bottom, transparent 0%, black 50%, transparent 100%); -webkit-mask-image: linear-gradient(to bottom, transparent 0%, black 50%, transparent 100%);"></div>
        <div class="landing-container animate-fade-in-up" style="max-width: 600px;">
            <h2 style="font-size: 2.25rem; font-weight: 800; margin-bottom: 1rem;">Ready to scale your local business?</h2>
            <p style="font-size: 1.125rem; margin-bottom: 2rem; opacity: 0.95;">Join our verified merchant network and gain access to thousands of local buyers and a dedicated fulfillment fleet.</p>
            <a href="{{ route('register.seller') }}" class="btn-hero-secondary" style="border: none; color: var(--brand-primary); font-weight: 800;">
                Apply as a Merchant
            </a>
        </div>
    </section>

@endsection