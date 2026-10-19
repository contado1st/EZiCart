@extends('layouts.app')

@push('styles')
    @vite('resources/css/logistics/logistics.css')
@endpush

@section('content')
    <div class="ops-shell" data-ops-shell>
        <button class="ops-menu-toggle" type="button" data-ops-menu-toggle aria-expanded="false"
            aria-controls="logistics-sidebar" aria-label="Open logistics navigation">
            <span></span><span></span><span></span>
        </button>
        <button class="ops-mobile-overlay" type="button" data-ops-menu-overlay aria-label="Close logistics navigation"
            tabindex="-1"></button>
        <aside class="ops-sidebar" id="logistics-sidebar" aria-label="Logistics workspace">
            <button class="ops-menu-close" type="button" data-ops-menu-close aria-label="Close navigation">
                <span></span><span></span>
            </button>
            <div>
                <div class="ops-brand">
                    <strong>EZiCart</strong>
                    <div class="ops-profile-card">
                        <span class="ops-role-tag">LOGISTICS</span>
                        <span class="ops-profile-name">{{ auth()->user()->business_name ?? 'Sorting center' }}</span>
                        <span class="ops-profile-detail">Operations workspace</span>
                    </div>
                </div>
                <nav class="ops-nav" aria-label="Logistics navigation">
                    <a class="{{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}"
                        aria-label="Overview" title="Overview" data-compact="OV"
                        aria-current="{{ request()->routeIs('logistics.dashboard') ? 'page' : 'false' }}"
                        href="{{ route('logistics.dashboard') }}">Overview</a>
                    <a class="{{ request()->routeIs('logistics.intake') ? 'active' : '' }}"
                        aria-label="Parcel intake" title="Parcel intake" data-compact="IN"
                        aria-current="{{ request()->routeIs('logistics.intake') ? 'page' : 'false' }}"
                        href="{{ route('logistics.intake') }}">Parcel intake</a>
                    <a href="{{ route('parcel-transfers.index') }}" aria-label="Hub transfers" title="Hub transfers" data-compact="TR">Hub transfers</a>
                    <a class="{{ request()->routeIs('logistics.pickupRequests') ? 'active' : '' }}"
                        aria-label="Pickup requests" title="Pickup requests" data-compact="PU"
                        aria-current="{{ request()->routeIs('logistics.pickupRequests') ? 'page' : 'false' }}"
                        href="{{ route('logistics.pickupRequests') }}">Pickup requests</a>
                    <a class="{{ request()->routeIs('logistics.sorting') ? 'active' : '' }}"
                        aria-label="Sorting queue" title="Sorting queue" data-compact="SO"
                        aria-current="{{ request()->routeIs('logistics.sorting') ? 'page' : 'false' }}"
                        href="{{ route('logistics.sorting') }}">Sorting queue</a>
                    <a class="{{ request()->routeIs('logistics.storage') ? 'active' : '' }}"
                        aria-label="Storage locations" title="Storage locations" data-compact="ST"
                        aria-current="{{ request()->routeIs('logistics.storage') ? 'page' : 'false' }}"
                        href="{{ route('logistics.storage') }}">Storage locations</a>
                    <a class="{{ request()->routeIs('logistics.scan-history') ? 'active' : '' }}"
                        aria-label="Scan history" title="Scan history" data-compact="SH"
                        aria-current="{{ request()->routeIs('logistics.scan-history') ? 'page' : 'false' }}"
                        href="{{ route('logistics.scan-history') }}">Scan history</a>
                    <a class="{{ request()->routeIs('logistics.dispatch') ? 'active' : '' }}"
                        aria-label="Rider dispatch" title="Rider dispatch" data-compact="DI"
                        aria-current="{{ request()->routeIs('logistics.dispatch') ? 'page' : 'false' }}"
                        href="{{ route('logistics.dispatch') }}">Rider dispatch</a>
                    <a class="{{ request()->routeIs('logistics.tracking') ? 'active' : '' }}"
                        aria-label="Delivery tracking" title="Delivery tracking" data-compact="TR"
                        aria-current="{{ request()->routeIs('logistics.tracking') ? 'page' : 'false' }}"
                        href="{{ route('logistics.tracking') }}">Delivery tracking</a>
                    <a class="{{ request()->routeIs('logistics.riders') ? 'active' : '' }}"
                        aria-label="Riders" title="Riders" data-compact="RI"
                        aria-current="{{ request()->routeIs('logistics.riders') ? 'page' : 'false' }}"
                        href="{{ route('logistics.riders') }}">Riders</a>
                    <a class="{{ request()->routeIs('logistics.areas*') ? 'active' : '' }}"
                        aria-label="Routing areas" title="Routing areas" data-compact="RA"
                        aria-current="{{ request()->routeIs('logistics.areas*') ? 'page' : 'false' }}"
                        href="{{ route('logistics.areas') }}">Routing areas</a>
                    <a class="{{ request()->routeIs('logistics.reports') ? 'active' : '' }}"
                        aria-label="Reports" title="Reports" data-compact="RE"
                        aria-current="{{ request()->routeIs('logistics.reports') ? 'page' : 'false' }}"
                        href="{{ route('logistics.reports') }}">Reports</a>
                    <span class="ops-nav-section">Account settings</span>
                    <a class="{{ request()->routeIs('notifications.*') ? 'active' : '' }}"
                        aria-label="Notifications" title="Notifications" data-compact="NO"
                        aria-current="{{ request()->routeIs('notifications.*') ? 'page' : 'false' }}"
                        href="{{ route('notifications.index') }}">
                        Notifications
                        @if (auth()->user()->unreadNotifications()->exists())
                            <span class="ops-nav-count">{{ auth()->user()->unreadNotifications()->count() }}</span>
                        @endif
                    </a>
                    <a class="{{ request()->routeIs('account.profile.*') ? 'active' : '' }}"
                        aria-label="Account and profile" title="Account and profile" data-compact="AC"
                        aria-current="{{ request()->routeIs('account.profile.*') ? 'page' : 'false' }}"
                        href="{{ route('account.profile.edit') }}">Account &amp; profile</a>
                </nav>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="ops-logout" type="submit">Sign out</button>
            </form>
        </aside>
        <main class="ops-main">
            @if (session('success'))
                <div class="ops-alert" role="status">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="ops-alert ops-alert--error" role="alert">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="ops-alert ops-alert--error" role="alert">{{ $errors->first() }}</div>
            @endif
            @yield('workspace')
        </main>
    </div>
@endsection
