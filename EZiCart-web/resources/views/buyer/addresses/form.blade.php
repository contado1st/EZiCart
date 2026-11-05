@extends('layouts.app')
@section('title', ($address ? 'Edit address' : 'Add an address').' — EZiCart')
@push('styles')
    @vite('resources/css/shared/dashboard.css')
    <link rel="stylesheet" href="{{ asset('css/buyer-addresses.css') }}?v={{ filemtime(public_path('css/buyer-addresses.css')) }}">
@endpush
@section('content')
<main class="buyer-address-page">
    <header class="dash-header"><div><h1 class="dash-title">{{ $address ? 'Edit address' : 'Add an address' }}</h1><p class="dash-subtitle">Save a delivery location and the contact who will receive your parcel.</p></div><a href="{{ route('buyer.addresses.index') }}" class="dash-btn-sm dash-btn-outline">Back to addresses</a></header>
    <section class="dash-panel buyer-address-form-panel">
        @if ($errors->any())<div class="buyer-address-error" role="alert"><strong>Check your address details.</strong><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ $address ? route('buyer.addresses.update', $address) : route('buyer.addresses.store') }}" class="buyer-address-form">
            @csrf
            @if ($address) @method('PUT') @endif
            <div class="buyer-address-form-grid">
                <div class="buyer-address-field"><label for="address-label">Address label</label><input id="address-label" name="label" maxlength="50" value="{{ old('label', $address?->label) }}" placeholder="Home, work, or another location" required></div>
                <div class="buyer-address-field"><label for="address-recipient">Recipient name</label><input id="address-recipient" name="recipient_name" minlength="2" maxlength="255" value="{{ old('recipient_name', $address?->recipient_name ?? trim(auth()->user()->first_name.' '.auth()->user()->last_name)) }}" autocomplete="name" required></div>
                <div class="buyer-address-field"><label for="address-contact">Recipient mobile number</label><input id="address-contact" name="recipient_contact" type="tel" maxlength="13" pattern="(09[0-9]{9}|\+639[0-9]{9})" value="{{ old('recipient_contact', $address?->recipient_contact ?? auth()->user()->contact_no) }}" autocomplete="tel" required aria-describedby="address-contact-help"><small id="address-contact-help">Use 09XXXXXXXXX or +639XXXXXXXXX.</small></div>
            </div>
            @include('components.psgc-address-fields', ['prefix' => 'saved-address', 'address' => $address, 'postalRequired' => false])
            @if ($address?->is_default)
                <p class="buyer-address-help"><span class="buyer-address-default">Default address</span> To change your default, choose another address from your saved list.</p>
            @else
                <label class="buyer-address-checkbox"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', false))> Use as my default delivery address</label>
            @endif
            <div class="buyer-address-actions"><button class="dash-btn-primary" type="submit">{{ $address ? 'Save changes' : 'Save address' }}</button><a class="dash-btn-sm dash-btn-outline" href="{{ route('buyer.addresses.index') }}">Cancel</a></div>
        </form>
    </section>
</main>
@endsection
