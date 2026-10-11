@extends('layouts.app')

@section('title', 'EZiCart — Shop products from marketplace sellers')
@section('meta-description',
    'Browse approved, in-stock products from EZiCart sellers. Compare listing details and
    prices, shop by category, and follow your orders.')

    @push('styles')
        @vite('resources/css/storefront/landing.css')
    @endpush

    @php
        $featuredProducts = $products->take(4);
        $featuredProduct = $products->first();
        $hasFilters = request()->filled('category') || request()->filled('search');
    @endphp

@section('content')
    <div class="ez-editorial" id="top">
        <nav class="editorial-nav" aria-label="Marketplace sections">
            <a href="#discover" data-rev>The marketplace</a>
            <a href="#how-it-works" data-rev>How shopping works</a>
            <a href="#browse-products" data-rev>Explore products</a>
            <a href="{{ route('register.seller') }}" data-rev>Apply to sell</a>
        </nav>

        <main>
            <section class="editorial-stage editorial-hero" data-scroll-stage="hero" aria-labelledby="hero-title">
                <div class="editorial-pin hero-pin">
                    <img class="hero-photograph" src="{{ asset('images/hero_asset.jpg') }}" alt=""
                        fetchpriority="high" width="1600" height="900">
                    <div class="hero-paper-wash" aria-hidden="true"></div>
                    <div class="hero-copy">
                        <h1 id="hero-title" class="hero-wordmark" data-word-reveal>EZiCart</h1>
                        <p class="hero-description" data-word-reveal>
                            Shop approved, in-stock products from EZiCart sellers. Compare details and prices, then add your
                            picks to the cart.
                        </p>
                        <a class="editorial-button hero-cta" href="#browse-products" data-rev>Browse the marketplace</a>
                    </div>
                    <div class="hero-straps" aria-label="Marketplace details">
                        <span data-rev>Seller details</span>
                        <span data-rev>Prices shown</span>
                        <span data-rev>Order updates</span>
                    </div>
                    <span class="stage-index" aria-hidden="true">01 / 03</span>
                </div>
            </section>

            <section class="statement-band" id="discover" aria-labelledby="statement-title">
                <div class="editorial-width statement-inner">
                    <h2 id="statement-title" data-word-reveal>Find products with the details in view.</h2>
                    <div class="statement-columns">
                        <p data-rev>Browse approved listings that currently have stock available.</p>
                        <p data-rev>Check product information, prices, and seller details before you choose.</p>
                        <p data-rev>Shop across categories, add products to your cart, and follow order updates from your
                            account.</p>
                    </div>
                </div>
            </section>

            <section class="editorial-stage editorial-reveal" data-scroll-stage="reveal" aria-labelledby="reveal-title">
                <div class="editorial-pin reveal-pin">
                    <img class="reveal-photograph" src="{{ asset('images/Banner_3.jpg') }}" alt="" loading="lazy"
                        width="1408" height="960">
                    <div class="reveal-paper" aria-hidden="true"></div>
                    <div class="reveal-copy">
                        <h2 id="reveal-title" data-word-reveal>Shop across the categories you use.</h2>
                        <p class="reveal-caption" data-rev>Browse fashion, home and living, beauty, electronics, and
                            groceries.</p>
                        <a href="#browse-products" class="reveal-link" data-rev>Browse available products <span
                                aria-hidden="true">↗</span></a>
                    </div>
                    <span class="stage-index" aria-hidden="true">02 / 03</span>
                </div>
            </section>

            <section class="market-edits" aria-labelledby="edits-title">
                <div class="editorial-width">
                    <div class="edits-heading">
                        <h2 id="edits-title" data-word-reveal>Recently added products</h2>
                        <p data-rev>See current listings and prices. Select a product to view its details.</p>
                    </div>

                    @if ($featuredProducts->isNotEmpty())
                        <div class="edits-list" aria-label="Featured available products">
                            @foreach ($featuredProducts as $index => $product)
                                <a href="{{ route('product.show', $product->id) }}" class="edit-row" data-rev>
                                    <span class="edit-index"
                                        aria-hidden="true">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="edit-product">
                                        <span class="edit-name">{{ $product->name }}</span>
                                        <span
                                            class="edit-description">{{ \Illuminate\Support\Str::limit($product->description ?: 'View product details and seller information.', 105) }}</span>
                                    </span>
                                    <span
                                        class="edit-seller">{{ $product->seller->business_name ?? 'EZiCart seller' }}</span>
                                    <span class="edit-price">₱{{ number_format($product->price, 2) }}</span>
                                    <span class="edit-arrow" aria-hidden="true">↗</span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="edits-empty">
                            @if ($hasFilters)
                                No products match these filters yet. Browse all available listings to start fresh.
                            @else
                                No approved products are available right now. Please check back soon.
                            @endif
                        </p>
                    @endif

                    <a class="text-link" href="#browse-products" data-rev>Browse all products <span
                            aria-hidden="true">↓</span></a>
                </div>
            </section>

            <section class="editorial-stage editorial-composition" data-scroll-stage="composition" id="how-it-works"
                aria-labelledby="composition-title">
                <div class="editorial-pin composition-pin">
                    <div class="composition-copy">
                        <h2 id="composition-title" data-word-reveal>Browse, choose, and track your order.</h2>
                        <p class="composition-intro" data-rev>Shopping on EZiCart takes a few simple steps.</p>
                        <ol class="journey-list">
                            <li data-journey-row><span class="journey-index">01</span><span
                                    class="journey-name">Browse</span><span class="journey-detail">Search or choose a
                                    category.</span></li>
                            <li data-journey-row><span class="journey-index">02</span><span
                                    class="journey-name">Explore</span><span class="journey-detail">Open a listing for
                                    seller and product details.</span></li>
                            <li data-journey-row><span class="journey-index">03</span><span
                                    class="journey-name">Choose</span><span class="journey-detail">Add the products you want
                                    to your cart.</span></li>
                            <li data-journey-row><span class="journey-index">04</span><span
                                    class="journey-name">Track</span><span class="journey-detail">Check order updates from
                                    your account.</span></li>
                        </ol>
                    </div>
                    <a class="composition-image-link"
                        href="{{ $featuredProduct ? route('product.show', $featuredProduct->id) : '#browse-products' }}"
                        aria-label="{{ $featuredProduct ? 'View ' . $featuredProduct->name : 'Browse available products' }}">
                        @if ($featuredProduct?->image_path)
                            <img class="composition-image" src="{{ asset('storage/' . $featuredProduct->image_path) }}"
                                alt="{{ $featuredProduct->name }}" loading="lazy">
                        @else
                            <img class="composition-image composition-image-placeholder"
                                src="{{ asset('images/floating_element_1.png') }}" alt="" loading="lazy"
                                width="970" height="1455">
                        @endif
                        @if ($featuredProduct)
                            <span
                                class="composition-label"><span>{{ $featuredProduct->category }}</span><strong>{{ $featuredProduct->name }}</strong><em>₱{{ number_format($featuredProduct->price, 2) }}</em></span>
                        @else
                            <span class="composition-label"><span>EZiCart</span><strong>Browse the
                                    marketplace</strong><em>Explore available finds</em></span>
                        @endif
                    </a>
                    <span class="stage-index" aria-hidden="true">03 / 03</span>
                </div>
            </section>

            <section class="marketplace-section" id="browse-products" aria-labelledby="market-title">
                <div class="editorial-width">
                    <header class="market-heading">
                        <div>
                            <h2 id="market-title" data-word-reveal>
                                {{ request('category') ? 'Products in ' . request('category') : (request('search') ? 'Search results' : 'Browse products') }}
                            </h2>
                            <p data-rev>{{ number_format($products->total()) }}
                                {{ \Illuminate\Support\Str::plural('product', $products->total()) }}{{ $hasFilters ? ($products->total() === 1 ? ' matches your filters.' : ' match your filters.') : ' currently available.' }}
                            </p>
                        </div>
                        @if ($hasFilters)
                            <a class="text-link clear-filters" href="{{ route('home') }}#browse-products">Clear filters
                                <span aria-hidden="true">↗</span></a>
                        @endif
                    </header>

                    <nav class="category-index" aria-label="Browse product categories">
                        <a href="{{ route('home') }}#browse-products"
                            class="{{ request('category') ? '' : 'is-current' }}">All</a>
                        @foreach ($categories as $category)
                            <a href="{{ route('home', ['category' => $category['name']]) }}#browse-products"
                                class="{{ request('category') === $category['name'] ? 'is-current' : '' }}">
                                <span>{{ $category['name'] }}</span>
                                <span class="category-count"
                                    aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            </a>
                        @endforeach
                    </nav>

                    @if ($products->isEmpty())
                        <div class="market-empty" role="status">
                            <h3>No products found.</h3>
                            @if ($hasFilters)
                                <p>Try a different search or category, or clear your filters to browse all products.</p>
                                <a class="editorial-button" href="{{ route('home') }}#browse-products">Clear filters and
                                    browse all</a>
                            @else
                                <p>No approved, in-stock products are available right now. Please check back soon.</p>
                            @endif
                        </div>
                    @else
                        <div class="product-grid">
                            @foreach ($products as $product)
                                <a href="{{ route('product.show', $product->id) }}" class="product-entry" data-rev>
                                    <span class="product-image-frame">
                                        @if ($product->image_path)
                                            <img src="{{ asset('storage/' . $product->image_path) }}"
                                                alt="{{ $product->name }}" class="product-image" loading="lazy">
                                        @else
                                            <span class="product-image-empty" aria-hidden="true">EZ</span>
                                        @endif
                                        <span class="product-category">{{ $product->category }}</span>
                                    </span>
                                    <span class="product-meta">
                                        <span class="product-name">{{ $product->name }}</span>
                                        <span
                                            class="product-seller">{{ $product->seller->business_name ?? 'EZiCart seller' }}</span>
                                        <span class="product-bottom"><span
                                                class="product-price">₱{{ number_format($product->price, 2) }}</span><span
                                                class="product-rating">{{ $product->average_rating > 0 ? number_format($product->average_rating, 1) . ' / 5' : 'New listing' }}</span></span>
                                    </span>
                                </a>
                            @endforeach
                        </div>

                        <nav class="product-pagination" aria-label="Product pages">
                            {{ $products->appends(request()->query())->fragment('browse-products')->links() }}
                        </nav>
                    @endif
                </div>
            </section>

            <section class="editorial-faq" aria-labelledby="faq-title">
                <div class="editorial-width faq-layout">
                    <div class="faq-heading">
                        <h2 id="faq-title" data-word-reveal>Shopping on EZiCart</h2>
                        <p data-rev>Listings, product details, and selling on the marketplace.</p>
                    </div>
                    <div class="faq-list">
                        <details data-rev>
                            <summary>What appears in the marketplace?</summary>
                            <p>Only approved products that are in stock appear in the marketplace.</p>
                        </details>
                        <details data-rev>
                            <summary>How can I learn more about an item?</summary>
                            <p>Open a product listing to see its details, price, and seller information.</p>
                        </details>
                        <details data-rev>
                            <summary>How do I apply to sell?</summary>
                            <p>Select “Apply to sell” and submit the seller registration form for review.</p>
                        </details>
                    </div>
                </div>
            </section>
        </main>

        <footer class="editorial-footer">
            <div class="footer-topline">
                <a class="footer-wordmark" href="#top">EZiCart</a>
                <p>Everyday products from sellers on EZiCart.</p>
                <a class="footer-top-link" href="#top">Back to the beginning ↑</a>
            </div>
            <div class="footer-bottomline">
                <span>© {{ date('Y') }} EZiCart Marketplace</span>
                <a href="{{ route('register.seller') }}">Apply to sell</a>
                <a href="{{ route('login') }}">Sign in</a>
            </div>
        </footer>
    </div>
@endsection
