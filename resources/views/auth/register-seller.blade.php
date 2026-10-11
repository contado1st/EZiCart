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
            <aside class="auth-aside"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a><div class="auth-aside-copy"><p class="auth-kicker">For independent shops and growing teams</p><h1>Bring your store to EZiCart.</h1><p>Share your shop details for review. You can start managing your storefront after approval.</p></div><p class="auth-aside-foot">Keep your business documents handy. Each file can be up to 5 MB.</p></aside>
            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="{{ route('home') }}">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Apply as a seller</h2><p class="auth-intro">Complete the application and verify your email before it goes to review.</p>
                <nav class="auth-role-nav" aria-label="Choose account type"><a href="{{ route('register') }}">Buyer</a><a class="is-active" aria-current="page" href="{{ route('register.seller') }}">Seller</a><a href="{{ route('register.sorting') }}">Logistics company</a></nav>
                @if ($errors->any())<div class="auth-message auth-message--error" role="alert"><strong>Review the highlighted fields.</strong><span>{{ $errors->first() }}</span></div>@endif
                @if (($resubmitting ?? false) && $applicant?->rejection_reason)<div class="auth-message auth-message--error" role="status"><strong>Changes requested:</strong> {{ $applicant->rejection_reason }}</div>@endif
                <form action="{{ ($resubmitting ?? false) ? route('application.resubmit.post') : route('register.seller.post') }}" method="POST" enctype="multipart/form-data" class="auth-form" data-auth-submit>
                    @csrf
                    <fieldset class="auth-section"><legend>Contact person</legend><div class="auth-grid">
                        <div class="auth-field"><label for="first_name">First name</label><input id="first_name" name="first_name" value="{{ old('first_name', $applicant?->first_name) }}" autocomplete="given-name" maxlength="50" required>@error('first_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="last_name">Last name</label><input id="last_name" name="last_name" value="{{ old('last_name', $applicant?->last_name) }}" autocomplete="family-name" maxlength="50" required>@error('last_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $applicant?->email) }}" autocomplete="email" required>@error('email')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="contact_no">Mobile number</label><input id="contact_no" type="tel" name="contact_no" value="{{ old('contact_no', $applicant?->contact_no) }}" placeholder="09XXXXXXXXX or +639XXXXXXXXX" autocomplete="tel" required>@error('contact_no')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    </div></fieldset>
                    <fieldset class="auth-section"><legend>Store details</legend><div class="auth-grid">
                        <div class="auth-field"><label for="shop_name">Shop name</label><input id="shop_name" name="shop_name" value="{{ old('shop_name', $applicant?->shop_name) }}" minlength="3" maxlength="30" required>@error('shop_name')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="line_of_business">Shop category</label><select id="line_of_business" name="line_of_business" required><option value="">Choose a category</option>@foreach (['Beauty', 'Home & Living', 'Fashion', 'Electronics', 'Groceries', 'Other'] as $category)<option value="{{ $category }}" @selected(old('line_of_business', $applicant?->line_of_business) === $category)>{{ $category }}</option>@endforeach</select>@error('line_of_business')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="seller_type">Seller type</label><select id="seller_type" name="seller_type" data-seller-type required><option value="">Choose a seller type</option>@foreach (['Individual', 'Sole Proprietorship', 'Corporation'] as $type)<option value="{{ $type }}" @selected(old('seller_type', $applicant?->seller_type) === $type)>{{ $type }}</option>@endforeach</select>@error('seller_type')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="tin">TIN</label><input id="tin" name="tin" value="{{ old('tin', $applicant?->tin) }}" placeholder="000-000-000-000" inputmode="numeric" required>@error('tin')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    </div></fieldset>
                    <fieldset class="auth-section"><legend>Store address</legend><div class="auth-grid">
                        <div class="auth-field"><label for="region">Region</label><select id="region" name="region" required><option value="">Choose a region</option>@foreach ($regions as $region)<option value="{{ $region }}" @selected(old('region', $applicant?->region) === $region)>{{ $region }}</option>@endforeach</select>@error('region')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        @foreach (['province' => 'Province', 'municipality' => 'City / Municipality', 'barangay' => 'Barangay'] as $name => $label)<div class="auth-field"><label for="{{ $name }}">{{ $label }}</label><input id="{{ $name }}" name="{{ $name }}" value="{{ old($name, $applicant?->{$name}) }}" autocomplete="address-level2" required>@error($name)<span class="auth-field-error">{{ $message }}</span>@enderror</div>@endforeach
                        <div class="auth-field auth-field--wide"><label for="street_address">Street address</label><input id="street_address" name="street_address" value="{{ old('street_address', $applicant?->street_address) }}" autocomplete="street-address" required>@error('street_address')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="postal_code">Postal code</label><input id="postal_code" name="postal_code" value="{{ old('postal_code', $applicant?->postal_code) }}" inputmode="numeric" maxlength="4" required>@error('postal_code')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    </div><p class="auth-help">Choose your region, then enter the province, city or municipality, and barangay exactly as used for your store address.</p></fieldset>
                    <fieldset class="auth-section"><legend>Required documents</legend><div class="auth-grid">
                        <div class="auth-field"><label for="id_document">Government-issued ID</label><input id="id_document" type="file" name="id_document" accept=".jpg,.jpeg,.png,.pdf" required>@error('id_document')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field" data-seller-document="Sole Proprietorship"><label for="dti_certificate">DTI certificate <span>(sole proprietorship)</span></label><input id="dti_certificate" type="file" name="dti_certificate" accept=".jpg,.jpeg,.png,.pdf">@error('dti_certificate')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field" data-seller-document="Sole Proprietorship,Corporation"><label for="bir_form_2303">BIR Form 2303 <span>(business types)</span></label><input id="bir_form_2303" type="file" name="bir_form_2303" accept=".jpg,.jpeg,.png,.pdf">@error('bir_form_2303')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field" data-seller-document="Corporation"><label for="sec_certificate">SEC certificate <span>(corporation)</span></label><input id="sec_certificate" type="file" name="sec_certificate" accept=".jpg,.jpeg,.png,.pdf">@error('sec_certificate')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    </div><p class="auth-help">Accepted files: JPG, PNG, or PDF. Maximum 5 MB per file.</p></fieldset>
                    <fieldset class="auth-section"><legend>Secure your account</legend><div class="auth-grid">
                        <div class="auth-field"><label for="password">Password</label><div class="auth-password-wrap"><input id="password" type="password" name="password" autocomplete="new-password" aria-describedby="seller-password-hint" required><button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button></div><small id="seller-password-hint" class="auth-help">8+ characters with uppercase, lowercase, number, and symbol.</small>@error('password')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                        <div class="auth-field"><label for="password_confirmation">Confirm password</label><div class="auth-password-wrap"><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required><button class="auth-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">Show</button></div></div>
                    </div></fieldset>
                    <div class="auth-consent"><label class="auth-check"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required><span>I agree to EZiCart’s Terms of Service and Privacy Policy.</span></label>@error('terms')<span class="auth-field-error">{{ $message }}</span>@enderror</div>
                    <button class="auth-submit" type="submit">{{ $resubmitting ? 'Resubmit seller application' : 'Submit seller application' }}</button>
                </form>
                <p class="auth-switch">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
            </div>
        </section>
    </main>
@endsection
