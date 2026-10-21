@extends('layouts.app')

@if ($user->role === 'buyer')
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/buyer-account.css') }}?v={{ filemtime(public_path('css/buyer-account.css')) }}">
    @endpush
@endif

@section('content')
    @if ($user->role === 'buyer')
        <main class="buyer-account-page">
            @include('components.buyer-account-sidebar', ['activeSection' => 'profile'])

            <div class="buyer-account-content">
                <header class="buyer-account-header">
                    <div><p class="buyer-account-eyebrow">My Account</p><h1>My profile</h1><p>Manage your profile and keep your delivery details up to date.</p></div>
                    <a href="{{ route('buyer.dashboard') }}">View my purchases <span aria-hidden="true">↗</span></a>
                </header>

                @if (session('success'))<div class="buyer-account-notice" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())<div class="buyer-account-notice buyer-account-notice--error" role="alert">{{ $errors->first() }}</div>@endif

                <section class="buyer-account-panel" id="buyer-profile" aria-labelledby="buyer-profile-title">
                    <div class="buyer-account-panel-heading"><div><h2 id="buyer-profile-title">Profile details</h2><p>Update the name and contact details connected to your account.</p></div></div>
                    <form action="{{ route('account.profile.update') }}" method="POST" class="buyer-account-form">
                        @csrf
                        @method('PATCH')
                        <div class="buyer-account-form-grid">
                            <div class="form-group"><label class="form-label" for="first-name">First name</label><input id="first-name" class="form-control" name="first_name" value="{{ old('first_name', $user->first_name) }}" autocomplete="given-name" required>@error('first_name')<p class="buyer-account-field-error">{{ $message }}</p>@enderror</div>
                            <div class="form-group"><label class="form-label" for="middle-initial">Middle initial</label><input id="middle-initial" class="form-control" name="middle_initial" maxlength="2" value="{{ old('middle_initial', $user->middle_initial) }}" autocomplete="additional-name">@error('middle_initial')<p class="buyer-account-field-error">{{ $message }}</p>@enderror</div>
                            <div class="form-group"><label class="form-label" for="last-name">Last name</label><input id="last-name" class="form-control" name="last_name" value="{{ old('last_name', $user->last_name) }}" autocomplete="family-name" required>@error('last_name')<p class="buyer-account-field-error">{{ $message }}</p>@enderror</div>
                            <div class="form-group"><label class="form-label" for="email">Email address</label><input id="email" class="form-control" value="{{ $user->email }}" autocomplete="email" disabled><small>Your login email is currently read-only.</small></div>
                            <div class="form-group"><label class="form-label" for="contact-number">Contact number</label><input id="contact-number" class="form-control" name="contact_no" value="{{ old('contact_no', $user->contact_no) }}" autocomplete="tel" required>@error('contact_no')<p class="buyer-account-field-error">{{ $message }}</p>@enderror</div>
                        </div>

                        <div class="buyer-account-address" id="buyer-addresses">
                            <div class="buyer-account-panel-heading"><div><h2>Default delivery address</h2><p>Editing this location also updates your default saved address.</p></div><a href="{{ route('buyer.addresses.index') }}">Manage saved addresses ({{ $user->addresses_count }})</a></div>
                            @include('components.psgc-address-fields', ['prefix' => 'account-address', 'address' => $user, 'postalRequired' => false])
                        </div>
                        <button class="buyer-account-save" type="submit">Save profile</button>
                    </form>
                </section>

                <section class="buyer-account-panel buyer-password-panel" id="buyer-password" aria-labelledby="buyer-password-title">
                    <div class="buyer-account-panel-heading"><div><h2 id="buyer-password-title">Change password</h2><p>Use a unique password to protect your EZiCart account.</p></div></div>
                    <form action="{{ route('account.password.update') }}" method="POST" class="buyer-account-password-form" data-decision="account.password.update" data-decision-title="Change your password?" data-decision-message="Use the new password the next time you sign in. Check that you have entered it correctly." data-decision-label="Change password" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                        @csrf
                        @method('PATCH')
                        <div class="buyer-password-fields">
                            <div class="form-group"><label class="form-label" for="current-password">Current password</label><div class="credential-password-wrap"><input id="current-password" type="password" class="form-control" name="current_password" required autocomplete="current-password"><button class="credential-password-toggle" type="button" data-password-toggle="current-password" aria-label="Show password" aria-pressed="false">Show</button></div>@error('current_password')<p class="buyer-account-field-error">{{ $message }}</p>@enderror</div>
                            <div class="form-group"><label class="form-label" for="new-password">New password</label><div class="credential-password-wrap"><input id="new-password" type="password" class="form-control" name="password" required autocomplete="new-password"><button class="credential-password-toggle" type="button" data-password-toggle="new-password" aria-label="Show password" aria-pressed="false">Show</button></div>@error('password')<p class="buyer-account-field-error">{{ $message }}</p>@enderror</div>
                            <div class="form-group"><label class="form-label" for="new-password-confirmation">Confirm new password</label><div class="credential-password-wrap"><input id="new-password-confirmation" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password"><button class="credential-password-toggle" type="button" data-password-toggle="new-password-confirmation" aria-label="Show password" aria-pressed="false">Show</button></div></div>
                        </div>
                        <button class="buyer-account-save" type="submit">Update password</button>
                    </form>
                </section>
            </div>
        </main>
    @else
        <main class="container u-extracted-d7ea292b2e">
            <h1>Account settings</h1>
            <p>Update your contact and profile details. Your role, approval status, and uploaded documents can’t be changed here.</p>

            @if (session('success'))<div class="form-notice">{{ session('success') }}</div>@endif

            <section class="dash-panel u-extracted-ebdbcd4a73">
                <h2>Profile and contact details</h2>
                <form action="{{ route('account.profile.update') }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="form-group"><label class="form-label" for="first-name">First name</label><input id="first-name" class="form-control" name="first_name" value="{{ old('first_name', $user->first_name) }}" required>@error('first_name')<p>{{ $message }}</p>@enderror</div>
                    <div class="form-group"><label class="form-label" for="middle-initial">Middle initial</label><input id="middle-initial" class="form-control" name="middle_initial" maxlength="2" value="{{ old('middle_initial', $user->middle_initial) }}"></div>
                    <div class="form-group"><label class="form-label" for="last-name">Last name</label><input id="last-name" class="form-control" name="last_name" value="{{ old('last_name', $user->last_name) }}" required>@error('last_name')<p>{{ $message }}</p>@enderror</div>
                    <div class="form-group"><label class="form-label" for="email">Email</label><input id="email" class="form-control" value="{{ $user->email }}" disabled></div>
                    <div class="form-group"><label class="form-label" for="contact-number">Contact number</label><input id="contact-number" class="form-control" name="contact_no" value="{{ old('contact_no', $user->contact_no) }}" required>@error('contact_no')<p>{{ $message }}</p>@enderror</div>
                    @if ($user->role === 'seller')
                        <div class="form-group"><label class="form-label" for="business-name">Business name</label><input id="business-name" class="form-control" name="business_name" value="{{ old('business_name', $user->business_name) }}" required></div>
                        <div class="form-group"><label class="form-label" for="line-of-business">Line of business</label><input id="line-of-business" class="form-control" name="line_of_business" value="{{ old('line_of_business', $user->line_of_business) }}"></div>
                    @endif
                    @if ($user->role === 'courier')
                        <div class="form-group"><label class="form-label" for="vehicle-type">Vehicle type</label><input id="vehicle-type" class="form-control" name="vehicle_type" value="{{ old('vehicle_type', $user->vehicle_type) }}"></div>
                        <div class="form-group"><label class="form-label" for="plate-number">Plate number</label><input id="plate-number" class="form-control" name="plate_number" value="{{ old('plate_number', $user->plate_number) }}"></div>
                        <h3>Active service areas</h3><ul>@forelse ($user->serviceAreas as $area)<li>{{ $area->name }}{{ $area->pivot->is_primary ? ' (primary)' : '' }}</li>@empty<li>No service areas assigned. Contact Logistics.</li>@endforelse</ul>
                    @endif
                    @include('components.psgc-address-fields', ['prefix' => 'account-address', 'address' => $user, 'postalRequired' => false])
                    <button class="btn-submit" type="submit">Save profile</button>
                </form>
            </section>

            <section class="dash-panel u-extracted-ebdbcd4a73">
                <h2>Change password</h2>
                <form action="{{ route('account.password.update') }}" method="POST" data-decision="account.password.update" data-decision-title="Change your password?" data-decision-message="Use the new password the next time you sign in. Check that you have entered it correctly." data-decision-label="Change password" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    @csrf
                    @method('PATCH')
                    @foreach ([['current-password', 'Current password', 'current_password', 'current-password'], ['new-password', 'New password', 'password', 'new-password'], ['new-password-confirmation', 'Confirm new password', 'password_confirmation', 'new-password']] as [$id, $label, $name, $autocomplete])
                        <div class="form-group"><label class="form-label" for="{{ $id }}">{{ $label }}</label><div class="credential-password-wrap"><input id="{{ $id }}" type="password" class="form-control" name="{{ $name }}" required autocomplete="{{ $autocomplete }}"><button class="credential-password-toggle" type="button" data-password-toggle="{{ $id }}" aria-label="Show password" aria-pressed="false">Show</button></div>@error($name)<p>{{ $message }}</p>@enderror</div>
                    @endforeach
                    <button class="btn-submit" type="submit">Change password</button>
                </form>
            </section>

            <section class="dash-panel u-extracted-ebdbcd4a73">
                <h2>Private documents</h2>
                <ul>
                    @if ($user->id_path || $user->id_upload_path)<li><a href="{{ route('account.documents.show', 'identity') }}">Download identity document</a></li>@endif
                    @if ($user->permit_path || $user->business_permit_path)<li><a href="{{ route('account.documents.show', 'permit') }}">Download business permit</a></li>@endif
                    @if ($user->license_path)<li><a href="{{ route('account.documents.show', 'license') }}">Download driver's licence</a></li>@endif
                    @if ($user->or_cr_path || $user->or_cr_upload_path)<li><a href="{{ route('account.documents.show', 'vehicle') }}">Download OR/CR</a></li>@endif
                </ul>
            </section>
        </main>
    @endif
@endsection
