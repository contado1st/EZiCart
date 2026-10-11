@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth-fallback.css') }}">
@endpush

@section('title', 'Create an account | EZiCart')

@section('content')
    <main class="auth-page">
        <section class="auth-shell auth-shell--form" aria-labelledby="auth-title">
            <aside class="auth-aside">
                <a class="auth-brand" href="{{ route('home') }}" aria-label="EZiCart home">EZiCart<span>.</span></a>
                <div class="auth-aside-copy">
                    <p class="auth-kicker">Shopping starts with a simple account</p>
                    <h1>Keep your finds close.</h1>
                    <p>Track orders and save the details you need for your next purchase.</p>
                </div>
                <p class="auth-aside-foot">Add your delivery address now, and update it later in account settings.</p>
            </aside>
            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Create your buyer account</h2>
                <p class="auth-intro">Create an account to shop and track orders.</p>
                <nav class="auth-role-nav" aria-label="Choose account type">
                    <a class="is-active" aria-current="page" href="{{ route('register') }}">Buyer</a>
                    <a href="{{ route('register.seller') }}">Seller</a>
                    <a href="{{ route('register.sorting') }}">Logistics company</a>
                </nav>
                <div class="registration-progress" data-registration-progress aria-live="polite">
                    <div class="registration-progress-copy"><span data-step-count>Step 1</span><strong data-step-title></strong></div>
                    <div class="registration-progress-track" role="progressbar" aria-label="Registration progress" aria-valuemin="1" aria-valuemax="3" aria-valuenow="1"><span data-progress-fill></span></div>
                </div>

                @if ($errors->any())
                    <div class="auth-message auth-message--error" role="alert"><strong>Review the highlighted fields.</strong><span>{{ $errors->first() }}</span></div>
                @endif

                <form action="{{ route('register.post') }}" method="POST" class="auth-form auth-form--registration" data-auth-submit data-registration-wizard novalidate>
                    @csrf
                    <section class="registration-step" data-registration-step data-step-label="Your details"><div class="auth-grid">
                        <div class="auth-field"><label for="first_name">First name</label><input id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" maxlength="50" pattern="[\p{L}\p{M} ]{2,50}" data-name-only required>@error('first_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="last_name">Last name</label><input id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" maxlength="50" pattern="[\p{L}\p{M} ]{2,50}" data-name-only required>@error('last_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="middle_name">Middle name <span>(optional)</span></label><input id="middle_name" name="middle_name" value="{{ old('middle_name') }}" autocomplete="additional-name" maxlength="50" pattern="[\p{L}\p{M} ]{1,50}" data-name-only>@error('middle_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="suffix">Suffix <span>(optional)</span></label><input id="suffix" name="suffix" value="{{ old('suffix') }}" autocomplete="honorific-suffix" maxlength="20" pattern="[\p{L}\p{M}\p{N} .-]{0,20}" data-name-suffix><small class="auth-help">Letters and numbers (e.g., Jr.).</small>@error('suffix')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>@error('email')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="contact_no">Contact number</label><input id="contact_no" type="tel" name="contact_no" value="{{ old('contact_no', '+63 ') }}" placeholder="+63 9XX XXX XXXX" autocomplete="tel" inputmode="tel" maxlength="16" pattern="(?:09\d{2} ?\d{3} ?\d{4}|\+63 ?9\d{2} ?\d{3} ?\d{4})" data-philippine-phone required><small class="auth-help">PH mobile: +63 9XX XXX XXXX or 09XX XXX XXXX; verified.</small>@error('contact_no')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="birthday">Date of birth</label><input id="birthday" type="date" name="birthday" value="{{ old('birthday') }}" autocomplete="bday" required>@error('birthday')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    </div></section>
                    <section class="registration-step" data-registration-step data-step-label="Delivery address"><fieldset class="auth-section"><legend>Delivery address</legend>@include('components.psgc-address-fields', ['prefix' => 'buyer-address', 'address' => null, 'postalRequired' => false])</fieldset></section>
                    <section class="registration-step" data-registration-step data-step-label="Password & terms">
                        <div class="auth-field"><label for="password">Password</label><div class="auth-password-wrap"><input id="password" type="password" name="password" autocomplete="new-password" aria-describedby="password-hint" required><button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button></div><small id="password-hint" class="auth-help">8+ characters; uppercase, lowercase, number, and symbol.</small>@error('password')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    <div class="auth-field"><label for="password_confirmation">Confirm password</label><div class="auth-password-wrap"><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required><button class="auth-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">Show</button></div></div>
                    <div class="auth-consent"><label class="auth-check"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required><span>I agree to EZiCart’s Terms of Service and Privacy Policy.</span></label>@error('terms')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    <button class="auth-submit" type="submit">Create account</button></section>
                </form>
                <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
            </div>
        </section>
    </main>
@endsection
