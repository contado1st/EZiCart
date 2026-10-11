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
            <aside class="auth-aside"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a><div class="auth-aside-copy"><p class="auth-kicker">For delivery and sorting partners</p><h1>Connect your logistics team.</h1><p>Submit your company details for review. Operational access begins after verification and approval.</p></div><p class="auth-aside-foot">Accepted documents: JPG, PNG, or PDF, up to 5 MB each.</p></aside>
            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Apply as a logistics company</h2><p class="auth-intro">We’ll verify your email before sending your application to admin review.</p>
                <nav class="auth-role-nav" aria-label="Choose account type"><a href="{{ route('register') }}">Buyer</a><a href="{{ route('register.seller') }}">Seller</a><a class="is-active" aria-current="page" href="{{ route('register.sorting') }}">Logistics company</a></nav>
                @if ($errors->any())<div class="auth-message auth-message--error" role="alert"><strong>Review the highlighted fields.</strong><span>{{ $errors->first() }}</span></div>@endif
                @if ($resubmitting && $applicant?->rejection_reason)<div class="auth-message auth-message--error" role="status"><strong>Changes requested:</strong> {{ $applicant->rejection_reason }}</div>@endif
                <form action="{{ $resubmitting ? route('application.resubmit.post') : route('register.sorting.post') }}" method="POST" enctype="multipart/form-data" class="auth-form" data-auth-submit>
                    @csrf
                    <fieldset class="auth-section"><legend>Company details</legend><div class="auth-grid">
                        <div class="auth-field"><label for="business_name">Company name</label><input id="business_name" name="business_name" value="{{ old('business_name', $applicant?->business_name) }}" required>@error('business_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="sec_registration_no">SEC registration number</label><input id="sec_registration_no" name="sec_registration_no" value="{{ old('sec_registration_no', $applicant?->sec_registration_no) }}" placeholder="CS202012345" required>@error('sec_registration_no')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="tin">TIN</label><input id="tin" name="tin" value="{{ old('tin', $applicant?->tin) }}" placeholder="000-000-000-000" inputmode="numeric" required>@error('tin')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="contact_position">Contact person’s position</label><input id="contact_position" name="contact_position" value="{{ old('contact_position', $applicant?->contact_position) }}" required>@error('contact_position')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    </div></fieldset>
                    <fieldset class="auth-section"><legend>Authorized contact</legend><div class="auth-grid">
                        <div class="auth-field"><label for="first_name">First name</label><input id="first_name" name="first_name" value="{{ old('first_name', $applicant?->first_name) }}" autocomplete="given-name" required>@error('first_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="last_name">Last name</label><input id="last_name" name="last_name" value="{{ old('last_name', $applicant?->last_name) }}" autocomplete="family-name" required>@error('last_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $applicant?->email) }}" autocomplete="email" required>@error('email')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="contact_no">Mobile number</label><input id="contact_no" type="tel" name="contact_no" value="{{ old('contact_no', $applicant?->contact_no) }}" placeholder="09XXXXXXXXX or +639XXXXXXXXX" autocomplete="tel" required>@error('contact_no')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    </div></fieldset>
                    <fieldset class="auth-section"><legend>Company address</legend><div class="auth-grid">
                        <div class="auth-field"><label for="region">Region</label><select id="region" name="region" required><option value="">Choose a region</option>@foreach ($regions as $region)<option value="{{ $region }}" @selected(old('region', $applicant?->region) === $region)>{{ $region }}</option>@endforeach</select>@error('region')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        @foreach (['province' => 'Province', 'municipality' => 'City / Municipality', 'barangay' => 'Barangay'] as $name => $label)<div class="auth-field"><label for="{{ $name }}">{{ $label }}</label><input id="{{ $name }}" name="{{ $name }}" value="{{ old($name, $applicant?->{$name}) }}" required>@error($name)<span class="auth-field-error">{{ $message }}</span>@enderror</div>@endforeach
                        <div class="auth-field auth-field--wide"><label for="street_address">Street address</label><input id="street_address" name="street_address" value="{{ old('street_address', $applicant?->street_address) }}" autocomplete="street-address" required>@error('street_address')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="postal_code">Postal code</label><input id="postal_code" name="postal_code" value="{{ old('postal_code', $applicant?->postal_code) }}" inputmode="numeric" maxlength="4" required>@error('postal_code')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field auth-field--wide"><label for="service_areas">Service areas <span>(select all that apply)</span></label><select id="service_areas" name="service_areas[]" multiple size="5" required>@foreach ($regions as $area)<option value="{{ $area }}" @selected(in_array($area, old('service_areas', $applicant?->service_areas ?? []), true))>{{ $area }}</option>@endforeach</select>@error('service_areas')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    </div><p class="auth-help">Choose your region, then enter the province, city or municipality, and barangay exactly as used for your company address.</p></fieldset>
                    <fieldset class="auth-section"><legend>Company documents</legend><div class="auth-grid">
                        @foreach (['sec_certificate' => 'SEC certificate', 'bir_form_2303' => 'BIR Form 2303', 'business_permit' => 'Mayor’s / business permit', 'representative_id' => 'Authorized representative’s valid ID'] as $name => $label)<div class="auth-field"><label for="{{ $name }}">{{ $label }}</label><input id="{{ $name }}" type="file" name="{{ $name }}" accept=".jpg,.jpeg,.png,.pdf" required>@error($name)<span class="auth-field-error">{{ $message }}</span>@enderror</div>@endforeach
                    </div></fieldset>
                    <fieldset class="auth-section"><legend>Secure your account</legend><div class="auth-grid">
                        <div class="auth-field"><label for="password">Password</label><div class="auth-password-wrap"><input id="password" type="password" name="password" autocomplete="new-password" aria-describedby="logistics-password-hint" required><button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button></div><small id="logistics-password-hint" class="auth-help">8+ characters with uppercase, lowercase, number, and symbol.</small>@error('password')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="password_confirmation">Confirm password</label><div class="auth-password-wrap"><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required><button class="auth-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">Show</button></div></div>
                    </div></fieldset>
                    <div class="auth-consent"><label class="auth-check"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required><span>I agree to EZiCart’s Terms of Service and Privacy Policy.</span></label>@error('terms')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    <button class="auth-submit" type="submit">{{ $resubmitting ? 'Resubmit logistics application' : 'Submit logistics application' }}</button>
                </form>
                <p class="auth-switch">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
            </div>
        </section>
    </main>
@endsection
