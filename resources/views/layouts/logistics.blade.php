@extends('layouts.app')

@push('styles')
    @vite('resources/css/logistics/logistics.css')
@endpush

@section('content')
    <div class="ops-shell">
        <aside class="ops-sidebar" aria-label="Logistics workspace">
            <div>
                <div class="ops-brand">
                    <strong>EZiCart operations</strong>
                    <span>{{ auth()->user()->business_name ?? 'Sorting center' }}</span>
                </div>
                <nav class="ops-nav" aria-label="Logistics navigation">
                    <a class="{{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('logistics.dashboard') ? 'page' : 'false' }}"
                        href="{{ route('logistics.dashboard') }}">Overview</a>
                    <a class="{{ request()->routeIs('logistics.intake') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('logistics.intake') ? 'page' : 'false' }}"
                        href="{{ route('logistics.intake') }}">Parcel intake</a>
                    <a class="{{ request()->routeIs('logistics.pickupRequests') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('logistics.pickupRequests') ? 'page' : 'false' }}"
                        href="{{ route('logistics.pickupRequests') }}">Pickup requests</a>
                    <a class="{{ request()->routeIs('logistics.sorting') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('logistics.sorting') ? 'page' : 'false' }}"
                        href="{{ route('logistics.sorting') }}">Sorting queue</a>
                    <a class="{{ request()->routeIs('logistics.storage') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('logistics.storage') ? 'page' : 'false' }}"
                        href="{{ route('logistics.storage') }}">Storage locations</a>
                    <a class="{{ request()->routeIs('logistics.scan-history') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('logistics.scan-history') ? 'page' : 'false' }}"
                        href="{{ route('logistics.scan-history') }}">Scan history</a>
                    <a class="{{ request()->routeIs('logistics.dispatch') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('logistics.dispatch') ? 'page' : 'false' }}"
                        href="{{ route('logistics.dispatch') }}">Rider dispatch</a>
                    <a class="{{ request()->routeIs('logistics.tracking') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('logistics.tracking') ? 'page' : 'false' }}"
                        href="{{ route('logistics.tracking') }}">Delivery tracking</a>
                    <a class="{{ request()->routeIs('logistics.riders') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('logistics.riders') ? 'page' : 'false' }}"
                        href="{{ route('logistics.riders') }}">Riders</a>
                    <a class="{{ request()->routeIs('logistics.areas*') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('logistics.areas*') ? 'page' : 'false' }}"
                        href="{{ route('logistics.areas') }}">Routing areas</a>
                    <a class="{{ request()->routeIs('logistics.reports') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('logistics.reports') ? 'page' : 'false' }}"
                        href="{{ route('logistics.reports') }}">Reports</a>
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
