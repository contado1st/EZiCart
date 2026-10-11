@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth-fallback.css') }}">
@endpush

@section('title', 'Verify your email | EZiCart')

@section('content')
    <main class="auth-page">
        <section class="auth-shell auth-shell--verify" aria-labelledby="auth-title">
            <aside class="auth-aside"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a><div class="auth-aside-copy"><p class="auth-kicker">One quick check</p><h1>Confirm it’s you.</h1><p>We sent a short verification code to the email address you used to register.</p></div></aside>
            <div class="auth-content"><div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Verify your email</h2><p class="auth-intro">Enter the six-digit code. It expires five minutes after it was sent.</p>
                @if (session('success'))<div class="auth-message auth-message--success" role="status">{{ session('success') }}</div>@endif
                @if (session('status'))<div class="auth-message" role="status">{{ session('status') }}</div>@endif
                @if (session('error'))<div class="auth-message auth-message--error" role="alert">{{ session('error') }}</div>@endif
                @if ($errors->any())<div class="auth-message auth-message--error" role="alert">{{ $errors->first() }}</div>@endif
                <form action="{{ route('verification.verify') }}" method="POST" class="auth-form">
                    @csrf
                    <div class="auth-field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required>@error('email')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    <div class="auth-field"><label for="code">Six-digit code</label><input id="code" type="text" name="code" value="" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>@error('code')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    <button class="auth-submit" type="submit">Verify email</button>
                </form>
                <form action="{{ route('verification.resend') }}" method="POST" class="auth-resend">@csrf<input type="hidden" name="email" value="{{ old('email', $email) }}"><button type="submit">Send a new code</button></form>
                <p class="auth-switch"><a href="{{ route('login') }}">Back to sign in</a></p>
            </div>
        </section>
    </main>
@endsection
