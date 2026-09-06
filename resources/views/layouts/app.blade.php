<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EziCart - A Better Marketplace</title>
    <!-- External CSS stylesheet -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
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
                <form action="#" method="GET" class="search-form">
                    <input 
                        type="text" 
                        name="q" 
                        placeholder="Search for products, shops and more" 
                        class="search-input"
                    >
                    <button type="submit" class="search-button">
                        Search
                    </button>
                </form>
            </div>

            <!-- Auth Buttons -->
            <div class="auth-nav">
                <a href="{{ route('login') }}" class="nav-link">Sign in</a>
                <a href="{{ route('register') }}" class="btn-primary">
                    Create account
                </a>
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
            &copy; {{ date('Y') }} EziCart Marketplace. All rights reserved.
        </div>
    </footer>

</body>
</html>