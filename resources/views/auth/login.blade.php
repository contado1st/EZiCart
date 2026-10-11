@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth-fallback.css') }}">
@endpush

@section('title', 'Sign in | EZiCart')

@section('content')
    <main class="auth-page">
        <section class="auth-shell" aria-labelledby="auth-title">
            <aside class="auth-aside">
                <a class="auth-brand" href="{{ route('home') }}" aria-label="EZiCart home">EZiCart<span>.</span></a>
                <div class="auth-aside-copy">
                    <p class="auth-kicker">Your marketplace, in one place</p>
                    <h1>Good finds start here.</h1>
                    <p>Sign in to continue shopping, manage your store, or pick up where you left off.</p>
                </div>
                <p class="auth-aside-foot">Made for local shopping in the Philippines.</p>
            </aside>

            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Sign in to EZiCart</h2>
                <p class="auth-intro">Use the email address and password for your account.</p>

                @if (session('success'))
                    <div class="auth-message auth-message--success" role="status">{{ session('success') }}</div>
                @endif
                @if (session('status'))
                    <div class="auth-message" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="auth-message auth-message--error" role="alert">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="auth-message auth-message--error" role="alert">{{ $errors->first() }}</div>
                @endif

                <form action="{{ route('login.post') }}" method="POST" class="auth-form">
                    @csrf
                    <div class="auth-field">
                        <label for="email">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                        @error('email')<span class="auth-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="auth-field">
                        <div class="auth-label-row"><label for="password">Password</label><a href="{{ route('password.request') }}">Forgot password?</a></div>
                        <div class="auth-password-wrap">
                            <input id="password" type="password" name="password" autocomplete="current-password" required>
                            <button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button>
                        </div>
                        @error('password')<span class="auth-field-error">{{ $message }}</span>@enderror
                    </div>
                    <label class="auth-check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Keep me signed in on this device</span></label>
                    <button class="auth-submit" type="submit">Sign in</button>
                </form>
                <p class="auth-switch">New to EZiCart? <a href="{{ route('register') }}">Create a buyer account</a></p>
            </div>
        </section>
    </main>
@endsection
