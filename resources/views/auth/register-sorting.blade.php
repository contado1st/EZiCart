@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth-fallback.css') }}">
@endpush

@section('title', 'Register a logistics company | EZiCart')

@section('content')
    @php($applicant = $applicant ?? null)
    @php($resubmitting = $resubmitting ?? false)
    <main class="auth-page">
        <section class="auth-shell auth-shell--form" aria-labelledby="auth-title">
            <aside class="auth-aside"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a><div class="auth-aside-copy"><p class="auth-kicker">For delivery and sorting partners</p><h1>Connect your logistics team.</h1><p>Submit company details for review. Access starts after approval.</p></div><p class="auth-aside-foot">Documents: JPG, PNG, or PDF, up to 5 MB each.</p></aside>
            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Apply as a logistics company</h2><p class="auth-intro">Verify your email, then submit for review.</p>
                <nav class="auth-role-nav" aria-label="Choose account type"><a href="{{ route('register') }}">Buyer</a><a href="{{ route('register.seller') }}">Seller</a><a class="is-active" aria-current="page" href="{{ route('register.sorting') }}">Logistics company</a></nav>
                <div class="registration-progress" data-registration-progress aria-live="polite">
                    <div class="registration-progress-copy"><span data-step-count>Step 1</span><strong data-step-title></strong></div>
                    <div class="registration-progress-track" role="progressbar" aria-label="Registration progress" aria-valuemin="1" aria-valuemax="5" aria-valuenow="1"><span data-progress-fill></span></div>
                </div>
                @if ($errors->any())<div class="auth-message auth-message--error" role="alert"><strong>Review the highlighted fields.</strong><span>{{ $errors->first() }}</span></div>@endif
                @if ($resubmitting && $applicant?->rejection_reason)<div class="auth-message auth-message--error" role="status"><strong>Changes requested:</strong> {{ $applicant->rejection_reason }}</div>@endif
                <form action="{{ $resubmitting ? route('application.resubmit.post') : route('register.sorting.post') }}" method="POST" enctype="multipart/form-data" class="auth-form auth-form--registration" data-auth-submit data-registration-wizard novalidate>
                    @csrf
                    <section class="registration-step" data-registration-step data-step-label="Company details"><fieldset class="auth-section"><legend>Company details</legend><div class="auth-grid">
                        <div class="auth-field"><label for="business_name">Company name</label><input id="business_name" name="business_name" value="{{ old('business_name', $applicant?->business_name) }}" required>@error('business_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="sec_registration_no">SEC registration number</label><input id="sec_registration_no" name="sec_registration_no" value="{{ old('sec_registration_no', $applicant?->sec_registration_no) }}" placeholder="CS202012345" required>@error('sec_registration_no')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="tin">TIN</label><input id="tin" name="tin" value="{{ old('tin', $applicant?->tin) }}" placeholder="000-000-000-000" inputmode="numeric" required>@error('tin')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="contact_position">Contact person’s position</label><input id="contact_position" name="contact_position" value="{{ old('contact_position', $applicant?->contact_position) }}" required>@error('contact_position')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    </div></fieldset></section>
                    <section class="registration-step" data-registration-step data-step-label="Authorized contact"><fieldset class="auth-section"><legend>Authorized contact</legend><div class="auth-grid">
                        <div class="auth-field"><label for="first_name">First name</label><input id="first_name" name="first_name" value="{{ old('first_name', $applicant?->first_name) }}" autocomplete="given-name" maxlength="50" pattern="[\p{L}\p{M} ]{2,50}" data-name-only required>@error('first_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="last_name">Last name</label><input id="last_name" name="last_name" value="{{ old('last_name', $applicant?->last_name) }}" autocomplete="family-name" maxlength="50" pattern="[\p{L}\p{M} ]{2,50}" data-name-only required>@error('last_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="middle_name">Middle name <span>(optional)</span></label><input id="middle_name" name="middle_name" value="{{ old('middle_name', $applicant?->middle_name) }}" autocomplete="additional-name" maxlength="50" pattern="[\p{L}\p{M} ]{1,50}" data-name-only>@error('middle_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="suffix">Suffix <span>(optional)</span></label><input id="suffix" name="suffix" value="{{ old('suffix', $applicant?->suffix) }}" autocomplete="honorific-suffix" maxlength="20" pattern="[\p{L}\p{M}\p{N} .-]{0,20}" data-name-suffix><small class="auth-help">Letters and numbers (e.g., Jr.).</small>@error('suffix')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $applicant?->email) }}" autocomplete="email" autocapitalize="none" spellcheck="false" pattern="[A-Za-z0-9]+(\.[A-Za-z0-9]+)*@[A-Za-z0-9]+(\.[A-Za-z0-9]+)+" title="Use letters and numbers, with @ and dots in the email address." data-email-character-limit required>@error('email')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="contact_no">Contact number</label><input id="contact_no" type="tel" name="contact_no" value="{{ old('contact_no', $applicant?->contact_no ?? '+63 ') }}" placeholder="+63 9XX XXX XXXX" autocomplete="tel" inputmode="tel" maxlength="16" pattern="(?:09\d{2} ?\d{3} ?\d{4}|\+63 ?9\d{2} ?\d{3} ?\d{4})" data-philippine-phone required><small class="auth-help">PH mobile: +63 9XX XXX XXXX or 09XX XXX XXXX.</small>@error('contact_no')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    </div></fieldset></section>
                    <section class="registration-step" data-registration-step data-step-label="Company address"><fieldset class="auth-section"><legend>Company address</legend>@include('components.psgc-address-fields', ['prefix' => 'logistics-address', 'address' => $applicant, 'postalRequired' => true])<div class="auth-field"><label for="service_areas">Service areas <span>(select all that apply)</span></label><select id="service_areas" name="service_areas[]" multiple size="5" required>@foreach ($regions as $area)<option value="{{ $area }}" @selected(in_array($area, old('service_areas', $applicant?->service_areas ?? []), true))>{{ $area }}</option>@endforeach</select>@error('service_areas')<span class="auth-field-error">{{ $message }}</span>@enderror</div></fieldset></section>
                    <section class="registration-step" data-registration-step data-step-label="Company documents"><fieldset class="auth-section"><legend>Company documents</legend><div class="auth-grid">
                        @foreach (['sec_certificate' => 'SEC certificate', 'bir_form_2303' => 'BIR Form 2303', 'business_permit' => 'Mayor’s / business permit', 'representative_id' => 'Authorized representative’s valid ID'] as $name => $label)<div class="auth-field"><label for="{{ $name }}">{{ $label }}</label><input id="{{ $name }}" type="file" name="{{ $name }}" accept=".jpg,.jpeg,.png,.pdf" required>@error($name)<span class="auth-field-error">{{ $message }}</span>@enderror</div>@endforeach
                    </div></fieldset></section>
                    <section class="registration-step" data-registration-step data-step-label="Password & terms"><fieldset class="auth-section"><legend>Secure your account</legend><div class="auth-grid">
                        <div class="auth-field"><label for="password">Password</label><div class="auth-password-wrap"><input id="password" type="password" name="password" autocomplete="new-password" aria-describedby="logistics-password-hint" required><button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button></div><small id="logistics-password-hint" class="auth-help">8+ characters; uppercase, lowercase, number, and symbol.</small>@error('password')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="password_confirmation">Confirm password</label><div class="auth-password-wrap"><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required><button class="auth-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">Show</button></div></div>
                    </div></fieldset><div class="auth-consent"><label class="auth-check"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required><span>I agree to EZiCart’s Terms of Service and Privacy Policy.</span></label>@error('terms')<span class="auth-field-error">{{ $message }}</span>@enderror</div></section>

                    <button class="auth-submit" type="submit">{{ $resubmitting ? 'Resubmit logistics application' : 'Submit logistics application' }}</button>
                </form>
                <p class="auth-switch">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
            </div>
        </section>
    </main>
@endsection
