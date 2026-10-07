@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endpush

@section('content')
<div class="dash-wrapper">
    <!-- Buyer Sidebar -->
    <aside class="dash-sidebar">
        <div>
            <div class="dash-profile-badge">
                <span class="dash-role-tag">Buyer Portal</span>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.5rem;">
                    <img src="{{ auth()->user()->profile_photo_url }}" alt="Profile" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover;">
                    <div>
                        <h2 class="dash-profile-title" style="margin: 0; font-size: 1rem;">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h2>
                        <p class="dash-profile-subtitle" style="margin: 0;">Member since {{ auth()->user()->created_at->format('M Y') }}</p>
                    </div>
                </div>
            </div>

            <nav class="dash-nav">
                <a href="{{ route('buyer.dashboard') }}" class="dash-nav-item">
                    📦 My Orders
                </a>
                <a href="{{ route('cart.index') }}" class="dash-nav-item">
                    🛒 My Cart
                </a>
                <a href="{{ route('buyer.profile') }}" class="dash-nav-item active">
                    👤 My Profile
                </a>
                <a href="{{ route('buyer.addresses.index') }}" class="dash-nav-item">
                    📍 Delivery Addresses
                </a>
                <a href="{{ route('buyer.password') }}" class="dash-nav-item">
                    🔒 Change Password
                </a>
            </nav>
        </div>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="dash-logout-btn">
                🚪 Logout
            </button>
        </form>
    </aside>

    <!-- Main Workspace -->
    <main class="dash-main">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">My Profile</h1>
                <p class="dash-subtitle">Manage your personal information and profile picture.</p>
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

        <div class="dash-panel" style="max-width: 750px;">
            <form action="{{ route('buyer.profile.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <!-- Profile Photo Row -->
                <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--slate-200);">
                    <img id="avatarPreview" src="{{ $user->profile_photo_url }}" alt="Profile Photo" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--slate-200); box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    <div>
                        <label for="profile_photo" class="dash-btn-sm dash-btn-outline" style="cursor: pointer; display: inline-block; margin-bottom: 0.25rem;">
                            📷 Change Picture
                        </label>
                        <input type="file" name="profile_photo" id="profile_photo" accept="image/*" style="display: none;" onchange="previewAvatar(this)">
                        <div style="font-size: 0.75rem; color: var(--slate-500);">JPEG, PNG, JPG, or WEBP up to 3MB.</div>
                    </div>
                </div>

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
                        <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">Phone Number *</label>
                        <input type="text" name="contact_no" class="form-control" value="{{ old('contact_no', $user->contact_no) }}" required>
                    </div>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">Email Address</label>
                    <input type="email" class="form-control" value="{{ $user->email }}" disabled style="background: var(--slate-100); color: var(--slate-500); cursor: not-allowed;">
                    <span style="font-size: 0.75rem; color: var(--slate-400);">Email address is associated with your account credentials.</span>
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
                        💾 Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
    function previewAvatar(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatarPreview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endsection

