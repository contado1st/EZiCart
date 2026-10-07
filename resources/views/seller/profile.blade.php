@extends('layouts.seller')

@section('title', 'Store & Profile - EZiCart Seller')

@section('content')
<div class="dash-header">
    <div>
        <h1 class="dash-title">Store & Merchant Profile</h1>
        <p class="dash-subtitle">Manage your business brand details, contact information, and store avatar.</p>
    </div>
</div>

@if(session('success'))
    <div class="dash-alert-success">✅ {{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="dash-alert-success" style="border-left-color: var(--dash-danger); background-color: var(--dash-danger-bg); color: var(--dash-danger);">
        <ul style="margin: 0; padding-left: 1rem;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="dash-panel" style="max-width: 800px;">
    <form action="{{ route('seller.profile.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <!-- Store Avatar Row -->
        <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--slate-200);">
            <img id="sellerAvatarPreview" src="{{ $user->profile_photo_url }}" alt="Store Logo" style="width: 85px; height: 85px; border-radius: 50%; object-fit: cover; border: 3px solid var(--ez-primary); box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
            <div>
                <label for="profile_photo" class="dash-btn-sm dash-btn-outline" style="cursor: pointer; display: inline-block; margin-bottom: 0.25rem;">
                    📷 Change Store Picture
                </label>
                <input type="file" name="profile_photo" id="profile_photo" accept="image/*" style="display: none;" onchange="previewSellerAvatar(this)">
                <div style="font-size: 0.75rem; color: var(--slate-500);">JPEG, PNG, or WEBP up to 3MB. Displayed on your store profile.</div>
            </div>
        </div>

        <h3 style="font-size: 1rem; font-weight: 800; color: var(--slate-850); margin-bottom: 1rem;">
            🏬 Store Information
        </h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">Business / Store Name *</label>
                <input type="text" name="business_name" class="form-control" value="{{ old('business_name', $user->business_name) }}" required>
            </div>
            <div>
                <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">Line of Business / Category *</label>
                <input type="text" name="line_of_business" class="form-control" value="{{ old('line_of_business', $user->line_of_business) }}" required>
            </div>
        </div>

        <h3 style="font-size: 1rem; font-weight: 800; color: var(--slate-850); margin-bottom: 1rem; padding-top: 1rem; border-top: 1px solid var(--slate-100);">
            👤 Merchant Owner Details
        </h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">First Name *</label>
                <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $user->first_name) }}" required>
            </div>
            <div>
                <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">Last Name *</label>
                <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $user->last_name) }}" required>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">Middle Initial</label>
                <input type="text" name="middle_initial" maxlength="5" class="form-control" value="{{ old('middle_initial', $user->middle_initial) }}">
            </div>
            <div>
                <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">Contact Number *</label>
                <input type="text" name="contact_no" class="form-control" value="{{ old('contact_no', $user->contact_no) }}" required>
            </div>
        </div>

        <div style="margin-bottom: 1rem;">
            <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">Email Address</label>
            <input type="email" class="form-control" value="{{ $user->email }}" disabled style="background: var(--slate-100); color: var(--slate-500); cursor: not-allowed;">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">Gender *</label>
                <select name="sex" class="form-control" required>
                    <option value="Male" {{ old('sex', $user->sex) === 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ old('sex', $user->sex) === 'Female' ? 'selected' : '' }}>Female</option>
                    <option value="Other" {{ old('sex', $user->sex) === 'Other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>
            <div>
                <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">Date of Birth *</label>
                <input type="date" name="birthday" class="form-control" value="{{ old('birthday', $user->birthday ? \Carbon\Carbon::parse($user->birthday)->format('Y-m-d') : '') }}" required>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end;">
            <button type="submit" class="dash-btn-primary" style="padding: 0.65rem 1.75rem; font-size: 0.9rem;">
                💾 Save Store Details
            </button>
        </div>
    </form>
</div>

<script>
    function previewSellerAvatar(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('sellerAvatarPreview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endsection

