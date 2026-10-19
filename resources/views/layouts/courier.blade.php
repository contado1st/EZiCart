@extends('layouts.app')

@push('styles')
    @vite('resources/css/courier/courier.css')
@endpush

@section('content')
    <div class="ops-shell" data-ops-shell>
        <button class="ops-menu-toggle" type="button" data-ops-menu-toggle aria-expanded="false"
            aria-controls="courier-sidebar" aria-label="Open courier navigation">
            <span></span><span></span><span></span>
        </button>
        <button class="ops-mobile-overlay" type="button" data-ops-menu-overlay aria-label="Close courier navigation"
            tabindex="-1"></button>
        <aside class="ops-sidebar" id="courier-sidebar" aria-label="Courier workspace">
            <button class="ops-menu-close" type="button" data-ops-menu-close aria-label="Close navigation">
                <span></span><span></span>
            </button>
            <div>
                <div class="ops-brand">
                    <strong>EZiCart</strong>
                    <div class="ops-profile-card">
                        <span class="ops-role-tag">COURIER</span>
                        <span class="ops-profile-name">{{ auth()->user()->first_name }}
                            {{ auth()->user()->last_name }}</span>
                        <span class="ops-profile-detail">{{ auth()->user()->vehicle_type ?? 'Vehicle not set' }}</span>
                    </div>
                </div>
                <nav class="ops-nav" aria-label="Rider navigation">
                    <a class="{{ request()->routeIs('courier.dashboard') ? 'active' : '' }}"
                        aria-label="Work board" title="Work board" data-compact="OV"
                        aria-current="{{ request()->routeIs('courier.dashboard') ? 'page' : 'false' }}"
                        href="{{ route('courier.dashboard') }}">Work board</a>
                    <a href="{{ route('finance.index') }}" aria-label="Cash remittances" title="Cash remittances" data-compact="CO">Cash remittances</a>
                    <a class="{{ request()->routeIs('courier.tracking') ? 'active' : '' }}"
                        aria-label="Active parcels" title="Active parcels" data-compact="AC"
                        aria-current="{{ request()->routeIs('courier.tracking') ? 'page' : 'false' }}"
                        href="{{ route('courier.tracking') }}">Active parcels</a>
                    <a class="{{ request()->routeIs('courier.history') ? 'active' : '' }}"
                        aria-label="Delivery history" title="Delivery history" data-compact="HI"
                        aria-current="{{ request()->routeIs('courier.history') ? 'page' : 'false' }}"
                        href="{{ route('courier.history') }}">Delivery history</a>
                    <a class="{{ request()->routeIs('courier.earnings') ? 'active' : '' }}"
                        aria-label="Earnings" title="Earnings" data-compact="EA"
                        aria-current="{{ request()->routeIs('courier.earnings') ? 'page' : 'false' }}"
                        href="{{ route('courier.earnings') }}">Earnings</a>
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
                        aria-label="Account and profile" title="Account and profile" data-compact="PR"
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
