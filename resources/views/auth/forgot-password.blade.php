@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth-fallback.css') }}">
@endpush

@section('title', 'Reset your password | EZiCart')

@section('content')
    <main class="auth-page">
        <section class="auth-shell auth-shell--verify" aria-labelledby="auth-title">
            <aside class="auth-aside"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a><div class="auth-aside-copy"><h1>Get back in safely.</h1><p>We’ll email a recovery code if an active account matches the address you provide.</p></div></aside>
            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Reset your password</h2>
                <p class="auth-intro">Enter the email address associated with your EZiCart account. We’ll send a one-time code to verify your identity before you choose a new password.</p>

                @if (session('status'))
                    <div class="auth-message auth-message--success" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="auth-message auth-message--error" role="alert">{{ session('error') }}</div>
                @endif

                <form action="{{ route('password.email') }}" method="POST" class="auth-form">
                    @csrf
                    <div class="auth-field">
                        <label for="email">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                        @error('email')
                            <span class="auth-field-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <button type="submit" class="auth-submit">Send recovery code</button>
                </form>

                <p class="auth-switch"><a href="{{ route('login') }}">Back to sign in</a></p>
            </div>
        </section>
    </main>
@endsection
