@extends('layouts.app')

@section('content')
    <main class="container" style="max-width: 900px; padding-block: 2rem;">
        <h1>Account settings</h1>
        <p>Update your contact and profile details. Your role, approval status, and uploaded documents can’t be changed here.</p>

        @if (session('success'))
            <div class="form-notice">{{ session('success') }}</div>
        @endif

        <section class="dash-panel" style="margin-block:1rem; padding:1.5rem;">
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
                    <h3>Active service areas</h3>
                    <ul>
                        @forelse ($user->serviceAreas as $area)
                            <li>{{ $area->name }}{{ $area->pivot->is_primary ? ' (primary)' : '' }}</li>
                        @empty
                            <li>No service areas assigned. Contact Logistics.</li>
                        @endforelse
                    </ul>
                @endif
                <div class="form-group"><label class="form-label" for="province">Province</label><input id="province" class="form-control" name="province" value="{{ old('province', $user->province) }}" required></div>
                <div class="form-group"><label class="form-label" for="municipality">Municipality</label><input id="municipality" class="form-control" name="municipality" value="{{ old('municipality', $user->municipality) }}" required></div>
                <div class="form-group"><label class="form-label" for="barangay">Barangay</label><input id="barangay" class="form-control" name="barangay" value="{{ old('barangay', $user->barangay) }}" required></div>
                <div class="form-group"><label class="form-label" for="street-address">Street address</label><input id="street-address" class="form-control" name="street_address" value="{{ old('street_address', $user->street_address) }}" required></div>
                <button class="btn-submit" type="submit">Save profile</button>
            </form>
        </section>

        <section class="dash-panel" style="margin-block:1rem; padding:1.5rem;">
            <h2>Change password</h2>
            <form action="{{ route('account.password.update') }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="form-group"><label class="form-label" for="current-password">Current password</label><input id="current-password" type="password" class="form-control" name="current_password" required autocomplete="current-password">@error('current_password')<p>{{ $message }}</p>@enderror</div>
                <div class="form-group"><label class="form-label" for="new-password">New password</label><input id="new-password" type="password" class="form-control" name="password" required autocomplete="new-password">@error('password')<p>{{ $message }}</p>@enderror</div>
                <div class="form-group"><label class="form-label" for="new-password-confirmation">Confirm new password</label><input id="new-password-confirmation" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password"></div>
                <button class="btn-submit" type="submit">Change password</button>
            </form>
        </section>

        <section class="dash-panel" style="margin-block:1rem; padding:1.5rem;">
            <h2>Private documents</h2>
            <ul>
                @if ($user->id_path || $user->id_upload_path)
                    <li><a href="{{ route('account.documents.show', 'identity') }}">Download identity document</a></li>
                @endif
                @if ($user->permit_path || $user->business_permit_path)
                    <li><a href="{{ route('account.documents.show', 'permit') }}">Download business permit</a></li>
                @endif
                @if ($user->license_path)
                    <li><a href="{{ route('account.documents.show', 'license') }}">Download driver's licence</a></li>
                @endif
                @if ($user->or_cr_path || $user->or_cr_upload_path)
                    <li><a href="{{ route('account.documents.show', 'vehicle') }}">Download OR/CR</a></li>
                @endif
            </ul>
        </section>
    </main>
@endsection
