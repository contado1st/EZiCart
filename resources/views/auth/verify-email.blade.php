@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth-fallback.css') }}">
@endpush

@section('title', 'Continue to EZiCart | EZiCart')

@section('content')
    <main class="auth-page">
        <section class="auth-shell auth-shell--verify" aria-labelledby="auth-title">
            <aside class="auth-aside">
                <a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a>
                <div class="auth-aside-copy">
                    <p class="auth-kicker">Email saved</p>
                    <h1>You’re all set.</h1>
                    <p>You can continue without an email code for now.</p>
                </div>
            </aside>
            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Continue to EZiCart</h2>
                <p class="auth-intro">Your email address has been saved. Email verification is paused, so no code is needed.</p>

                @if (session('success'))<div class="auth-message auth-message--success" role="status">{{ session('success') }}</div>@endif
                @if (session('error'))<div class="auth-message auth-message--error" role="alert">{{ session('error') }}</div>@endif
                @if ($errors->any())<div class="auth-message auth-message--error" role="alert">{{ $errors->first() }}</div>@endif

                @if ($canSkip)
                    <div class="auth-field">
                        <label for="email">Email address</label>
                        <input id="email" type="email" value="{{ $email }}" autocomplete="email" readonly>
                    </div>
                    <form action="{{ route('verification.skip') }}" method="POST" class="auth-form">
                        @csrf
                        <button class="auth-submit" type="submit">Skip email verification and continue</button>
                    </form>
                @else
                    <p class="auth-help">To continue without a code, sign in with your password or register again.</p>
                @endif
                <p class="auth-switch"><a href="{{ route('login') }}">Back to sign in</a></p>
            </div>
        </section>
    </main>
@endsection
