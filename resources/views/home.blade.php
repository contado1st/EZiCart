@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
@endpush

@section('content')

    <!-- Global Fixed Background -->
    <div class="global-background">
        <div class="bg-grid-pattern"></div>
        <div class="shape-blob blob-1"></div>
        <div class="shape-blob blob-2"></div>
    </div>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="landing-container hero-grid" style="position: relative;">
            
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
                        <span style="color: var(--brand-primary); font-size: 1.25rem;">✓</span> Verified Permits
                    </div>
                    <div class="trust-badge-item">
                        <span style="color: var(--brand-primary); font-size: 1.25rem;">✓</span> Secure Logistics
                    </div>
                    <div class="trust-badge-item">
                        <span style="color: var(--brand-primary); font-size: 1.25rem;">✓</span> Buyer Protection
                    </div>
                </div>
            </div>

            <!-- Floating Decorative Cart Element -->
            <img src="{{ asset('images/floating_element_1.png') }}" alt="Storefront Cart" class="floating-decoration-1">

            <!-- Right: 3D Render Asset with Floating Animation -->
            <div class="hero-image-wrapper animate-float">
                <img src="{{ asset('images/hero_asset.jpg') }}" alt="Fast and secure local delivery" class="main-hero-img">
            </div>
            
        </div>
    </section>

    <!-- Platform Ecosystem / Features -->
    <section class="ecosystem-section">
        <div class="landing-container">
            <div class="section-header timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
                <h2>A Secure Ecosystem</h2>
                <p>Our strictly enforced architecture guarantees a professional experience.</p>
            </div>
            
            <div class="feature-grid">
                <div class="feature-card timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
                    <div class="feature-icon">🛡️</div>
                    <h3>Verified Merchants</h3>
                    <p>No dummy accounts. Every seller undergoes strict manual verification requiring valid Government IDs and Business Permits.</p>
                </div>
                
                <div class="feature-card timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
                    <div class="feature-icon">🛵</div>
                    <h3>Dedicated Fleet</h3>
                    <p>Our integrated middle-mile logistics system ensures your items are tracked securely from merchant to doorstep.</p>
                </div>
                
                <div class="feature-card timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
                    <div class="feature-icon">⭐</div>
                    <h3>Authentic Reviews</h3>
                    <p>Our closed-loop feedback system means ratings can only be left by verified buyers after an order is fully completed.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Decorative Transition Border -->
    <div class="section-transition-border timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']"></div>

    <!-- Marketplace / Products Grid -->
    <section id="browse-products" class="market-section">
        <div class="landing-container">
            
            <!-- Dynamic Headers & Meta -->
            <div class="market-header-flex timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
                <div>
                    <h2 class="market-title">
                        {{ request('category') ? request('category') : (request('search') ? 'Search Results' : 'Explore Marketplace') }}
                    </h2>
                    <p class="market-meta">
                        {{ $products->total() }} verified items available right now
                    </p>
                </div>
                @if(request('category') || request('search'))
                    <a href="{{ route('home') }}#browse-products" class="btn-hero-secondary" style="font-size: 0.875rem; padding: 0.5rem 1rem;">✕ Clear Filters</a>
                @endif
            </div>

            <!-- Categories Navigation -->
            <div class="category-pills timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
                @foreach($categories as $cat)
                    <a href="{{ route('home', ['category' => $cat['name']]) }}#browse-products" 
                       class="category-pill {{ request('category') === $cat['name'] ? 'active' : '' }}">
                        <span>{{ $cat['icon'] }}</span>
                        <span>{{ $cat['name'] }}</span>
                    </a>
                @endforeach
            </div>

            <!-- Products Logic -->
            @if($products->isEmpty())
                <div class="empty-state timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(12px); border-radius: 1rem; padding: 4rem; text-align: center; border: 1px solid rgba(255,255,255,0.9);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                    <h3 style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.5rem;">No products found</h3>
                    <p style="color: var(--text-body); margin-bottom: 1.5rem;">Try searching with another keyword or explore a different category.</p>
                    <a href="{{ route('home') }}#browse-products" class="btn-hero-primary">View All Products</a>
                </div>
            @else
                <div class="product-grid">
                    @foreach($products as $product)
                        <a href="{{ route('product.show', $product->id) }}" class="product-card timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
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

                <div style="margin-top: 3rem;">
                    {{ $products->appends(request()->query())->fragment('browse-products')->links() }}
                </div>
            @endif

        </div>
    </section>

    <!-- Join CTA -->
    <section class="cta-section timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
        <div class="bg-grid-pattern" style="mask-image: linear-gradient(to bottom, transparent 0%, black 50%, transparent 100%); -webkit-mask-image: linear-gradient(to bottom, transparent 0%, black 50%, transparent 100%);"></div>
        <div class="landing-container" style="max-width: 600px; padding: 2rem 0;">
            <h2 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem; letter-spacing: -0.02em;">Ready to scale your local business?</h2>
            <p style="font-size: 1.125rem; margin-bottom: 2.5rem; opacity: 0.95;">Join our verified merchant network and gain access to thousands of local buyers and a dedicated fulfillment fleet.</p>
            <a href="{{ route('register.seller') }}" class="btn-hero-secondary" style="border: none; color: var(--brand-primary); font-weight: 800;">
                Apply as a Merchant
            </a>
        </div>
    </section>

    <!-- NEW: Auto-scroll script for filters and pagination -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            // Check if the URL has active filter or pagination queries
            if (urlParams.has('category') || urlParams.has('search') || urlParams.has('page')) {
                // Slight delay ensures the DOM layout and images are fully rendered
                setTimeout(() => {
                    const marketSection = document.getElementById('browse-products');
                    if (marketSection) {
                        // Calculate exact scroll position minus navbar height (approx 80px)
                        const targetPosition = marketSection.getBoundingClientRect().top + window.scrollY - 80;
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                    }
                }, 150); 
            }
        });
    </script>

@endsection