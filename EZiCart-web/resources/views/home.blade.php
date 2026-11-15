@extends('layouts.app')

@section('title', 'EZiCart — Shop products from marketplace sellers')
@section('meta-description', 'Browse approved, in-stock products from EZiCart sellers. Compare details, prices, and seller information.')

@push('styles')
    @vite('resources/css/storefront/landing.css')
    <link rel="stylesheet" href="{{ asset('css/buyer-storefront.css') }}?v={{ filemtime(public_path('css/buyer-storefront.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/category-carousel.css') }}?v={{ filemtime(public_path('css/category-carousel.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/buyer-recommendations.css') }}?v={{ filemtime(public_path('css/buyer-recommendations.css')) }}">
    <script src="{{ asset('js/category-carousel.js') }}?v={{ filemtime(public_path('js/category-carousel.js')) }}" defer></script>
@endpush

@php
    $featuredProducts = $products->take(4);
    $featuredProduct = $products->first();
    $hasFilters = request()->filled('category') || request()->filled('search');
@endphp

@section('content')
    <div class="ez-editorial buyer-storefront" id="top">
        <nav class="editorial-nav buyer-store-nav" aria-label="Marketplace sections">
            <a href="#buyer-categories">Categories</a>
            <a href="#buyer-new-arrivals">New arrivals</a>
            <a href="#browse-products">Discover products</a>
            @auth
                @if (auth()->user()->role === 'buyer')
                    <a href="#marketplace-for-you">For you</a>
                    <a href="{{ route('buyer.dashboard') }}">My orders</a>
                    <a href="{{ route('account.profile.edit') }}">My account</a>
                @endif
            @else
                <a href="{{ route('register.seller') }}">Apply to sell</a>
            @endauth
        </nav>

        <main class="buyer-store-main">
            <section class="buyer-promo" id="discover" aria-labelledby="buyer-promo-title">
                <x-hero-videos />
                <div class="buyer-promo-copy">
                    <p class="buyer-promo-kicker">The EZiCart marketplace</p>
                    @auth
                        @if (auth()->user()->role === 'buyer')
                            <h1 id="buyer-promo-title">Good finds, {{ auth()->user()->first_name }}.</h1>
                        @else
                            <h1 id="buyer-promo-title">Good finds start here.</h1>
                        @endif
                    @else
                        <h1 id="buyer-promo-title">Good finds start here.</h1>
                    @endauth
                    <p>Browse approved, in-stock products from EZiCart sellers. Compare details and prices before you choose.</p>
                    <a class="buyer-promo-action" href="#browse-products">Explore products <span aria-hidden="true">↘</span></a>
                </div>
                @if ($featuredProduct)
                    <div class="storefront-featured-card">
                        <x-catalog-product-card :product="$featuredProduct" />
                    </div>
                @endif
            </section>

            <nav class="buyer-shortcuts" aria-label="Quick links">
                <a href="#buyer-categories"><span class="buyer-shortcut-mark">01</span><span>Shop by category</span></a>
                <a href="#buyer-new-arrivals"><span class="buyer-shortcut-mark">02</span><span>Recently added</span></a>
                @auth
                    @if (auth()->user()->role === 'buyer')
                        <a href="{{ route('buyer.dashboard') }}"><span class="buyer-shortcut-mark">03</span><span>Track an order</span></a>
                        <a href="{{ route('cart.index') }}"><span class="buyer-shortcut-mark">04</span><span>View your cart</span></a>
                    @endif
                @else
                    <a href="{{ route('register') }}"><span class="buyer-shortcut-mark">03</span><span>Create an account</span></a>
                    <a href="{{ route('cart.index') }}"><span class="buyer-shortcut-mark">04</span><span>View your cart</span></a>
                @endauth
            </nav>

            <section class="buyer-shelf buyer-category-shelf" id="buyer-categories" aria-labelledby="buyer-category-title" data-category-carousel aria-roledescription="carousel">
                <header class="buyer-shelf-heading">
                    <div><p class="buyer-section-label">Browse the marketplace</p><h2 id="buyer-category-title">Shop by category</h2></div>
                    <a class="buyer-see-all" href="#browse-products">See all products <span aria-hidden="true">↘</span></a>
                </header>
                <div class="category-carousel-controls" data-carousel-controls hidden>
                    <button type="button" data-carousel-toggle>Pause rotation</button>
                    <button type="button" data-carousel-previous aria-label="Previous categories" aria-controls="category-carousel-track"><x-shop-icon name="arrowback" /></button>
                    <button type="button" data-carousel-next aria-label="Next categories" aria-controls="category-carousel-track"><x-shop-icon name="arrowback" class="category-carousel-next-icon" /></button>
                </div>
                <div class="buyer-category-grid" id="category-carousel-track" data-carousel-track tabindex="0" role="group" aria-label="Shop categories; scroll horizontally to browse">
                    @foreach ($categories as $category)
                        <a class="buyer-category-tile" href="{{ route('home', ['category' => $category['name']]) }}#browse-products">
                            @if ($category['image'])
                                <img class="buyer-category-background" src="{{ asset('images/categories/'.$category['image']) }}" alt="" width="1024" height="1024" loading="lazy" decoding="async">
                            @else
                                <x-shop-icon :name="$category['icon']" class="buyer-category-icon" />
                            @endif
                            <span class="buyer-category-caption">
                                <strong>{{ $category['name'] }}</strong>
                                <span class="buyer-category-link">Explore category <span aria-hidden="true">→</span></span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>

            @if ($featuredProducts->isNotEmpty())
                <section class="buyer-shelf buyer-arrival-shelf" id="buyer-new-arrivals" aria-labelledby="buyer-arrival-title">
                    <header class="buyer-shelf-heading">
                        <div><p class="buyer-section-label">Fresh from EZiCart sellers</p><h2 id="buyer-arrival-title">Recently added</h2></div>
                        <a class="buyer-see-all" href="#browse-products">Browse the full collection <span aria-hidden="true">↘</span></a>
                    </header>
                    <div class="storefront-product-grid">
                        @foreach ($featuredProducts as $product)
                            <x-catalog-product-card :product="$product" />
                        @endforeach
                    </div>
                </section>
            @endif

            @if (auth()->user()?->role === 'buyer')
                @include('buyer.partials.marketplace-for-you', ['products' => $forYouProducts])
            @endif

            <section class="buyer-shelf buyer-discover-shelf" id="browse-products" aria-labelledby="market-title">
                <header class="buyer-shelf-heading buyer-discover-heading">
                    <div>
                        <p class="buyer-section-label">Find something for today</p>
                        <h2 id="market-title">{{ request('category') ? 'Products in ' . request('category') : (request('search') ? 'Search results' : 'Discover products') }}</h2>
                        <p class="buyer-result-count">{{ number_format($products->total()) }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}{{ $hasFilters ? ($products->total() === 1 ? ' matches your filters.' : ' match your filters.') : ' currently available.' }}</p>
                    </div>
                    @if ($hasFilters)
                        <a class="buyer-see-all" href="{{ route('home') }}#browse-products">Clear filters <span aria-hidden="true">↗</span></a>
                    @endif
                </header>

                <nav class="buyer-category-filter" aria-label="Filter by category">
                    <a href="{{ route('home') }}#browse-products" @class(['is-current' => !request('category')])>All products</a>
                    @foreach ($categories as $category)
                        <a href="{{ route('home', ['category' => $category['name']]) }}#browse-products" @class(['is-current' => request('category') === $category['name']])>{{ $category['name'] }}</a>
                    @endforeach
                </nav>

                @if ($products->isEmpty())
                    <div class="buyer-empty-state" role="status">
                        <h3>No products found.</h3>
                        @if ($hasFilters)
                            <p>Try another search or category, or clear your filters to browse all available products.</p>
                            <a class="buyer-promo-action" href="{{ route('home') }}#browse-products">Clear filters</a>
                        @else
                            <p>No approved products are available right now. Please check back soon.</p>
                        @endif
                    </div>
                @else
                    <div class="storefront-product-grid">
                        @foreach ($products as $product)
                            <x-catalog-product-card :product="$product" />
                        @endforeach
                    </div>
                    <nav class="buyer-pagination" aria-label="Product pages">
                        {{ $products->appends(request()->query())->fragment('browse-products')->links() }}
                    </nav>
                @endif
            </section>

            <section class="buyer-help-shelf" id="how-it-works" aria-labelledby="buyer-help-title">
                <div class="buyer-howto">
                    <p class="buyer-section-label">A simple way to shop</p>
                    <h2 id="buyer-help-title">Browse, choose, and track your order.</h2>
                    <ol>
                        <li><span>01</span><strong>Browse</strong><small>Search or choose a category.</small></li>
                        <li><span>02</span><strong>Compare</strong><small>Review listing and seller details.</small></li>
                        <li><span>03</span><strong>Choose</strong><small>Add the products you want to your cart.</small></li>
                        <li><span>04</span><strong>Track</strong><small>Follow order updates from your account.</small></li>
                    </ol>
                </div>
                <div class="buyer-faq" aria-label="Marketplace information">
                    <h2>Shopping on EZiCart</h2>
                    <details><summary>What appears in the marketplace?</summary><p>Only approved products that are in stock appear in the marketplace.</p></details>
                    <details><summary>Where can I find product details?</summary><p>Open a listing to see its details, price, and seller information.</p></details>
                    <details><summary>How can I apply to sell?</summary><p>Submit the seller registration form for admin review.</p></details>
                </div>
            </section>
        </main>

        <footer class="editorial-footer buyer-store-footer">
            <div class="footer-topline"><a class="footer-wordmark" href="#top">EZiCart</a><p>Everyday products from sellers on EZiCart.</p><a class="footer-top-link" href="#top">Back to the beginning ↑</a></div>
            <div class="footer-bottomline"><span>© {{ date('Y') }} EZiCart Marketplace</span><a href="{{ route('register.seller') }}">Apply to sell</a><a href="{{ route('login') }}">Sign in</a></div>
        </footer>
    </div>
@endsection
