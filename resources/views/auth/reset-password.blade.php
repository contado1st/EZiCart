@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth-fallback.css') }}">
@endpush

@section('title', 'Choose a new password | EZiCart')

@section('content')
    <main class="auth-page">
        <section class="auth-shell auth-shell--verify" aria-labelledby="auth-title">
            <aside class="auth-aside"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a><div class="auth-aside-copy"><h1>Choose a fresh password.</h1><p>Use a password you have not used on EZiCart before.</p></div></aside>
            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Choose a new password</h2>

                <form action="{{ route('password.update') }}" method="POST" class="auth-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="auth-field">
                        <label for="email">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">
                        @error('email')
                            <span class="auth-field-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="auth-field">
                        <label for="password">New password</label>
                        <div class="auth-password-wrap"><input id="password" type="password" name="password" required autocomplete="new-password"><button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button></div>
                        <small class="auth-help">At least 8 characters, with uppercase and lowercase letters, a number, and a symbol.</small>
                        @error('password')
                            <span class="auth-field-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="auth-field">
                        <label for="password-confirmation">Confirm new password</label>
                        <div class="auth-password-wrap"><input id="password-confirmation" type="password" name="password_confirmation" required autocomplete="new-password"><button class="auth-password-toggle" type="button" data-password-toggle="password-confirmation" aria-label="Show password" aria-pressed="false">Show</button></div>
                    </div>
                    <button type="submit" class="auth-submit">Reset password</button>
                </form>
            </div>
        </section>
    </main>
@endsection
