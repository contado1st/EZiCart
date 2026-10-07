<header class="ez-navbar">
    <div class="ez-container-fluid ez-nav-wrapper">
        
        <!-- Brand Logo -->
        <a href="{{ route('home') }}" class="ez-logo">
            EZ<span>i</span>Cart
        </a>

        <!-- Categories Dropdown Trigger -->
        <div class="ez-nav-dropdown-wrapper" style="position: relative;">
            <button type="button" class="ez-nav-cat-btn" onclick="toggleCategoriesMenu(event)" style="background: none; border: 1px solid var(--ez-border); padding: 0.5rem 0.9rem; border-radius: 50px; font-weight: 700; font-size: 0.85rem; color: var(--ez-dark); display: flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                <span>☰ Categories</span>
                <span style="font-size: 0.65rem;">▼</span>
            </button>
            <div id="ezCategoriesDropdown" style="display: none; position: absolute; top: calc(100% + 8px); left: 0; background: white; border: 1px solid var(--slate-200); border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 220px; z-index: 999; padding: 0.5rem 0;">
                <a href="{{ route('home') }}" style="display: block; padding: 0.5rem 1rem; color: var(--slate-700); text-decoration: none; font-size: 0.85rem; font-weight: 600;">✨ All Products</a>
                <div style="height: 1px; background: var(--slate-100); margin: 0.25rem 0;"></div>
                <a href="{{ route('home', ['category' => "Men's Apparel"]) }}" style="display: block; padding: 0.4rem 1rem; color: var(--slate-700); text-decoration: none; font-size: 0.825rem;">👕 Men's Apparel</a>
                <a href="{{ route('home', ['category' => "Women's Apparel"]) }}" style="display: block; padding: 0.4rem 1rem; color: var(--slate-700); text-decoration: none; font-size: 0.825rem;">👚 Women's Apparel</a>
                <a href="{{ route('home', ['category' => 'Electronics']) }}" style="display: block; padding: 0.4rem 1rem; color: var(--slate-700); text-decoration: none; font-size: 0.825rem;">📱 Electronics</a>
                <a href="{{ route('home', ['category' => 'Home and Garden']) }}" style="display: block; padding: 0.4rem 1rem; color: var(--slate-700); text-decoration: none; font-size: 0.825rem;">🏡 Home & Garden</a>
                <a href="{{ route('home', ['category' => 'Health and Beauty']) }}" style="display: block; padding: 0.4rem 1rem; color: var(--slate-700); text-decoration: none; font-size: 0.825rem;">✨ Health & Beauty</a>
                <a href="{{ route('home', ['category' => 'Kids and Baby']) }}" style="display: block; padding: 0.4rem 1rem; color: var(--slate-700); text-decoration: none; font-size: 0.825rem;">🧸 Kids & Baby</a>
                <a href="{{ route('home', ['category' => 'Pet Supplies']) }}" style="display: block; padding: 0.4rem 1rem; color: var(--slate-700); text-decoration: none; font-size: 0.825rem;">🐕 Pet Supplies</a>
                <a href="{{ route('home', ['category' => 'Sports and Outdoors']) }}" style="display: block; padding: 0.4rem 1rem; color: var(--slate-700); text-decoration: none; font-size: 0.825rem;">⚽ Sports & Outdoors</a>
            </div>
        </div>

        <!-- Search Bar in Nav -->
        <div class="ez-nav-search">
            <form action="{{ route('home') }}" method="GET" class="ez-search-form">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}"
                    placeholder="Search products, brands, or descriptions..."
                    aria-label="Search products"
                >
                <button type="submit">🔍 Search</button>
            </form>
        </div>

        <!-- Cart & Auth Controls -->
        <div class="ez-auth-btns">
            <!-- Cart Link Visible to Guests and Buyers -->
            @if(!auth()->check() || auth()->user()->role === 'buyer')
                <a href="{{ route('cart.index') }}" class="ez-nav-cart-link" title="My Shopping Cart">
                    <span>🛒 Cart</span>
                    @if(session('cart') && count(session('cart')) > 0)
                        <span class="ez-cart-badge">
                            {{ array_sum(array_column(session('cart'), 'quantity')) }}
                        </span>
                    @endif
                </a>
            @endif

            @auth
                @if(auth()->user()->role === 'buyer')
                    <!-- Buyer Orders Link -->
                    <a href="{{ route('buyer.dashboard') }}" class="nav-link" style="font-weight: 600; color: var(--slate-700); text-decoration: none; padding: 0.4rem 0.6rem;">
                        📦 Orders
                    </a>

                    <!-- Buyer Account Menu -->
                    <div style="position: relative;">
                        <button type="button" class="ez-btn ez-btn-outline" onclick="toggleAccountMenu(event)" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.85rem; border-radius: 50px; font-size: 0.85rem;">
                            <img src="{{ auth()->user()->profile_photo_url }}" alt="avatar" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover;">
                            <span>{{ auth()->user()->first_name }}</span>
                            <span style="font-size: 0.65rem;">▼</span>
                        </button>
                        <div id="ezAccountDropdown" style="display: none; position: absolute; right: 0; top: calc(100% + 8px); background: white; border: 1px solid var(--slate-200); border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 190px; z-index: 999; padding: 0.5rem 0;">
                            <div style="padding: 0.5rem 1rem; border-bottom: 1px solid var(--slate-100); font-size: 0.75rem; color: var(--slate-500);">
                                Signed in as<br><strong style="color: var(--slate-800); font-size: 0.8rem;">{{ auth()->user()->email }}</strong>
                            </div>
                            <a href="{{ route('buyer.profile') }}" style="display: block; padding: 0.5rem 1rem; color: var(--slate-700); text-decoration: none; font-size: 0.85rem; font-weight: 600;">👤 Profile</a>
                            <a href="{{ route('buyer.addresses.index') }}" style="display: block; padding: 0.5rem 1rem; color: var(--slate-700); text-decoration: none; font-size: 0.85rem; font-weight: 600;">📍 My Addresses</a>
                            <a href="{{ route('buyer.dashboard') }}" style="display: block; padding: 0.5rem 1rem; color: var(--slate-700); text-decoration: none; font-size: 0.85rem; font-weight: 600;">📦 My Orders</a>
                            <a href="{{ route('buyer.password') }}" style="display: block; padding: 0.5rem 1rem; color: var(--slate-700); text-decoration: none; font-size: 0.85rem; font-weight: 600;">🔒 Change Password</a>
                            <div style="height: 1px; background: var(--slate-100); margin: 0.25rem 0;"></div>
                            <form action="{{ route('logout') }}" method="POST" style="margin: 0; padding: 0;">
                                @csrf
                                <button type="submit" style="width: 100%; text-align: left; background: none; border: none; padding: 0.5rem 1rem; color: #dc2626; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                                    🚪 Logout
                                </button>
                            </form>
                        </div>
                    </div>
                @elseif(auth()->user()->role === 'seller')
                    <a href="{{ route('seller.dashboard') }}" class="ez-btn ez-btn-primary">Seller Portal</a>
                    <form action="{{ route('logout') }}" method="POST" class="ez-logout-form">
                        @csrf
                        <button type="submit" class="ez-btn ez-btn-outline">Logout</button>
                    </form>
                @elseif(auth()->user()->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="ez-btn ez-btn-primary">Admin Panel</a>
                    <form action="{{ route('logout') }}" method="POST" class="ez-logout-form">
                        @csrf
                        <button type="submit" class="ez-btn ez-btn-outline">Logout</button>
                    </form>
                @elseif(auth()->user()->role === 'courier')
                    <a href="{{ route('courier.dashboard') }}" class="ez-btn ez-btn-primary">Deliveries</a>
                    <form action="{{ route('logout') }}" method="POST" class="ez-logout-form">
                        @csrf
                        <button type="submit" class="ez-btn ez-btn-outline">Logout</button>
                    </form>
                @elseif(auth()->user()->role === 'sorting_center')
                    <a href="{{ route('logistics.dashboard') }}" class="ez-btn ez-btn-primary">Sorting Hub</a>
                    <form action="{{ route('logout') }}" method="POST" class="ez-logout-form">
                        @csrf
                        <button type="submit" class="ez-btn ez-btn-outline">Logout</button>
                    </form>
                @endif
            @else
                <a href="{{ route('login') }}" class="ez-btn ez-btn-outline" style="border: none;">Log in</a>
                <a href="{{ route('register') }}" class="ez-btn ez-btn-primary">Sign up</a>
            @endauth
        </div>
    </div>
</header>

<script>
    function toggleCategoriesMenu(e) {
        e.stopPropagation();
        const drop = document.getElementById('ezCategoriesDropdown');
        const acct = document.getElementById('ezAccountDropdown');
        if (acct) acct.style.display = 'none';
        drop.style.display = drop.style.display === 'block' ? 'none' : 'block';
    }

    function toggleAccountMenu(e) {
        e.stopPropagation();
        const drop = document.getElementById('ezAccountDropdown');
        const cats = document.getElementById('ezCategoriesDropdown');
        if (cats) cats.style.display = 'none';
        drop.style.display = drop.style.display === 'block' ? 'none' : 'block';
    }

    document.addEventListener('click', () => {
        const cats = document.getElementById('ezCategoriesDropdown');
        const acct = document.getElementById('ezAccountDropdown');
        if (cats) cats.style.display = 'none';
        if (acct) acct.style.display = 'none';
    });
</script>