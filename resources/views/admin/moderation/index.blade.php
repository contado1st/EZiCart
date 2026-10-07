@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
    @vite('resources/css/shared/platform-controls.css')
@endpush

@section('content')
    <div class="dash-wrapper">
        <aside class="dash-sidebar">
            <div>
                <div class="dash-profile-badge">
                    <span class="dash-role-tag dash-role-admin">Super Admin</span>
                    <h2 class="dash-profile-title">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h2>
                </div>
                <nav class="dash-nav">
                    <a href="{{ route('admin.dashboard') }}" class="dash-nav-item">📊 Platform Overview</a>
                    <a href="{{ route('admin.registrations.index') }}" class="dash-nav-item">🛡️ User Approvals</a>
                    <a href="{{ route('admin.reports.index') }}" class="dash-nav-item">📑 Financial & Commission</a>
                    <a href="{{ route('admin.disputes.index') }}" class="dash-nav-item">⚖️ Dispute Arbitration</a>
                    <a href="{{ route('admin.announcements.index') }}" class="dash-nav-item">📢 Announcements</a>
                    <a href="{{ route('admin.moderation.index') }}" class="dash-nav-item active">🚫 Account Moderation</a>
                </nav>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="dash-logout-btn">🚪 Logout</button>
            </form>
        </aside>

        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Platform Account Moderation & Governance</h1>
                    <p class="dash-subtitle">Investigate policy infractions, manage account sanctions, and issue account
                        suspensions.</p>
                </div>
            </div>

            @if (session('success'))
                <div class="dash-alert-success">✅ {{ session('success') }}</div>
            @endif

            <div class="dash-panel">
                <div class="filter-bar-flex">
                    <form action="{{ route('admin.moderation.index') }}" method="GET" class="u-extracted-d8393f3793">
                        <input type="text" name="search" value="{{ $search }}"
                            placeholder="Search name, email, store..." class="controls-input">
                        <select name="role" class="controls-select u-extracted-de2395bfe6">
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
                                        @if ($user->business_name)
                                            <div class="u-extracted-696134c27b">
                                                {{ $user->business_name }}</div>
                                        @endif
                                    </td>
                                    <td><span
                                            class="badge-target-role">{{ strtoupper(str_replace('_', ' ', $user->role)) }}</span>
                                    </td>
                                    <td>
                                        {{ $user->email }}
                                        <div class="u-extracted-cf0c302441">
                                            {{ $user->contact_no }}</div>
                                    </td>
                                    <td>
                                        @if ($user->role === 'courier' && $user->active_pickups_count + $user->active_parcels_count > 0)
                                            @php($activeParcelCount = $user->active_pickups_count + $user->active_parcels_count)
                                            <p class="text-muted-small">Suspending this rider will open {{ $activeParcelCount }} logistics recovery {{ $activeParcelCount === 1 ? 'case' : 'cases' }}.</p>
                                        @endif
                                        @if ($user->status === 'suspended')
                                            <span class="status-pill status-badge-suspended">SUSPENDED</span>
                                            <div class="u-extracted-da01588a85">
                                                {{ $user->suspension_reason }}
                                            </div>
                                        @else
                                            <span class="status-pill status-{{ strtolower($user->status) }}">
                                                {{ strtoupper($user->status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($user->status === 'suspended')
                                            @if ($user->suspension_previous_status === 'approved')
                                                <form action="{{ route('admin.moderation.reactivate', $user->id) }}"
                                                    method="POST">
                                                    @csrf
                                                    <button type="submit"
                                                        class="dash-btn-sm dash-btn-success">Reactivate</button>
                                                </form>
                                            @else
                                                <p class="text-muted-small">Prior status is unknown. Review the account before restoring it.</p>
                                                <form action="{{ route('admin.moderation.reactivate', $user->id) }}"
                                                    method="POST">
                                                    @csrf
                                                    <label class="form-label"
                                                        for="restore-status-{{ $user->id }}">Restore status after review</label>
                                                    <select id="restore-status-{{ $user->id }}" class="controls-select"
                                                        name="restored_status" required>
                                                        <option value="" disabled selected>Choose status</option>
                                                        <option value="pending">Pending review</option>
                                                        <option value="approved">Approved after review</option>
                                                        <option value="rejected">Rejected</option>
                                                    </select>
                                                    <button type="submit" class="dash-btn-sm dash-btn-success">Restore account</button>
                                                </form>
                                            @endif
                                        @elseif ($user->status === 'approved')
                                            <form action="{{ route('admin.moderation.suspend', $user->id) }}"
                                                method="POST" class="u-extracted-6d9d6fda77">
                                                @csrf
                                                <input type="text" name="suspension_reason"
                                                    placeholder="Violation reason..."
                                                    class="controls-input u-extracted-637ac4c504" required>
                                                <button type="submit" class="dash-btn-sm dash-btn-danger"
                                                    onclick="return confirm('Suspend this user from platform access?');">Suspend</button>
                                            </form>
                                        @else
                                            <span class="text-muted-small">Registration review handles pending and rejected
                                                accounts.</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="dash-table-empty">No accounts match the current filter
                                        criteria.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="u-extracted-dab43fb936">
                    {{ $users->links() }}
                </div>
            </div>
        </main>
    </div>
@endsection
