@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endpush

@section('content')
<div class="dash-wrapper">
    <!-- Buyer Sidebar -->
    <aside class="dash-sidebar">
        <div>
            <div class="dash-profile-badge">
                <span class="dash-role-tag">Buyer Portal</span>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.5rem;">
                    <img src="{{ auth()->user()->profile_photo_url }}" alt="Profile" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover;">
                    <div>
                        <h2 class="dash-profile-title" style="margin: 0; font-size: 1rem;">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h2>
                        <p class="dash-profile-subtitle" style="margin: 0;">Member since {{ auth()->user()->created_at->format('M Y') }}</p>
                    </div>
                </div>
            </div>

            <nav class="dash-nav">
                <a href="{{ route('buyer.dashboard') }}" class="dash-nav-item">
                    📦 My Orders
                </a>
                <a href="{{ route('cart.index') }}" class="dash-nav-item">
                    🛒 My Cart
                </a>
                <a href="{{ route('buyer.profile') }}" class="dash-nav-item">
                    👤 My Profile
                </a>
                <a href="{{ route('buyer.addresses.index') }}" class="dash-nav-item">
                    📍 Delivery Addresses
                </a>
                <a href="{{ route('buyer.password') }}" class="dash-nav-item active">
                    🔒 Change Password
                </a>
            </nav>
        </div>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="dash-logout-btn">
                🚪 Logout
            </button>
        </form>
    </aside>

    <!-- Main Workspace -->
    <main class="dash-main">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Security & Password</h1>
                <p class="dash-subtitle">Update your account credentials to keep your profile secure.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="dash-alert-success">✅ {{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="dash-alert-success" style="border-left-color: var(--dash-danger); background-color: var(--dash-danger-bg); color: var(--dash-danger);">
                <ul style="margin: 0; padding-left: 1rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="dash-panel" style="max-width: 600px;">
            <div style="background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.85rem; color: var(--slate-600); line-height: 1.5;">
                🔒 <strong>Password Requirements:</strong>
                Ensure your password is at least 8 characters long and contains a mix of letters and numbers for maximum security.
            </div>

            <form action="{{ route('buyer.password.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">
                        Current Password *
                    </label>
                    <input type="password" name="current_password" class="form-control" required placeholder="Enter current password">
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">
                        New Password *
                    </label>
                    <input type="password" name="new_password" class="form-control" required placeholder="Minimum 8 characters">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">
                        Confirm New Password *
                    </label>
                    <input type="password" name="new_password_confirmation" class="form-control" required placeholder="Re-enter new password">
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="dash-btn-primary" style="padding: 0.65rem 1.75rem; font-size: 0.9rem;">
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>
@endsection

