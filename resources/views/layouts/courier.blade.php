@extends('layouts.app')

@push('styles')
    @vite('resources/css/courier/courier.css')
@endpush

@section('content')
    <div class="ops-shell">
        <aside class="ops-sidebar" aria-label="Courier workspace">
            <div>
                <div class="ops-brand">
                    <strong>EZiCart rider workspace</strong>
                    <span>{{ auth()->user()->first_name }} {{ auth()->user()->last_name }} · {{ auth()->user()->vehicle_type ?? 'Vehicle not set' }}</span>
                </div>
                <nav class="ops-nav" aria-label="Rider navigation">
                    <a class="{{ request()->routeIs('courier.dashboard') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('courier.dashboard') ? 'page' : 'false' }}"
                        href="{{ route('courier.dashboard') }}">Work board</a>
                    <a class="{{ request()->routeIs('courier.tracking') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('courier.tracking') ? 'page' : 'false' }}"
                        href="{{ route('courier.tracking') }}">Active parcels</a>
                    <a class="{{ request()->routeIs('courier.history') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('courier.history') ? 'page' : 'false' }}"
                        href="{{ route('courier.history') }}">Delivery history</a>
                    <a class="{{ request()->routeIs('courier.earnings') ? 'active' : '' }}"
                        aria-current="{{ request()->routeIs('courier.earnings') ? 'page' : 'false' }}"
                        href="{{ route('courier.earnings') }}">Earnings</a>
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
