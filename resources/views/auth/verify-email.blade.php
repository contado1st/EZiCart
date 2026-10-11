@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth-fallback.css') }}">
@endpush

@section('title', 'Verify your account | EZiCart')

@section('content')
    <main class="auth-page">
        <section class="auth-shell auth-shell--verify" aria-labelledby="auth-title">
            <aside class="auth-aside"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a><div class="auth-aside-copy"><p class="auth-kicker">One quick check</p><h1>Confirm it’s you.</h1><p>Choose email or text message for your verification code.</p></div></aside>
            <div class="auth-content"><div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Verify your account</h2><p class="auth-intro">Choose where to receive your code, then enter it below. Codes expire in five minutes.</p>
                @if (session('success'))<div class="auth-message auth-message--success" role="status">{{ session('success') }}</div>@endif
                @if (session('status'))<div class="auth-message" role="status">{{ session('status') }}</div>@endif
                @if (session('error'))<div class="auth-message auth-message--error" role="alert">{{ session('error') }}</div>@endif
                @if ($errors->any())<div class="auth-message auth-message--error" role="alert">{{ $errors->first() }}</div>@endif
                <form action="{{ route('verification.resend') }}" method="POST" class="auth-form auth-resend-method">@csrf<input type="hidden" name="email" value="{{ old('email', $email) }}"><fieldset class="auth-section"><legend>Send code by</legend><label class="auth-check"><input type="radio" name="channel" value="email" @checked(old('channel', 'email') === 'email') required><span>Email (Gmail)</span></label><label class="auth-check"><input type="radio" name="channel" value="phone" @checked(old('channel') === 'phone')><span>Text message to your registered number</span></label></fieldset><button class="auth-submit" type="submit">Send code</button></form>

                <form action="{{ route('verification.verify') }}" method="POST" class="auth-form">
                    @csrf
                    <div class="auth-field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required>@error('email')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    <div class="auth-field"><label for="code">Six-digit code</label><input id="code" type="text" name="code" value="" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>@error('code')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    <button class="auth-submit" type="submit">Verify account</button>
                </form>
                <p class="auth-switch"><a href="{{ route('login') }}">Back to sign in</a></p>
            </div>
        </section>
    </main>
@endsection
