@php
    $roleDashboardRoute = match (auth()->user()?->role) {
        'admin' => 'admin.dashboard',
        'courier' => 'courier.dashboard',
        'sorting_center' => 'logistics.dashboard',
        default => null,
    };
    $isRoleWorkspaceRoute = request()->routeIs('courier.*', 'logistics.*');
    $workspaceLabel = auth()->user()?->role === 'courier' ? 'Courier workspace' : 'Logistics workspace';
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
    @vite('resources/js/app.js')
    <script src="{{ asset('js/password-visibility.js') }}?v={{ filemtime(public_path('js/password-visibility.js')) }}" defer></script>
    <script src="{{ asset('js/registration-name-inputs.js') }}?v={{ filemtime(public_path('js/registration-name-inputs.js')) }}" defer></script>
    <script src="{{ asset('js/email-input-constraint.js') }}?v={{ filemtime(public_path('js/email-input-constraint.js')) }}" defer></script>
    <script src="{{ asset('js/psgc-address.js') }}?v={{ filemtime(public_path('js/psgc-address.js')) }}" defer></script>
    <script src="{{ asset('js/registration-wizard.js') }}?v={{ filemtime(public_path('js/registration-wizard.js')) }}" defer></script>
</head>

<body @class([
    'landing-page' => request()->routeIs('home'),
    'role-workspace' => $isRoleWorkspaceRoute,
])>

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
                        <form action="{{ route('home') }}" method="GET" class="search-form">
                            <input type="text" name="search" value="{{ request('search') }}"
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
                            <span>🛒 Cart</span>
                            @if (session('cart') && count(session('cart')) > 0)
                                <span class="cart-count-badge">
                                    {{ array_sum(array_column(session('cart'), 'quantity')) }}
                                </span>
                            @endif
                        </a>
                    @endif

                    @auth
                        @if (auth()->user()->role !== 'buyer')
                            <a href="{{ route('notifications.index') }}" class="nav-link">Notifications
                                @if (auth()->user()->unreadNotifications()->exists())
                                    <span class="cart-count-badge">{{ auth()->user()->unreadNotifications()->count() }}</span>
                                @endif
                            </a>
                            <a href="{{ route('account.profile.edit') }}" class="nav-link">Account</a>
                        @endif
                        @if (auth()->user()->role === 'seller')
                            <a href="{{ route('seller.dashboard') }}" class="btn-primary nav-btn-compact">Dashboard</a>
                        @elseif(auth()->user()->role === 'admin')
                            <a href="{{ route('admin.dashboard') }}" class="btn-primary nav-btn-compact">Admin Panel</a>
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
                            🚨
                        @elseif($announcement->type === 'warning')
                            ⚠️
                        @elseif($announcement->type === 'maintenance')
                            🛠️
                        @else
                            📢
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
    <main class="container main-content">
        @yield('content')
    </main>

    <!-- Footer -->
    @unless ($isRoleWorkspaceRoute)
        <footer class="footer">
            <div class="container footer-text">
                &copy; {{ date('Y') }} EZiCart Marketplace. All rights reserved.
            </div>
        </footer>
    @endunless

</body>

</html>
