<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EZiCart - A Better Marketplace</title>
    <!-- Base Stylesheet -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <!-- Dynamic Page Stylesheets -->
    @stack('styles')
</head>
<body>

    <!-- Header / Navbar -->
    <header class="navbar">
        <div class="container navbar-container">
            
            <!-- Brand Logo -->
            <a href="{{ route('home') }}" class="brand-logo">
                <img src="{{ asset('images/ezicart-logo.png') }}" alt="EZiCart Logo" class="brand-logo-img">
            </a>

            <!-- Search Bar -->
            <div class="search-wrapper">
                <form action="{{ route('home') }}" method="GET" class="search-form">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}"
                        placeholder="Search for products, shops and more" 
                        class="search-input"
                    >
                    <button type="submit" class="search-button">
                        Search
                    </button>
                </form>
            </div>

            <!-- Cart & Auth Navigation -->
            <div class="auth-nav">
                <!-- Cart Link Visible Only to Guests and Buyers -->
                @if(!auth()->check() || auth()->user()->role === 'buyer')
                    <a href="{{ route('cart.index') }}" class="nav-link cart-link">
                        <span>🛒 Cart</span>
                        @if(session('cart') && count(session('cart')) > 0)
                            <span class="cart-count-badge">
                                {{ array_sum(array_column(session('cart'), 'quantity')) }}
                            </span>
                        @endif
                    </a>
                @endif

                @auth
                    @if(auth()->user()->role === 'seller')
                        <a href="{{ route('seller.dashboard') }}" class="btn-primary nav-btn-compact">Dashboard</a>
                    @elseif(auth()->user()->role === 'admin')
                        <a href="{{ route('admin.dashboard') }}" class="btn-primary nav-btn-compact">Admin Panel</a>
                    @elseif(auth()->user()->role === 'courier')
                        <a href="{{ route('courier.dashboard') }}" class="btn-primary nav-btn-compact">Deliveries</a>
                    @elseif(auth()->user()->role === 'sorting_center')
                        <a href="{{ route('logistics.dashboard') }}" class="btn-primary nav-btn-compact">Sorting Hub</a>
                    @else
                        <a href="{{ route('buyer.dashboard') }}" class="btn-primary nav-btn-compact">My Account</a>
                    @endif

                    <form action="{{ route('logout') }}" method="POST" class="logout-form-inline">
                        @csrf
                        <button type="submit" class="nav-link btn-logout">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="nav-link">Login</a>
                    <a href="{{ route('register') }}" class="btn-primary">Register</a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Main Content Body -->
    <main class="container main-content">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container footer-text">
            &copy; {{ date('Y') }} EZiCart Marketplace. All rights reserved.
        </div>
    </footer>

</body>
</html>