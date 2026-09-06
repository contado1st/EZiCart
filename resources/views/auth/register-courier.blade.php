@extends('layouts.app')

@section('content')
<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 950px;">
        
        <div class="auth-branding">
            <span class="section-tag">Courier Registration</span>
            <h1 class="auth-brand-title">Deliver with EZiCart</h1>
            <p class="hero-text">Become an official logistics partner and manage neighborhood deliveries.</p>
        </div>

        <div class="auth-form-container">
            <div class="role-selector">
                <a href="{{ route('register') }}" class="role-tab">🛍️ Buyer</a>
                <a href="{{ route('register.seller') }}" class="role-tab">🏪 Seller</a>
                <a href="{{ route('register.courier') }}" class="role-tab active">🚚 Courier</a>
            </div>

            <h2 class="auth-title">Sign Up as Courier</h2>

            <div class="form-notice" style="border-left-color: #3b82f6; background-color: #eff6ff; color: #1e40af;">
                🚚 Courier registration setup is up next! We will build vehicle selection, plate numbers, and OR/CR document uploads shortly.
            </div>

            <p class="auth-footer-text">
                Need help? <a href="{{ route('login') }}">Back to Login</a>
            </p>
        </div>

    </div>
</div>
@endsection