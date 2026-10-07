@extends('layouts.admin')

@section('title', 'User Accounts & Moderation - Admin Portal')

@section('content')
<div class="dash-header">
    <div>
        <h1 class="dash-title">Platform Account Moderation & Governance</h1>
        <p class="dash-subtitle">Investigate policy infractions, manage account sanctions, and issue account suspensions.</p>
    </div>
</div>

@if(session('success'))
    <div class="dash-alert-success" style="margin-bottom: 1.5rem;">✅ {{ session('success') }}</div>
@endif

<div class="dash-panel">
    <div class="filter-bar-flex">
        <form action="{{ route('admin.moderation.index') }}" method="GET" style="display: flex; gap: 0.5rem; flex: 1; max-width: 500px;">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search name, email, store..." class="controls-input">
            <select name="role" class="controls-select" style="max-width: 160px;">
                <option value="">All Roles</option>
                <option value="seller" {{ $role === 'seller' ? 'selected' : '' }}>Sellers</option>
                <option value="courier" {{ $role === 'courier' ? 'selected' : '' }}>Couriers</option>
                <option value="buyer" {{ $role === 'buyer' ? 'selected' : '' }}>Buyers</option>
                <option value="sorting_center" {{ $role === 'sorting_center' ? 'selected' : '' }}>Hubs</option>
            </select>
            <button type="submit" class="dash-btn-sm dash-btn-primary">Filter</button>
        </form>
    </div>

    <div class="dash-table-wrapper">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Account Name</th>
                    <th>Role</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Sanction / Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>
                            <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>
                            @if($user->business_name)
                                <div style="font-size: 0.75rem; color: var(--dash-primary);">{{ $user->business_name }}</div>
                            @endif
                        </td>
                        <td><span class="badge-target-role">{{ strtoupper(str_replace('_', ' ', $user->role)) }}</span></td>
                        <td>
                            {{ $user->email }}
                            <div style="font-size: 0.75rem; color: var(--dash-text-muted);">{{ $user->contact_no }}</div>
                        </td>
                        <td>
                            @if($user->status === 'suspended')
                                <span class="status-pill status-badge-suspended">SUSPENDED</span>
                                <div style="font-size: 0.75rem; color: var(--dash-danger); margin-top: 0.25rem;">
                                    {{ $user->suspension_reason }}
                                </div>
                            @else
                                <span class="status-pill status-{{ strtolower($user->status) }}">
                                    {{ strtoupper($user->status) }}
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($user->status === 'suspended')
                                <form action="{{ route('admin.moderation.reactivate', $user->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dash-btn-sm dash-btn-success">
                                        Reactivate
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('admin.moderation.suspend', $user->id) }}" method="POST" style="display: flex; gap: 0.35rem;">
                                    @csrf
                                    <input type="text" name="suspension_reason" placeholder="Violation reason..." class="controls-input" style="padding: 0.3rem 0.5rem; font-size: 0.75rem; max-width: 180px;" required>
                                    <button type="submit" class="dash-btn-sm dash-btn-danger" onclick="return confirm('Suspend this user from platform access?');">
                                        Suspend
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="dash-table-empty">No accounts match the current filter criteria.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1rem;">
        {{ $users->links() }}
    </div>
</div>
@endsection