<header class="ez-navbar">
    <div class="ez-container-fluid ez-nav-wrapper">
        
        <!-- Brand Logo -->
        <a href="{{ route('home') }}" class="ez-logo">
            EZ<span>i</span>Cart
        </a>

        <!-- Search Bar in Nav -->
        <div class="ez-nav-search">
            <form action="{{ route('home') }}" method="GET" class="ez-search-form">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}"
                    placeholder="Search for products, brands, or categories..."
                    aria-label="Search products"
                >
                <button type="submit">🔍 Search</button>
            </form>
        </div>

        <!-- Cart & Auth Controls -->
        <div class="ez-auth-btns">
            <!-- Cart Link Visible Only to Guests and Buyers -->
            @if(!auth()->check() || auth()->user()->role === 'buyer')
                <a href="{{ route('cart.index') }}" class="ez-nav-cart-link">
                    <span>🛒 Cart</span>
                    @if(session('cart') && count(session('cart')) > 0)
                        <span class="ez-cart-badge">
                            {{ array_sum(array_column(session('cart'), 'quantity')) }}
                        </span>
                    @endif
                </a>
            @endif

            @auth
                @if(auth()->user()->role === 'seller')
                    <a href="{{ route('seller.dashboard') }}" class="ez-btn ez-btn-primary">Dashboard</a>
                @elseif(auth()->user()->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="ez-btn ez-btn-primary">Admin Panel</a>
                @elseif(auth()->user()->role === 'courier')
                    <a href="{{ route('courier.dashboard') }}" class="ez-btn ez-btn-primary">Deliveries</a>
                @elseif(auth()->user()->role === 'sorting_center')
                    <a href="{{ route('logistics.dashboard') }}" class="ez-btn ez-btn-primary">Sorting Hub</a>
                @else
                    <a href="{{ route('buyer.dashboard') }}" class="ez-btn ez-btn-primary">My Account</a>
                @endif

                <form action="{{ route('logout') }}" method="POST" class="ez-logout-form">
                    @csrf
                    <button type="submit" class="ez-btn ez-btn-outline">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="ez-btn ez-btn-outline" style="border: none;">Log in</a>
                <a href="{{ route('register') }}" class="ez-btn ez-btn-primary">Sign up</a>
            @endauth
        </div>
    </div>
</header>