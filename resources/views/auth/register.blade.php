@extends('layouts.app')

@section('content')
<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 1050px;">
        
        <!-- Branding Hero Panel -->
        <div class="auth-branding">
            <span class="section-tag">Buyer Registration</span>
            <h1 class="auth-brand-title">Join EZiCart Today</h1>
            <p class="hero-text">Create your buyer account to explore deals, manage orders, and enjoy tailored shopping recommendations.</p>
            <ul class="auth-feature-list">
                <li class="auth-feature-item">
                    <span class="auth-feature-icon">🛡️</span>
                    Admin-verified accounts for transaction safety
                </li>
                <li class="auth-feature-item">
                    <span class="auth-feature-icon">🏷️</span>
                    Access to exclusive marketplace vouchers
                </li>
            </ul>
        </div>

        <!-- Register Form Panel -->
        <div class="auth-form-container">

            <div class="role-selector">
                <a href="{{ route('register') }}" class="role-tab active">🛍️ Buyer</a>
                <a href="{{ route('register.seller') }}" class="role-tab">🏪 Seller</a>
                <a href="{{ route('register.courier') }}" class="role-tab">🚚 Courier</a>
            </div>

            <h2 class="auth-title">Sign Up as Buyer</h2>

            <div class="form-notice">
                ℹ️ After submitting your registration, please wait for administrator approval sent to your email.
            </div>

            @if ($errors->any())
                <div class="alert alert-danger" style="background-color: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem;">
                    <ul style="margin: 0; padding-left: 1.25rem;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('register.post') }}" method="POST" enctype="multipart/form-data" onsubmit="enableAddressFields()">
                @csrf
                
                <div class="form-grid">
                    <!-- Personal Details -->
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" required value="{{ old('first_name') }}">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required value="{{ old('last_name') }}">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Middle Initial</label>
                        <input type="text" name="middle_initial" maxlength="2" class="form-control" value="{{ old('middle_initial') }}">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sex *</label>
                        <select name="sex" class="form-control" required>
                            <option value="">Select Sex</option>
                            <option value="Male" {{ old('sex') == 'Male' ? 'selected' : '' }}>Male</option>
                            <option value="Female" {{ old('sex') == 'Female' ? 'selected' : '' }}>Female</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">E-mail *</label>
                        <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact No. *</label>
                        <input type="text" name="contact_no" class="form-control" placeholder="09123456789" required value="{{ old('contact_no') }}">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Birthday *</label>
                        <input type="date" id="birthday" name="birthday" class="form-control" required value="{{ old('birthday') }}" onchange="calculateAge(this.value)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Age (Auto-generated) *</label>
                        <input type="number" id="age" name="age" class="form-control" readonly required placeholder="Auto-calculated" value="{{ old('age') }}">
                    </div>

                    <!-- Address Section -->
                    <div class="form-group">
                        <label class="form-label">Province *</label>
                        <select id="province" name="province" class="form-control" required>
                            <option value="">Select Province</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Municipality / City *</label>
                        <select id="municipality" name="municipality" class="form-control" required disabled>
                            <option value="">Select Municipality / City</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Barangay *</label>
                        <select id="barangay" name="barangay" class="form-control" required disabled>
                            <option value="">Select Barangay</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Street Address / House No. *</label>
                        <input type="text" name="street_address" class="form-control" placeholder="123 Main St" required value="{{ old('street_address') }}">
                    </div>

                    <!-- Uploads & Password -->
                    <div class="form-group full-width">
                        <label class="form-label">Upload Valid ID (Image/PDF) *</label>
                        <input type="file" name="id_document" class="form-control" accept="image/*,.pdf" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit" style="margin-top: 1rem;">Submit Registration</button>
            </form>

            <p class="auth-footer-text">
                Have an account? <a href="{{ route('login') }}">Log In</a>
            </p>
        </div>

    </div>
</div>

<script>
    function calculateAge(birthDate) {
        if(!birthDate) return;
        const today = new Date();
        const dob = new Date(birthDate);
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        document.getElementById('age').value = age;
    }

    function enableAddressFields() {
        document.getElementById('municipality').disabled = false;
        document.getElementById('barangay').disabled = false;
    }
</script>
@endsection

<script src="{{ asset('js/ph-address.js') }}"></script>