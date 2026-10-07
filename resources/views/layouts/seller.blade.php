<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Seller Portal - EZiCart')</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/platform-controls.css') }}">

    <style>
        .seller-mobile-bar {
            display: none;
            background: white;
            border-bottom: 1px solid var(--slate-200);
            padding: 0.75rem 1rem;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        @media (max-width: 900px) {
            .seller-mobile-bar {
                display: flex;
            }
            .dash-sidebar {
                position: fixed;
                top: 0;
                left: -280px;
                width: 260px;
                height: 100vh;
                z-index: 1000;
                transition: left 0.3s ease;
                box-shadow: 2px 0 15px rgba(0,0,0,0.15);
            }
            .dash-sidebar.open {
                left: 0;
            }
            .dash-main {
                padding: 1rem !important;
            }
            .sidebar-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            .sidebar-backdrop.active {
                display: block;
            }
        }
    </style>

    @stack('styles')
</head>
<body style="background-color: var(--slate-100); margin: 0; padding: 0;">

    <!-- Mobile Top Navigation Bar -->
    <div class="seller-mobile-bar">
        <button type="button" onclick="toggleSellerSidebar()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--slate-700);">
            ☰
        </button>
        <a href="{{ route('seller.dashboard') }}" class="ez-logo" style="font-size: 1.25rem;">
            EZ<span>i</span>Cart <span style="font-size: 0.75rem; background: var(--ez-primary); color: white; padding: 2px 6px; border-radius: 4px; margin-left: 4px;">Seller</span>
        </a>
        <img src="{{ auth()->user()->profile_photo_url }}" alt="avatar" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
    </div>

    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSellerSidebar()"></div>

    <div class="dash-wrapper">
        <!-- Dedicated Seller Sidebar Navigation -->
        <aside class="dash-sidebar" id="sellerSidebar">
            <div>
                <!-- Brand / Portal Header -->
                <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--slate-200); margin-bottom: 1rem;">
                    <a href="{{ route('seller.dashboard') }}" class="ez-logo" style="font-size: 1.5rem; text-decoration: none;">
                        EZ<span>i</span>Cart
                    </a>
                </div>

                <!-- Store Profile Header -->
                <div class="dash-profile-badge" style="background: white; border: 1px solid var(--slate-200); border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="{{ auth()->user()->profile_photo_url }}" alt="Store Logo" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid var(--ez-primary);">
                        <div style="overflow: hidden;">
                            <span class="dash-role-tag" style="background: #ecfdf5; color: #059669; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;">Seller Portal</span>
                            <h2 class="dash-profile-title" style="margin: 0.2rem 0 0 0; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                {{ auth()->user()->business_name ?? (auth()->user()->first_name . '\'s Store') }}
                            </h2>
                            <p class="dash-profile-subtitle" style="margin: 0; font-size: 0.75rem;">{{ auth()->user()->line_of_business ?? 'Verified Merchant' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Seller Navigation Links -->
                <nav class="dash-nav">
                    <a href="{{ route('seller.dashboard') }}" class="dash-nav-item {{ request()->routeIs('seller.dashboard') ? 'active' : '' }}">
                        📊 Dashboard Overview
                    </a>
                    <a href="{{ route('seller.products.index') }}" class="dash-nav-item {{ request()->routeIs('seller.products.*') ? 'active' : '' }}">
                        📦 Products / Inventory
                    </a>
                    <a href="{{ route('seller.orders.index') }}" class="dash-nav-item {{ request()->routeIs('seller.orders.*') ? 'active' : '' }}">
                        🛍️ Order Management
                    </a>
                    <a href="{{ route('seller.feedback') }}" class="dash-nav-item {{ request()->routeIs('seller.feedback') ? 'active' : '' }}">
                        ⭐ Customer Feedback
                    </a>
                    <a href="{{ route('seller.reports.index') }}" class="dash-nav-item {{ request()->routeIs('seller.reports.*') ? 'active' : '' }}">
                        📈 Financial Reports
                    </a>
                    <a href="{{ route('seller.vouchers.index') }}" class="dash-nav-item {{ request()->routeIs('seller.vouchers.*') ? 'active' : '' }}">
                        🎟️ Store Vouchers
                    </a>

                    <div style="font-size: 0.7rem; font-weight: 800; color: var(--slate-400); text-transform: uppercase; margin: 1rem 0 0.5rem 0.75rem; letter-spacing: 0.5px;">
                        Account Settings
                    </div>

                    <a href="{{ route('seller.profile') }}" class="dash-nav-item {{ request()->routeIs('seller.profile') ? 'active' : '' }}">
                        👤 Store & Profile
                    </a>
                    <a href="{{ route('seller.addresses.index') }}" class="dash-nav-item {{ request()->routeIs('seller.addresses.*') ? 'active' : '' }}">
                        📍 Warehouse Addresses
                    </a>
                    <a href="{{ route('seller.password') }}" class="dash-nav-item {{ request()->routeIs('seller.password') ? 'active' : '' }}">
                        🔒 Change Password
                    </a>
                </nav>
            </div>

            <!-- Logout Form -->
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="dash-logout-btn">
                    🚪 Logout
                </button>
            </form>
        </aside>

        <!-- Main Workspace -->
        <main class="dash-main">
            @yield('content')
        </main>
    </div>

    <script>
        function toggleSellerSidebar() {
            const sb = document.getElementById('sellerSidebar');
            const bd = document.getElementById('sidebarBackdrop');
            if (sb && bd) {
                sb.classList.toggle('open');
                bd.classList.toggle('active');
            }
        }
    </script>

    @stack('scripts')
</body>
</html>

