@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth-fallback.css') }}">
@endpush

@section('title', 'Register your store | EZiCart')

@section('content')
    @php($applicant = $applicant ?? null)
    @php($resubmitting = $resubmitting ?? false)
    <main class="auth-page">
        <section class="auth-shell auth-shell--form" aria-labelledby="auth-title">
            <aside class="auth-aside"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a><div class="auth-aside-copy"><p class="auth-kicker">For independent shops and growing teams</p><h1>Bring your store to EZiCart.</h1><p>Submit your shop details for review. Access starts after approval.</p></div><p class="auth-aside-foot">Documents: JPG, PNG, or PDF, up to 5 MB each.</p></aside>
            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Apply as a seller</h2><p class="auth-intro">Complete the form, verify your email, then await review.</p>
                <nav class="auth-role-nav" aria-label="Choose account type"><a href="{{ route('register') }}">Buyer</a><a class="is-active" aria-current="page" href="{{ route('register.seller') }}">Seller</a><a href="{{ route('register.sorting') }}">Logistics company</a></nav>
                <div class="registration-progress" data-registration-progress aria-live="polite">
                    <div class="registration-progress-copy"><span data-step-count>Step 1</span><strong data-step-title></strong></div>
                    <div class="registration-progress-track" role="progressbar" aria-label="Registration progress" aria-valuemin="1" aria-valuemax="5" aria-valuenow="1"><span data-progress-fill></span></div>
                </div>
                @if ($errors->any())<div class="auth-message auth-message--error" role="alert"><strong>Review the highlighted fields.</strong><span>{{ $errors->first() }}</span></div>@endif
                @if (($resubmitting ?? false) && $applicant?->rejection_reason)<div class="auth-message auth-message--error" role="status"><strong>Changes requested:</strong> {{ $applicant->rejection_reason }}</div>@endif
                <form action="{{ ($resubmitting ?? false) ? route('application.resubmit.post') : route('register.seller.post') }}" method="POST" enctype="multipart/form-data" class="auth-form auth-form--registration" data-auth-submit data-registration-wizard novalidate>
                    @csrf
                    <section class="registration-step" data-registration-step data-step-label="Contact person"><fieldset class="auth-section"><legend>Contact person</legend><div class="auth-grid">
                        <div class="auth-field"><label for="first_name">First name</label><input id="first_name" name="first_name" value="{{ old('first_name', $applicant?->first_name) }}" autocomplete="given-name" maxlength="50" pattern="[\p{L}\p{M} ]{2,50}" data-name-only required>@error('first_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="last_name">Last name</label><input id="last_name" name="last_name" value="{{ old('last_name', $applicant?->last_name) }}" autocomplete="family-name" maxlength="50" pattern="[\p{L}\p{M} ]{2,50}" data-name-only required>@error('last_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="middle_name">Middle name <span>(optional)</span></label><input id="middle_name" name="middle_name" value="{{ old('middle_name', $applicant?->middle_name) }}" autocomplete="additional-name" maxlength="50" pattern="[\p{L}\p{M} ]{1,50}" data-name-only>@error('middle_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="suffix">Suffix <span>(optional)</span></label><input id="suffix" name="suffix" value="{{ old('suffix', $applicant?->suffix) }}" autocomplete="honorific-suffix" maxlength="20" pattern="[\p{L}\p{M}\p{N} .-]{0,20}" data-name-suffix><small class="auth-help">Letters and numbers (e.g., Jr.).</small>@error('suffix')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $applicant?->email) }}" autocomplete="email" autocapitalize="none" spellcheck="false" pattern="[A-Za-z0-9]+(\.[A-Za-z0-9]+)*@[A-Za-z0-9]+(\.[A-Za-z0-9]+)+" title="Use letters and numbers, with @ and dots in the email address." data-email-character-limit required>@error('email')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="contact_no">Contact number</label><input id="contact_no" type="tel" name="contact_no" value="{{ old('contact_no', $applicant?->contact_no ?? '+63 ') }}" placeholder="+63 9XX XXX XXXX" autocomplete="tel" inputmode="tel" maxlength="16" pattern="(?:09\d{2} ?\d{3} ?\d{4}|\+63 ?9\d{2} ?\d{3} ?\d{4})" data-philippine-phone required><small class="auth-help">PH mobile: +63 9XX XXX XXXX or 09XX XXX XXXX.</small>@error('contact_no')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    </div></fieldset></section>
                    <section class="registration-step" data-registration-step data-step-label="Store details"><fieldset class="auth-section"><legend>Store details</legend><div class="auth-grid">
                        <div class="auth-field"><label for="shop_name">Shop name</label><input id="shop_name" name="shop_name" value="{{ old('shop_name', $applicant?->shop_name) }}" minlength="3" maxlength="30" required>@error('shop_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="line_of_business">Shop category</label><select id="line_of_business" name="line_of_business" required><option value="">Choose a category</option>@foreach (['Beauty', 'Home & Living', 'Fashion', 'Electronics', 'Groceries', 'Other'] as $category)<option value="{{ $category }}" @selected(old('line_of_business', $applicant?->line_of_business) === $category)>{{ $category }}</option>@endforeach</select>@error('line_of_business')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="seller_type">Seller type</label><select id="seller_type" name="seller_type" data-seller-type required><option value="">Choose a seller type</option>@foreach (['Individual', 'Sole Proprietorship', 'Corporation'] as $type)<option value="{{ $type }}" @selected(old('seller_type', $applicant?->seller_type) === $type)>{{ $type }}</option>@endforeach</select>@error('seller_type')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="tin">TIN</label><input id="tin" name="tin" value="{{ old('tin', $applicant?->tin) }}" placeholder="000-000-000-000" inputmode="numeric" required>@error('tin')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    </div></fieldset></section>
                    <section class="registration-step" data-registration-step data-step-label="Store address"><fieldset class="auth-section"><legend>Store address</legend>@include('components.psgc-address-fields', ['prefix' => 'seller-address', 'address' => $applicant, 'postalRequired' => true])</fieldset></section>
                    <section class="registration-step" data-registration-step data-step-label="Required documents"><fieldset class="auth-section"><legend>Required documents</legend><div class="auth-grid">
                        <div class="auth-field"><label for="id_document">Government-issued ID</label><input id="id_document" type="file" name="id_document" accept=".jpg,.jpeg,.png,.pdf" required>@error('id_document')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field" data-seller-document="Sole Proprietorship"><label for="dti_certificate">DTI certificate <span>(sole proprietorship)</span></label><input id="dti_certificate" type="file" name="dti_certificate" accept=".jpg,.jpeg,.png,.pdf">@error('dti_certificate')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field" data-seller-document="Sole Proprietorship,Corporation"><label for="bir_form_2303">BIR Form 2303 <span>(business types)</span></label><input id="bir_form_2303" type="file" name="bir_form_2303" accept=".jpg,.jpeg,.png,.pdf">@error('bir_form_2303')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field" data-seller-document="Corporation"><label for="sec_certificate">SEC certificate <span>(corporation)</span></label><input id="sec_certificate" type="file" name="sec_certificate" accept=".jpg,.jpeg,.png,.pdf">@error('sec_certificate')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    </div><p class="auth-help">JPG, PNG, or PDF; max 5 MB each.</p></fieldset></section>
                    <section class="registration-step" data-registration-step data-step-label="Password & terms"><fieldset class="auth-section"><legend>Secure your account</legend><div class="auth-grid">
                        <div class="auth-field"><label for="password">Password</label><div class="auth-password-wrap"><input id="password" type="password" name="password" autocomplete="new-password" aria-describedby="seller-password-hint" required><button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button></div><small id="seller-password-hint" class="auth-help">8+ characters; uppercase, lowercase, number, and symbol.</small>@error('password')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="password_confirmation">Confirm password</label><div class="auth-password-wrap"><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required><button class="auth-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">Show</button></div></div>
                    </div></fieldset><div class="auth-consent"><label class="auth-check"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required><span>I agree to EZiCart’s Terms of Service and Privacy Policy.</span></label>@error('terms')<span class="auth-field-error">{{ $message }}</span>@enderror</div></section>

                    <button class="auth-submit" type="submit">{{ $resubmitting ? 'Resubmit seller application' : 'Submit seller application' }}</button>
                </form>
                <p class="auth-switch">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
            </div>
        </section>
    </main>
@endsection
