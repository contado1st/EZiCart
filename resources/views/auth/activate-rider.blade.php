@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth-fallback.css') }}">
@endpush

@section('title', 'Activate rider account | EZiCart')

@section('content')
    <main class="auth-page">
        <section class="auth-shell auth-shell--verify" aria-labelledby="auth-title">
            <aside class="auth-aside"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a><div class="auth-aside-copy"><h1>Set up your rider access.</h1><p>Choose a password to activate the account added by your logistics company.</p></div></aside>
            <div class="auth-content"><div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Activate your rider account</h2><p class="auth-intro">This link can be used once and expires 24 hours after it was sent.</p>
                @if ($activationError)
                    <div class="auth-message auth-message--error" role="alert">{{ $activationError }}</div>
                @else
                @if ($errors->any())<div class="auth-message auth-message--error" role="alert">{{ $errors->first() }}</div>@endif
                <form action="{{ route('rider.activation.store') }}" method="POST" class="auth-form" data-auth-submit>
                    @csrf<input type="hidden" name="token" value="{{ $token }}">
                    <div class="auth-field"><label for="password">Password</label><div class="auth-password-wrap"><input id="password" type="password" name="password" autocomplete="new-password" aria-describedby="password-hint" minlength="8" maxlength="128" required><button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button></div><small id="password-hint" class="auth-help">8–128 characters, with uppercase and lowercase letters, a number, and a symbol.</small>@error('password')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    <div class="auth-field"><label for="password_confirmation">Confirm password</label><div class="auth-password-wrap"><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" maxlength="128" required><button class="auth-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">Show</button></div></div>
                    <button class="auth-submit" type="submit">Activate account</button>
                </form>
                @endif
                <p class="auth-switch"><a href="{{ route('login') }}">Back to sign in</a></p>
            </div>
        </section>
    </main>
@endsection
