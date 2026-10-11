@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth-fallback.css') }}">
@endpush

@section('title', 'Reset your password | EZiCart')

@section('content')
    <main class="auth-page">
        <section class="auth-shell auth-shell--verify" aria-labelledby="auth-title">
            <aside class="auth-aside"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a><div class="auth-aside-copy"><h1>Get back in safely.</h1><p>We’ll email a password reset link if an account matches the address you provide.</p></div></aside>
            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Reset your password</h2>
                <p class="auth-intro">Enter the email address associated with your EZiCart account.</p>

                @if (session('status'))
                    <div class="auth-message auth-message--success" role="status">{{ session('status') }}</div>
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
                    <button type="submit" class="auth-submit">Send reset link</button>
                </form>

                <p class="auth-switch"><a href="{{ route('login') }}">Back to sign in</a></p>
            </div>
        </section>
    </main>
@endsection
