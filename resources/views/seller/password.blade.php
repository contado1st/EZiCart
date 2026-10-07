@extends('layouts.seller')

@section('title', 'Change Password - EZiCart Seller')

@section('content')
<div class="dash-header">
    <div>
        <h1 class="dash-title">Security & Password</h1>
        <p class="dash-subtitle">Update your store account credentials to protect your store and finances.</p>
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

<div class="dash-panel" style="max-width: 600px;">
    <div style="background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.85rem; color: var(--slate-600); line-height: 1.5;">
        🔒 <strong>Merchant Security:</strong>
        Ensure your password is at least 8 characters long and contains uppercase letters, numbers, and symbols.
    </div>

    <form action="{{ route('seller.password.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div style="margin-bottom: 1.25rem;">
            <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">
                Current Password *
            </label>
            <input type="password" name="current_password" class="form-control" required placeholder="Enter current merchant password">
        </div>

        <div style="margin-bottom: 1.25rem;">
            <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">
                New Password *
            </label>
            <input type="password" name="new_password" class="form-control" required placeholder="Minimum 8 characters">
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: var(--slate-700); display: block; margin-bottom: 0.35rem;">
                Confirm New Password *
            </label>
            <input type="password" name="new_password_confirmation" class="form-control" required placeholder="Re-enter new password">
        </div>

        <div style="display: flex; justify-content: flex-end;">
            <button type="submit" class="dash-btn-primary" style="padding: 0.65rem 1.75rem; font-size: 0.9rem;">
                Update Password
            </button>
        </div>
    </form>
</div>
@endsection

