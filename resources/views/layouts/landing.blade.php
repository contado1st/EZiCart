<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'EZiCart - Online Marketplace')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/ezicart-landing.css') }}">
    @stack('styles')
</head>
<body>

    <!-- Header Navigation -->
    <header class="ez-navbar">
        <div class="ez-container-fluid ez-nav-wrapper">
            <a href="{{ url('/') }}" class="ez-logo">
                EZ<span>i</span>Cart
            </a>

            <!-- High-Density Search Bar in Nav -->
            <div class="ez-nav-search">
                <input type="text" placeholder="Search for products, brands, or categories...">
                <button type="button">🔍 Search</button>
            </div>

            <ul class="ez-nav-links">
                <li><a href="{{ url('/') }}" class="{{ request()->is('/') ? 'active' : '' }}">Home</a></li>
                <li><a href="#categories">Categories</a></li>
                <li><a href="#featured">Featured</a></li>
                <li><a href="#sellers">Top Sellers</a></li>
            </ul>

            <div class="ez-auth-btns">
                @auth
                    @if(auth()->user()->role === 'seller')
                        <a href="{{ route('seller.dashboard') }}" class="ez-btn ez-btn-primary">Seller Center</a>
                    @else
                        <a href="{{ url('/home') }}" class="ez-btn ez-btn-primary">My Account</a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="ez-btn ez-btn-outline" style="border: none;">Log in</a>
                    <a href="{{ route('register') }}" class="ez-btn ez-btn-primary">Sign up</a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="ez-footer">
        <div class="ez-container-fluid">
            <div class="ez-footer-grid">
                <div class="ez-footer-brand">
                    <a href="{{ url('/') }}" class="ez-logo">EZ<span>i</span>Cart</a>
                    <p>Direct-to-consumer e-commerce infrastructure supporting local merchants, verified delivery couriers, and middle-mile logistics sorting hubs.</p>
                </div>
                <div>
                    <h4 class="ez-footer-title">Marketplace</h4>
                    <ul class="ez-footer-links">
                        <li><a href="#categories">All Categories</a></li>
                        <li><a href="#">Flash Sales</a></li>
                        <li><a href="#">Top Rated Items</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="ez-footer-title">Seller Hub</h4>
                    <ul class="ez-footer-links">
                        <li><a href="{{ route('register.seller') }}">Become a Seller</a></li>
                        <li><a href="#">Merchant Dashboard</a></li>
                        <li><a href="#">Order Fulfillment Policy</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="ez-footer-title">Logistics & Partners</h4>
                    <ul class="ez-footer-links">
                        <li><a href="{{ route('register.courier') }}">Apply as Courier Rider</a></li>
                        <li><a href="{{ route('register.sorting') }}">Sorting Hub Application</a></li>
                        <li><a href="#">Parcel Tracking System</a></li>
                    </ul>
                </div>
            </div>
            <div class="ez-footer-bottom">
                <p>&copy; {{ date('Y') }} EZiCart Platform. Built for CS/IT Web Development Systems.</p>
            </div>
        </div>
    </footer>

</body>
</html>