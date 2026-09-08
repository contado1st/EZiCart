@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/register-sorting.css') }}">
@endpush

@section('content')
<div class="register-sorting-wrapper">
    <div class="register-sorting-header">
        <h1 class="register-sorting-title">Logistics Hub Registration</h1>
        <p class="register-sorting-desc">Register a middle-mile sorting center to coordinate parcel routing and rider fleets</p>
    </div>

    @if($errors->any())
        <div class="register-sorting-alert-error">
            <ul class="register-sorting-error-list">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('register.sorting.post') }}" method="POST" enctype="multipart/form-data" class="register-sorting-form">
        @csrf

        <div class="form-field-group">
            <label class="form-field-label">Sorting Center / Business Name *</label>
            <input type="text" name="business_name" value="{{ old('business_name') }}" class="form-control-input" placeholder="e.g. EZiCart Laguna Central Hub" required>
        </div>

        <div class="grid-names-row">
            <div class="form-field-group">
                <label class="form-field-label">First Name *</label>
                <input type="text" name="first_name" value="{{ old('first_name') }}" class="form-control-input" required>
            </div>
            <div class="form-field-group">
                <label class="form-field-label">Last Name *</label>
                <input type="text" name="last_name" value="{{ old('last_name') }}" class="form-control-input" required>
            </div>
            <div class="form-field-group">
                <label class="form-field-label">M.I.</label>
                <input type="text" name="middle_initial" value="{{ old('middle_initial') }}" maxlength="2" class="form-control-input">
            </div>
        </div>

        <div class="grid-demographics-row">
            <div class="form-field-group">
                <label class="form-field-label">Sex *</label>
                <select name="sex" class="form-control-select" required>
                    <option value="Male" {{ old('sex') == 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ old('sex') == 'Female' ? 'selected' : '' }}>Female</option>
                    <option value="Other" {{ old('sex') == 'Other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>
            <div class="form-field-group">
                <label class="form-field-label">Birthday *</label>
                <input type="date" name="birthday" id="birthdayInput" value="{{ old('birthday') }}" class="form-control-input" required>
            </div>
            <div class="form-field-group">
                <label class="form-field-label">Age *</label>
                <input type="text" id="ageDisplay" readonly class="form-control-input form-control-readonly" placeholder="--">
            </div>
        </div>

        <div class="grid-two-columns">
            <div class="form-field-group">
                <label class="form-field-label">Email Address *</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control-input" required>
            </div>
            <div class="form-field-group">
                <label class="form-field-label">Contact Number *</label>
                <input type="text" name="contact_no" value="{{ old('contact_no') }}" class="form-control-input" required>
            </div>
        </div>

        <div class="grid-two-columns">
            <div class="form-field-group">
                <label class="form-field-label">Province *</label>
                <input type="text" name="province" value="{{ old('province', 'Laguna') }}" class="form-control-input" required>
            </div>
            <div class="form-field-group">
                <label class="form-field-label">Municipality *</label>
                <input type="text" name="municipality" value="{{ old('municipality', 'Santa Cruz') }}" class="form-control-input" required>
            </div>
        </div>

        <div class="grid-two-columns">
            <div class="form-field-group">
                <label class="form-field-label">Barangay *</label>
                <input type="text" name="barangay" value="{{ old('barangay') }}" class="form-control-input" required>
            </div>
            <div class="form-field-group">
                <label class="form-field-label">Facility Street Address *</label>
                <input type="text" name="street_address" value="{{ old('street_address') }}" class="form-control-input" required>
            </div>
        </div>

        <!-- Mandatory Verification Documents -->
        <div class="doc-compliance-card">
            <div class="doc-compliance-title">📋 Mandatory Business Verification</div>

            <div class="doc-upload-item">
                <label class="form-field-label">Upload Government-Issued ID *</label>
                <input type="file" name="id_document" accept=".jpg,.jpeg,.png,.pdf" class="doc-file-input" required>
            </div>

            <div class="doc-upload-item">
                <label class="form-field-label">Upload Business / DTI Permit *</label>
                <input type="file" name="business_permit" accept=".jpg,.jpeg,.png,.pdf" class="doc-file-input" required>
            </div>
        </div>

        <div class="grid-two-columns">
            <div class="form-field-group">
                <label class="form-field-label">Password *</label>
                <input type="password" name="password" class="form-control-input" required>
            </div>
            <div class="form-field-group">
                <label class="form-field-label">Confirm Password *</label>
                <input type="password" name="password_confirmation" class="form-control-input" required>
            </div>
        </div>

        <button type="submit" class="btn-submit-registration">
            Submit Sorting Center Application
        </button>
    </form>
</div>

<script>
    document.getElementById('birthdayInput').addEventListener('change', function() {
        const birthDate = new Date(this.value);
        if (!isNaN(birthDate)) {
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            document.getElementById('ageDisplay').value = age >= 0 ? age : 0;
        }
    });
</script>
@endsection