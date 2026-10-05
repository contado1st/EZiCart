@extends('layouts.app')

@push('styles')
    @vite('resources/css/logistics/logistics.css')
@endpush

@section('content')
    <div class="ops-shell">
        <aside class="ops-sidebar">
            <div>
                <div class="ops-brand"><strong>EZiCart
                        operations</strong><span>{{ auth()->user()->business_name ?? 'Sorting center' }}</span></div>
                <nav class="ops-nav" aria-label="Logistics navigation">
                    <a class="{{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}"
                        href="{{ route('logistics.dashboard') }}">Overview</a>
                    <a class="{{ request()->routeIs('logistics.intake') ? 'active' : '' }}"
                        href="{{ route('logistics.intake') }}">Parcel intake</a>
                    <a class="{{ request()->routeIs('logistics.pickupRequests') ? 'active' : '' }}"
                        href="{{ route('logistics.pickupRequests') }}">Pickup requests</a>
                    <a class="{{ request()->routeIs('logistics.sorting') ? 'active' : '' }}"
                        href="{{ route('logistics.sorting') }}">Sorting queue</a>
                    <a class="{{ request()->routeIs('logistics.dispatch') ? 'active' : '' }}"
                        href="{{ route('logistics.dispatch') }}">Rider dispatch</a>
                    <a class="{{ request()->routeIs('logistics.tracking') ? 'active' : '' }}"
                        href="{{ route('logistics.tracking') }}">Delivery tracking</a>
                    <a class="{{ request()->routeIs('logistics.riders') ? 'active' : '' }}"
                        href="{{ route('logistics.riders') }}">Riders</a>
                    <a class="{{ request()->routeIs('logistics.areas*') ? 'active' : '' }}"
                        href="{{ route('logistics.areas') }}">Routing areas</a>
                    <a class="{{ request()->routeIs('logistics.reports') ? 'active' : '' }}"
                        href="{{ route('logistics.reports') }}">Reports</a>
                </nav>
            </div>
            <form action="{{ route('logout') }}" method="POST">@csrf<button class="ops-logout" type="submit">Sign
                    out</button></form>
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
