@php
    $roleDashboardRoute = match (auth()->user()?->role) {
        'admin', 'ecommerce_admin' => 'admin.dashboard',
        'logistics_admin' => 'logistics-admin.dashboard',
        'courier' => 'courier.dashboard',
        'sorting_center' => 'logistics.dashboard',
        default => null,
    };
    $isRoleWorkspaceRoute = request()->routeIs('courier.*', 'logistics.*');
    $workspaceLabel = auth()->user()?->role === 'courier' ? 'Courier workspace' : 'Logistics workspace';
    $isDashboardWorkspace = auth()->check() && request()->routeIs(
        'admin.*', 'seller.*', 'courier.*', 'logistics.*', 'logistics-admin.*',
        'buyer.dashboard', 'buyer.orders.*', 'buyer.addresses.*', 'finance.*', 'parcel-transfers.*',
        'account.profile.*', 'notifications.*'
    );
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'EZiCart - A Better Marketplace')</title>
    <meta name="description" content="@yield('meta-description', 'Shop available products from sellers on the EZiCart marketplace.')">
    <!-- Base Stylesheet -->
    @vite('resources/css/shared/app.css')
    <!-- Platform Bulletins & Banners Stylesheet -->
    @vite('resources/css/shared/platform-controls.css')
    <!-- Shared navigation style for projects running against an older Vite manifest -->
    <link rel="stylesheet"
        href="{{ asset('css/navigation-fallback.css') }}?v={{ filemtime(public_path('css/navigation-fallback.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/psgc-address.css') }}?v={{ filemtime(public_path('css/psgc-address.css')) }}">
    <!-- Dynamic Page Stylesheets -->
    @stack('styles')
    @if ($isDashboardWorkspace)
        <link rel="stylesheet" href="{{ asset('css/workspace.css') }}?v={{ filemtime(public_path('css/workspace.css')) }}">
        <script src="{{ asset('js/workspace.js') }}?v={{ filemtime(public_path('js/workspace.js')) }}" defer></script>
    @endif
    @vite('resources/js/app.js')
    <script src="{{ asset('js/password-visibility.js') }}?v={{ filemtime(public_path('js/password-visibility.js')) }}" defer></script>
    <script src="{{ asset('js/registration-name-inputs.js') }}?v={{ filemtime(public_path('js/registration-name-inputs.js')) }}" defer></script>
    <script src="{{ asset('js/email-input-constraint.js') }}?v={{ filemtime(public_path('js/email-input-constraint.js')) }}" defer></script>
    <script src="{{ asset('js/psgc-address.js') }}?v={{ filemtime(public_path('js/psgc-address.js')) }}" defer></script>
    <script src="{{ asset('js/registration-wizard.js') }}?v={{ filemtime(public_path('js/registration-wizard.js')) }}" defer></script>
</head>

<body @class([
    'landing-page' => request()->routeIs('home'),
    'catalog-search-page' => request()->routeIs('search.index'),
    'role-workspace' => $isRoleWorkspaceRoute,
    'dashboard-workspace' => $isDashboardWorkspace,
    'workspace-logistics' => $isDashboardWorkspace && in_array(auth()->user()?->role, ['courier', 'sorting_center', 'logistics_admin'], true),
])>

    @if ($isDashboardWorkspace)
        @include('components.workspace-topbar')
    @else
    <!-- Header / Navbar -->
    <header class="navbar{{ $isRoleWorkspaceRoute ? ' navbar--workspace' : '' }}">
            <div class="container navbar-container">

                <!-- Brand Logo -->
                <a href="{{ $roleDashboardRoute ? route($roleDashboardRoute) : route('home') }}" class="brand-logo">
                    <img src="{{ asset('images/ezicart-logo.png') }}" alt="EZiCart Logo" class="brand-logo-img">
                </a>

                @if ($isRoleWorkspaceRoute)
                    <p class="navbar-workspace-label">{{ $workspaceLabel }}</p>
                @elseif (!$roleDashboardRoute)
                    <div class="search-wrapper">
                        <form action="{{ route('search.index') }}" method="GET" class="search-form" role="search">
                            <input type="search" name="search" value="{{ request('search') }}" maxlength="150" aria-label="Search products and shops"
                                placeholder="Search for products, shops and more" class="search-input">
                            <button type="submit" class="search-button">
                                Search
                            </button>
                        </form>
                    </div>
                @endif

                <!-- Cart & Auth Navigation -->
                @if ($isRoleWorkspaceRoute)
                    <div class="auth-nav">
                        <a href="{{ route($roleDashboardRoute) }}" class="nav-link">
                            {{ auth()->user()->role === 'courier' ? 'Work board' : 'Overview' }}
                        </a>
                    </div>
                @else
                <div class="auth-nav">
                    <!-- Cart Link Visible Only to Guests and Buyers -->
                    @if (!auth()->check() || auth()->user()->role === 'buyer')
                        <a href="{{ route('cart.index') }}" class="nav-link cart-link">
                            <span><x-shop-icon name="cart2" /> Cart</span>
                            @if (session('cart') && count(session('cart')) > 0)
                                <span class="cart-count-badge">
                                    {{ count(session('cart')) }}
                                </span>
                            @endif
                        </a>
                    @endif

                    @auth
                        @if (auth()->user()->role !== 'buyer')
                            <a href="{{ route('notifications.index') }}" class="nav-link"><x-shop-icon name="bell" /> Notifications
                                @if (auth()->user()->unreadNotifications()->exists())
                                    <span class="cart-count-badge">{{ auth()->user()->unreadNotifications()->count() }}</span>
                                @endif
                            </a>
                            <a href="{{ route('account.profile.edit') }}" class="nav-link">Account</a>
                        @endif
                        @if (in_array(auth()->user()->role, ['buyer', 'seller', 'courier', 'admin', 'ecommerce_admin', 'logistics_admin'], true))
                            <a href="{{ route('finance.index') }}" class="nav-link">Financial records</a>
                        @endif
                        @if (auth()->user()->role === 'seller')
                            <a href="{{ route('seller.dashboard') }}" class="btn-primary nav-btn-compact">Dashboard</a>
                        @elseif(auth()->user()->isCommerceAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="btn-primary nav-btn-compact">Admin Panel</a>
                        @elseif(auth()->user()->isLogisticsAdmin())
                            <a href="{{ route('logistics-admin.dashboard') }}" class="btn-primary nav-btn-compact">Logistics control tower</a>
                        @elseif(auth()->user()->role === 'courier')
                            <a href="{{ route('courier.dashboard') }}" class="btn-primary nav-btn-compact">Deliveries</a>
                        @elseif(auth()->user()->role === 'sorting_center')
                            <a href="{{ route('logistics.dashboard') }}" class="btn-primary nav-btn-compact">Sorting Hub</a>
                        @else
                            <a href="{{ route('account.profile.edit') }}" class="btn-primary nav-btn-compact">My Account
                                @if (auth()->user()->unreadNotifications()->exists())
                                    <span class="cart-count-badge">{{ auth()->user()->unreadNotifications()->count() }}</span>
                                @endif
                            </a>
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
                @endif
            </div>
        </header>
    @endif

    <!-- Global Platform Announcements -->
    @php
        $activeAnnouncements = \App\Models\Announcement::activeForUser(auth()->user())
            ->latest()
            ->take(3)
            ->get();
    @endphp

    @if ($activeAnnouncements->isNotEmpty())
        <div class="{{ $isRoleWorkspaceRoute ? 'ops-announcements' : 'container u-extracted-dab43fb936' }}">
            @foreach ($activeAnnouncements as $announcement)
                <div class="announcement-banner banner-type-{{ $announcement->type }}">
                    <span class="u-extracted-888f29e5c3">
                        @if ($announcement->type === 'urgent')
                            <x-shop-icon name="exclamationtriangle" />
                        @elseif($announcement->type === 'warning')
                            <x-shop-icon name="warning" />
                        @elseif($announcement->type === 'maintenance')
                            <x-shop-icon name="settings" />
                        @else
                            <x-shop-icon name="megaphone" />
                        @endif
                    </span>
                    <div>
                        <strong>{{ $announcement->title }}:</strong> {{ $announcement->content }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Main Content Body -->
    @if ($isDashboardWorkspace)
        <div class="workspace-frame">
            @include('components.workspace-sidebar')
            <button type="button" class="workspace-overlay" data-workspace-close aria-label="Close navigation" tabindex="-1" hidden></button>
            <div class="workspace-page" id="workspace-content" tabindex="-1">
                @yield('content')
            </div>
        </div>
    @else
        <main class="container main-content">
            @yield('content')
        </main>
    @endif

    <!-- Footer -->
    @unless ($isRoleWorkspaceRoute || $isDashboardWorkspace)
        <footer class="footer">
            <div class="container footer-text">
                &copy; {{ date('Y') }} EZiCart Marketplace. All rights reserved.
            </div>
        </footer>
    @endunless

</body>

</html>
