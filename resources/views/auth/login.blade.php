@extends('layouts.app')

@section('content')
<div class="auth-wrapper">
    <div class="auth-card">
        
        <!-- Branding Hero Panel -->
        <div class="auth-branding">
            <span class="section-tag">EZiCart Marketplace</span>
            <h1 class="auth-brand-title">Shop easier.<br>Sell faster.</h1>
            <ul class="auth-feature-list">
                <li class="auth-feature-item">
                    <span class="auth-feature-icon">🛍️</span>
                    Leading marketplace platform for local finds
                </li>
                <li class="auth-feature-item">
                    <span class="auth-feature-icon">⚡</span>
                    Fast checkout & secure buyer protection
                </li>
                <li class="auth-feature-item">
                    <span class="auth-feature-icon">📦</span>
                    Seamless delivery tracking and updates
                </li>
            </ul>
        </div>

        <!-- Login Form Panel -->
        <div class="auth-form-container">
            <h2 class="auth-title">Log In</h2>

            @if(session('success'))
                <div class="form-notice" style="border-left-color: #10b981; background-color: #ecfdf5; color: #065f46;">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="name@example.com" value="{{ old('email') }}" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn-submit">Log In</button>
            </form>

            <div class="auth-divider">
                <span>OR</span>
            </div>

            <div class="social-auth">
                <a href="#" class="btn-social"><span>📘</span> Facebook</a>
                <a href="#" class="btn-social"><span>🔍</span> Google</a>
            </div>

            <p class="auth-footer-text">
                New to EZiCart? <a href="{{ route('register') }}">Sign Up</a>
            </p>
        </div>

    </div>
</div>
@endsection