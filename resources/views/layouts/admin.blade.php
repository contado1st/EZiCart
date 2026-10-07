<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin Governance Portal - EZiCart')</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/platform-controls.css') }}">

    <style>
        .admin-mobile-bar {
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
            .admin-mobile-bar {
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
    <div class="admin-mobile-bar">
        <button type="button" onclick="toggleAdminSidebar()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--slate-700);">
            ☰
        </button>
        <a href="{{ route('admin.dashboard') }}" class="ez-logo" style="font-size: 1.25rem;">
            EZ<span>i</span>Cart <span style="font-size: 0.75rem; background: #dc2626; color: white; padding: 2px 6px; border-radius: 4px; margin-left: 4px;">Admin</span>
        </a>
        <img src="{{ auth()->user()->profile_photo_url }}" alt="avatar" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
    </div>

    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleAdminSidebar()"></div>

    <div class="dash-wrapper">
        <!-- Dedicated Admin Sidebar Navigation -->
        <aside class="dash-sidebar" id="adminSidebar">
            <div>
                <!-- Brand / Portal Header -->
                <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--slate-200); margin-bottom: 1rem;">
                    <a href="{{ route('admin.dashboard') }}" class="ez-logo" style="font-size: 1.5rem; text-decoration: none;">
                        EZ<span>i</span>Cart
                    </a>
                </div>

                <!-- Admin Profile Badge -->
                <div class="dash-profile-badge" style="background: white; border: 1px solid var(--slate-200); border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <img src="{{ auth()->user()->profile_photo_url }}" alt="Admin Avatar" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid #dc2626;">
                        <div style="overflow: hidden;">
                            <span class="dash-role-tag" style="background: #fef2f2; color: #dc2626; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;">Super Admin</span>
                            <h2 class="dash-profile-title" style="margin: 0.2rem 0 0 0; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}
                            </h2>
                            <p class="dash-profile-subtitle" style="margin: 0; font-size: 0.75rem;">Platform Governance</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation Links -->
                <nav class="dash-nav">
                    <a href="{{ route('admin.dashboard') }}" class="dash-nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        📊 Platform Overview
                    </a>
                    <a href="{{ route('admin.registrations.index') }}" class="dash-nav-item {{ request()->routeIs('admin.registrations.*') ? 'active' : '' }}">
                        🛡️ User Registrations
                    </a>
                    <a href="{{ route('admin.moderation.index') }}" class="dash-nav-item {{ request()->routeIs('admin.moderation.*') ? 'active' : '' }}">
                        👥 User Accounts
                    </a>
                    <a href="{{ route('admin.products.index') }}" class="dash-nav-item {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                        📦 Product Moderation
                    </a>
                    <a href="{{ route('admin.disputes.index') }}" class="dash-nav-item {{ request()->routeIs('admin.disputes.*') ? 'active' : '' }}">
                        ⚖️ Complaints & Disputes
                    </a>
                    <a href="{{ route('admin.reports.index') }}" class="dash-nav-item {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                        📈 Financial & Commission
                    </a>
                    <a href="{{ route('admin.announcements.index') }}" class="dash-nav-item {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}">
                        📢 Bulletins & Announcements
                    </a>
                    <a href="{{ route('admin.policies') }}" class="dash-nav-item {{ request()->routeIs('admin.policies') ? 'active' : '' }}">
                        📜 Platform Policies
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
        function toggleAdminSidebar() {
            const sb = document.getElementById('adminSidebar');
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

