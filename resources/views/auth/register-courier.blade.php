@extends('layouts.app')

@section('content')
<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 1050px;">
        
        <!-- Branding Hero Panel -->
        <div class="auth-branding">
            <span class="section-tag">Courier Registration</span>
            <h1 class="auth-brand-title">Deliver & Earn with EZiCart</h1>
            <p class="hero-text">Join our active delivery network, fulfill local store orders, and track your daily payouts.</p>
            <ul class="auth-feature-list">
                <li class="auth-feature-item">
                    <span class="auth-feature-icon">🛵</span>
                    Flexible delivery routes in your local area
                </li>
                <li class="auth-feature-item">
                    <span class="auth-feature-icon">📋</span>
                    Direct coordination with Sorting Center dispatchers
                </li>
            </ul>
        </div>

        <!-- Form Panel -->
        <div class="auth-form-container">
            <!-- Role Switcher -->
            <div class="role-selector">
                <a href="{{ route('register') }}" class="role-tab">🛍️ Buyer</a>
                <a href="{{ route('register.seller') }}" class="role-tab">🏪 Seller</a>
                <a href="{{ route('register.courier') }}" class="role-tab active">🚚 Courier</a>
            </div>

            <h2 class="auth-title">Sign Up as Courier</h2>

            <div class="form-notice">
                ℹ️ After submitting your registration, please wait for approval from the Logistic/Sorting Center sent to your email.
            </div>

            <form action="{{ route('register.courier.post') }}" method="POST" enctype="multipart/form-data">
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
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
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
                        <input type="date" id="birthday" name="birthday" class="form-control" required onchange="calculateAge(this.value)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Age (Auto-generated) *</label>
                        <input type="number" id="age" name="age" class="form-control" readonly required placeholder="Auto-calculated">
                    </div>

                    <!-- Vehicle Details -->
                    <div class="form-group">
                        <label class="form-label">Choose Vehicle *</label>
                        <select name="vehicle_type" class="form-control" required>
                            <option value="">Select Vehicle Type</option>
                            <option value="Motorcycle">Motorcycle</option>
                            <option value="Van">Van</option>
                            <option value="Truck">Truck</option>
                            <option value="Multicab">Multicab</option>
                            <option value="Bicycle">Bicycle</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Enter Plate Number *</label>
                        <input type="text" name="plate_number" class="form-control" placeholder="e.g. ABC 1234" required value="{{ old('plate_number') }}">
                    </div>

                    <!-- Address -->
                    <div class="form-group">
                        <label class="form-label">Province *</label>
                        <select name="province" class="form-control" required>
                            <option value="Laguna">Laguna</option>
                            <option value="Metro Manila">Metro Manila</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Municipality *</label>
                        <select name="municipality" class="form-control" required>
                            <option value="Majayjay">Majayjay</option>
                            <option value="Santa Cruz">Santa Cruz</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Barangay *</label>
                        <select name="barangay" class="form-control" required>
                            <option value="Poblacion">Poblacion</option>
                            <option value="San Roque">San Roque</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Street Address / House No. *</label>
                        <input type="text" name="street_address" class="form-control" placeholder="123 Main St" required value="{{ old('street_address') }}">
                    </div>

                    <!-- Required Uploads -->
                    <div class="form-group">
                        <label class="form-label">Upload Driver's License / ID *</label>
                        <input type="file" name="id_upload" class="form-control" accept="image/*,.pdf" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Upload Vehicle OR/CR *</label>
                        <input type="file" name="or_cr_upload" class="form-control" accept="image/*,.pdf" required>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit" style="margin-top: 1rem;">Submit Courier Registration</button>
            </form>

            <p class="auth-footer-text">
                Already registered? <a href="{{ route('login') }}">Log In</a>
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
</script>
@endsection