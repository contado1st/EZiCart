@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth-fallback.css') }}">
@endpush

@section('title', 'Verify Your Email | EZiCart')

@section('content')
    @php($isLoginOtp = ($verificationMode ?? 'registration') === 'login')
    <main class="auth-page">
        <section class="auth-shell auth-shell--verify" aria-labelledby="auth-title">
            <aside class="auth-aside">
                <a class="auth-brand" href="{{ route('home') }}" aria-label="EZiCart home">EZiCart<span>.</span></a>
                <div class="auth-aside-copy">
                    <p class="auth-kicker">Account Security</p>
                    <h1>One last step.</h1>
                    <p>{{ $isLoginOtp ? 'Confirm it is you before we open your account.' : 'Verify your email address to protect your account and start using EZiCart.' }}</p>
                </div>
                <p class="auth-aside-foot">Philippine commerce, verified and secured.</p>
            </aside>

            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">{{ $isLoginOtp ? 'Verify Your Sign In' : 'Verify Your Email' }}</h2>
                <p class="auth-intro">
                    {{ $isLoginOtp ? 'We sent a 6-digit sign-in code to' : "We've sent a 6-digit verification code to" }}
                    <span class="auth-target-email">{{ $email ?: 'your registered email address' }}</span>.
                    {{ $isLoginOtp ? 'Enter it to finish signing in.' : 'Enter the code below to complete verification.' }}
                </p>

                {{-- Status & Error Messages --}}
                @if (session('success'))
                    <div class="auth-message auth-message--success" role="status">{{ session('success') }}</div>
                @endif
                @if (session('status'))
                    <div class="auth-message auth-message--success" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="auth-message auth-message--error" role="alert">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="auth-message auth-message--error" role="alert">
                        <strong>Verification notice:</strong>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                {{-- OTP Verification Form --}}
                <form action="{{ $isLoginOtp ? route('login.otp.verify') : route('verification.verify') }}" method="POST" class="auth-form" id="verify-form" novalidate>
                    @csrf
                    
                    @if ($email && ! $isLoginOtp)
                        <input type="hidden" name="email" id="verify-email" value="{{ $email }}">
                    @elseif (! $email)
                        <div class="auth-field">
                            <label for="input-email">Registered email address</label>
                            <input id="input-email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="name@example.com">
                            @error('email')<span class="auth-field-error">{{ $message }}</span>@enderror
                        </div>
                    @endif

                    <div class="auth-field">
                        <label for="otp-code">6-Digit Verification Code</label>
                        <input
                            id="otp-code"
                            type="text"
                            name="code"
                            class="auth-otp-input"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            autocomplete="one-time-code"
                            placeholder="••••••"
                            value="{{ old('code') }}"
                            required
                            @if ($email) autofocus @endif
                        >
                        <small class="auth-help">Enter the 6 numbers sent to your email. Expires in 5 minutes.</small>
                        @error('code')<span class="auth-field-error">{{ $message }}</span>@enderror
                    </div>

                    <button class="auth-submit" type="submit" id="verify-btn">
                        <span id="verify-btn-text">Verify OTP</span>
                    </button>
                </form>

                {{-- Resend OTP Section with Cooldown Timer --}}
                <div class="auth-resend">
                    <form action="{{ $isLoginOtp ? route('login.otp.resend') : route('verification.resend') }}" method="POST" id="resend-form">
                        @csrf
                        @unless ($isLoginOtp)
                            <input type="hidden" name="email" id="resend-email" value="{{ $email }}">
                        @endunless
                        <p class="auth-help" style="margin-bottom: 0.5rem;">Didn’t receive the code? Check your spam folder or resend.</p>
                        <button type="submit" id="resend-btn" @disabled($cooldownSeconds > 0)>
                            <span id="resend-btn-text">
                                @if ($cooldownSeconds > 0)
                                    Resend OTP in <span id="cooldown-timer">{{ $cooldownSeconds }}</span>s
                                @else
                                    Resend OTP
                                @endif
                            </span>
                        </button>
                    </form>
                </div>

                <p class="auth-switch">{{ $isLoginOtp ? 'Need to start over?' : 'Already verified or need another account?' }} <a href="{{ route('login') }}">Back to sign in</a></p>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var cooldownSeconds = {{ (int) $cooldownSeconds }};
            var timerSpan = document.getElementById('cooldown-timer');
            var resendBtn = document.getElementById('resend-btn');
            var resendText = document.getElementById('resend-btn-text');
            var verifyForm = document.getElementById('verify-form');
            var verifyBtn = document.getElementById('verify-btn');
            var verifyBtnText = document.getElementById('verify-btn-text');
            var resendForm = document.getElementById('resend-form');
            var otpInput = document.getElementById('otp-code');
            var inputEmail = document.getElementById('input-email');
            var resendEmail = document.getElementById('resend-email');

            // Synchronize email if entered manually
            if (inputEmail && resendEmail) {
                inputEmail.addEventListener('input', function () {
                    resendEmail.value = inputEmail.value.trim();
                });
            }

            // Sanitize OTP input to digits only
            if (otpInput) {
                otpInput.addEventListener('input', function () {
                    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
                });
            }

            // Cooldown countdown timer
            if (cooldownSeconds > 0 && resendBtn && timerSpan) {
                var interval = setInterval(function () {
                    cooldownSeconds--;
                    if (cooldownSeconds <= 0) {
                        clearInterval(interval);
                        resendBtn.disabled = false;
                        resendText.textContent = 'Resend OTP';
                    } else {
                        timerSpan.textContent = cooldownSeconds;
                    }
                }, 1000);
            }

            // Loading state on verification submit
            if (verifyForm && verifyBtn) {
                verifyForm.addEventListener('submit', function () {
                    if (otpInput && otpInput.value.trim().length === 6) {
                        verifyBtn.disabled = true;
                        verifyBtnText.textContent = 'Verifying OTP…';
                    }
                });
            }

            // Loading state on resend submit
            if (resendForm && resendBtn) {
                resendForm.addEventListener('submit', function () {
                    resendBtn.disabled = true;
                    resendText.textContent = 'Sending new OTP…';
                });
            }
        });
    </script>
@endsection
