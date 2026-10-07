@extends('layouts.landing')

@section('title', 'EZiCart - Full-Featured E-Commerce Marketplace')

@section('content')
    <!-- Hero Section with Full Edge Utilization -->
    <section class="ez-hero">
        <div class="ez-container-fluid">
            <div class="ez-hero-grid">
                <!-- Left Column: Copy & Actions -->
                <div class="ez-hero-left">
                    <div class="ez-pill-tag">🔥 Fast Middle-Mile Logistics System</div>
                    <h1 class="ez-hero-title">
                        The marketplace you <span>can actually trust.</span>
                    </h1>
                    <p class="ez-hero-desc">
                        EZiCart bridges local sellers, sorting center hubs, and courier fleets into one unified real-time delivery pipeline. Experience instant checkout, automated waybill creation, and multi-stage tracking.
                    </p>
                    <div class="ez-hero-cta">
                        <a href="{{ route('register') }}" class="ez-btn ez-btn-primary">Start Shopping Now</a>
                        <a href="{{ route('register.seller') }}" class="ez-btn ez-btn-outline">Open Seller Store</a>
                    </div>

                    <!-- Density Fill: Live Metrics Bar -->
                    <div class="ez-hero-stats">
                        <div class="ez-stat-item">
                            <span class="ez-stat-num">8+</span>
                            <span class="ez-stat-lbl">Major Categories</span>
                        </div>
                        <div class="ez-stat-item">
                            <span class="ez-stat-num">100%</span>
                            <span class="ez-stat-lbl">Verified Sellers</span>
                        </div>
                        <div class="ez-stat-item">
                            <span class="ez-stat-num">24/7</span>
                            <span class="ez-stat-lbl">Courier Operations</span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Interactive Graphic Card (Prevents Missing Image Errors) -->
                <div class="ez-hero-right">
                    <div class="ez-hero-banner-card">
                        <div class="ez-badge-live">🟢 Express Delivery Active</div>
                        <div class="ez-illustration-graphic">
                            🛴📦
                        </div>
                        <h3>Fast & Direct Fulfillment</h3>
                        <p>Orders are packaged by sellers, processed through local logistics sorting hubs, and dispatched directly via assigned couriers.</p>
                        
                        <div class="ez-mini-tracker">
                            <div class="ez-step active">1. Order Placed</div>
                            <div class="ez-step active">2. Seller Packed</div>
                            <div class="ez-step">3. Hub Sorted</div>
                            <div class="ez-step">4. Delivered</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Grid (Fills Edge-to-Edge) -->
    <section id="categories" class="ez-section">
        <div class="ez-container-fluid">
            <div class="ez-section-header">
                <p class="ez-section-subtitle">Comprehensive Catalog</p>
                <h2 class="ez-section-title">Explore Featured Categories</h2>
            </div>

            <div class="ez-categories-grid">
                <a href="#" class="ez-category-card">
                    <span class="ez-cat-icon">👕</span>
                    <span class="ez-cat-name">Men's Apparel</span>
                    <span class="ez-cat-count">120+ Products</span>
                </a>
                <a href="#" class="ez-category-card">
                    <span class="ez-cat-icon">👚</span>
                    <span class="ez-cat-name">Womens Apparel</span>
                    <span class="ez-cat-count">450+ Products</span>
                </a>
                <a href="#" class="ez-category-card">
                    <span class="ez-cat-icon">🧸</span>
                    <span class="ez-cat-name">Kids and Baby</span>
                    <span class="ez-cat-count">890+ Products</span>
                </a>
                <a href="#" class="ez-category-card">
                    <span class="ez-cat-icon">🐕</span>
                    <span class="ez-cat-name">Pet Supplies</span>
                    <span class="ez-cat-count">310+ Products</span>
                </a>
                <a href="#" class="ez-category-card">
                    <span class="ez-cat-icon">📱</span>
                    <span class="ez-cat-name">Electronics and Gadgets</span>
                    <span class="ez-cat-count">180+ Products</span>
                </a>
                <a href="#" class="ez-category-card">
                    <span class="ez-cat-icon">🏡</span>
                    <span class="ez-cat-name">Home and Garden</span>
                    <span class="ez-cat-count">240+ Products</span>
                </a>
                <a href="#" class="ez-category-card">
                    <span class="ez-cat-icon">✨</span>
                    <span class="ez-cat-name">Health and Beauty</span>
                    <span class="ez-cat-count">600+ Products</span>
                </a>
                <a href="#" class="ez-category-card">
                    <span class="ez-cat-icon">⚽</span>
                    <span class="ez-cat-name">Sports and Outdoors</span>
                    <span class="ez-cat-count">150+ Products</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Platform Role Badges (Eliminates empty white bottom space) -->
    <section class="ez-section ez-features-bg">
        <div class="ez-container-fluid">
            <div class="ez-section-header">
                <p class="ez-section-subtitle">Multi-Role Architecture</p>
                <h2 class="ez-section-title">Designed For The Entire Supply Chain</h2>
            </div>

            <div class="ez-features-grid">
                <div class="ez-feature-card">
                    <div class="ez-feature-icon">🛍️</div>
                    <h3 class="ez-feature-title">For Buyers</h3>
                    <p class="ez-feature-desc">Seamless cart management, discount voucher application, dispute resolution, and step-by-step order tracking.</p>
                </div>

                <div class="ez-feature-card">
                    <div class="ez-feature-icon">📦</div>
                    <h3 class="ez-feature-title">For Sellers</h3>
                    <p class="ez-feature-desc">Complete product inventory management, voucher generation, sales reporting, and automated waybill printing.</p>
                </div>

                <div class="ez-feature-card">
                    <div class="ez-feature-icon">🏬</div>
                    <h3 class="ez-feature-title">For Sorting Hubs</h3>
                    <p class="ez-feature-desc">Middle-mile package reception, precinct sorting, rider approvals, and delivery assignment management.</p>
                </div>

                <div class="ez-feature-card">
                    <div class="ez-feature-icon">🚚</div>
                    <h3 class="ez-feature-title">For Couriers</h3>
                    <p class="ez-feature-desc">Dedicated fulfillment workspace to claim pending pickup tasks, initiate transit, and complete last-mile deliveries.</p>
                </div>
            </div>
        </div>
    </section>
@endsection