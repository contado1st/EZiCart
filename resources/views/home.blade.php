@extends('layouts.app')

@push('styles')
    @vite('resources/css/storefront/landing.css')
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
        <div class="landing-container hero-grid u-extracted-50666a574d">

            <!-- Left: Copy & CTAs -->
            <div class="hero-content animate-fade-in-up">
                <h1>The marketplace you can <span>actually trust.</span></h1>
                <p>
                    EZiCart is an all-in-one online marketplace designed to make everyday shopping fast and simple. We offer
                    a wide range of essential products across 8 major categories from pet care and electronics to fashion,
                    home goods, sports gear, and beauty products bringing quality items directly to your doorstep.
                </p>
                <div class="hero-actions">
                    <a href="#browse-products" class="btn-hero-primary">Start Shopping</a>
                    <a href="{{ route('register.seller') }}" class="btn-hero-secondary">Become a Seller</a>
                </div>

                <!-- Trust Badges -->
                <div class="trust-badges">
                    <div class="trust-badge-item">
                        <span class="u-extracted-da92d5303b">✓</span> Verified Permits
                    </div>
                    <div class="trust-badge-item">
                        <span class="u-extracted-da92d5303b">✓</span> Secure Logistics
                    </div>
                    <div class="trust-badge-item">
                        <span class="u-extracted-da92d5303b">✓</span> Buyer Protection
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
            <div
                class="section-header timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
                <h2>A Secure Ecosystem</h2>
                <p>Our strictly enforced architecture guarantees a professional experience.</p>
            </div>

            <div class="feature-grid">
                <div
                    class="feature-card timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
                    <div class="feature-icon">🛡️</div>
                    <h3>Verified Merchants</h3>
                    <p>No dummy accounts. Every seller undergoes strict manual verification requiring valid Government IDs
                        and Business Permits.</p>
                </div>

                <div
                    class="feature-card timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
                    <div class="feature-icon">🛵</div>
                    <h3>Dedicated Fleet</h3>
                    <p>Our integrated middle-mile logistics system ensures your items are tracked securely from merchant to
                        doorstep.</p>
                </div>

                <div
                    class="feature-card timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
                    <div class="feature-icon">⭐</div>
                    <h3>Authentic Reviews</h3>
                    <p>Our closed-loop feedback system means ratings can only be left by verified buyers after an order is
                        fully completed.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Decorative Transition Border -->
    <div
        class="section-transition-border timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
    </div>

    <!-- Marketplace / Products Grid -->
    <section id="browse-products" class="market-section">
        <div class="landing-container">

            <!-- Dynamic Headers & Meta -->
            <div
                class="market-header-flex timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
                <div>
                    <h2 class="market-title">
                        {{ request('category') ? request('category') : (request('search') ? 'Search Results' : 'Explore Marketplace') }}
                    </h2>
                    <p class="market-meta">
                        {{ $products->total() }} verified items available right now
                    </p>
                </div>
                @if (request('category') || request('search'))
                    <a href="{{ route('home') }}#browse-products" class="btn-hero-secondary u-extracted-cd46c199af">✕ Clear
                        Filters</a>
                @endif
            </div>

            <!-- Categories Navigation -->
            <div
                class="category-pills timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
                @foreach ($categories as $cat)
                    <a href="{{ route('home', ['category' => $cat['name']]) }}#browse-products"
                        class="category-pill {{ request('category') === $cat['name'] ? 'active' : '' }}">
                        <span>{{ $cat['icon'] }}</span>
                        <span>{{ $cat['name'] }}</span>
                    </a>
                @endforeach
            </div>

            <!-- Products Logic -->
            @if ($products->isEmpty())
                <div
                    class="empty-state timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]'] u-extracted-6e3380726a">
                    <div class="u-extracted-779aaf5ac2">🔍</div>
                    <h3 class="u-extracted-77c628239d">No
                        products found</h3>
                    <p class="u-extracted-80b3f7fa91">Try searching with another keyword or explore
                        a different category.</p>
                    <a href="{{ route('home') }}#browse-products" class="btn-hero-primary">View All Products</a>
                </div>
            @else
                <div class="product-grid">
                    @foreach ($products as $product)
                        <a href="{{ route('product.show', $product->id) }}"
                            class="product-card timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
                            <div class="product-image-wrapper">
                                @if ($product->image_path)
                                    <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}"
                                        class="product-image">
                                @else
                                    <span class="u-extracted-17d832c8fd">📦</span>
                                @endif
                                <span class="product-badge-overlay">{{ $product->category }}</span>
                            </div>

                            <div class="product-info">
                                <h3 class="product-title">{{ $product->name }}</h3>
                                <p class="product-store">Sold by
                                    {{ $product->seller->business_name ?? 'EZiCart Merchant' }}</p>

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

                <div class="u-extracted-7d64d4e6a9">
                    {{ $products->appends(request()->query())->fragment('browse-products')->links() }}
                </div>
            @endif

        </div>
    </section>

    <!-- Join CTA -->
    <section class="cta-section timeline-view animate-blurred-fade-in [class~='animate-range-[entry_10%_contain_30%]']">
        <div class="bg-grid-pattern u-extracted-01e802c113">
        </div>
        <div class="landing-container u-extracted-8e911b8c59">
            <h2 class="u-extracted-9d0e418a72">Ready to scale
                your local business?</h2>
            <p class="u-extracted-835d8ed888">Join our verified merchant network and
                gain access to thousands of local buyers and a dedicated fulfillment fleet.</p>
            <a href="{{ route('register.seller') }}" class="btn-hero-secondary u-extracted-a3b3c46d68">
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
                        const targetPosition = marketSection.getBoundingClientRect().top + window.scrollY -
                            80;
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
