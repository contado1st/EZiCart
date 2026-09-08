@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
@endpush

@section('content')
<div class="admin-container">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title">Account Approval Center</h1>
            <p class="admin-page-desc">Review pending applicant submissions and inspect uploaded credentials.</p>
        </div>
        <span class="badge-pending-count">
            Pending Approvals: {{ $pendingUsers->count() }}
        </span>
    </div>

    @if(session('success'))
        <div class="alert-success">
            ✅ {{ session('success') }}
        </div>
    @endif

    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Role</th>
                    <th>Contact & Address</th>
                    <th>Role Details</th>
                    <th>Verification Files</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pendingUsers as $user)
                    <tr>
                        <!-- Applicant -->
                        <td>
                            <div class="text-dark-bold">
                                {{ $user->first_name }} {{ $user->middle_initial }} {{ $user->last_name }}
                            </div>
                            <div class="text-muted-small">
                                Age: {{ $user->age }} | Sex: {{ $user->sex }}
                            </div>
                        </td>

                        <!-- Role -->
                        <td>
                            <span class="role-badge {{ $user->role === 'seller' ? 'role-seller' : ($user->role === 'courier' ? 'role-courier' : 'role-buyer') }}">
                                {{ $user->role }}
                            </span>
                        </td>

                        <!-- Contact & Address -->
                        <td>
                            <div class="contact-info">{{ $user->email }}</div>
                            <div class="text-muted-small">{{ $user->contact_no }}</div>
                            <div class="text-muted-small">
                                {{ $user->street_address }}, {{ $user->barangay }}, {{ $user->municipality }}, {{ $user->province }}
                            </div>
                        </td>

                        <!-- Role Specifics -->
                        <td>
                            @if($user->role === 'seller')
                                <div><strong>Store:</strong> {{ $user->business_name }}</div>
                                <div class="text-muted-small">Category: {{ $user->line_of_business }}</div>
                            @elseif($user->role === 'courier')
                                <div><strong>Vehicle:</strong> {{ $user->vehicle_type }}</div>
                                <div class="text-muted-small">Plate: {{ $user->plate_number }}</div>
                            @else
                                <span style="color: #94a3b8;">Standard Buyer Account</span>
                            @endif
                        </td>

                        <!-- Uploaded Document Links -->
                        <td>
                            @if($user->id_upload_path)
                                <a href="{{ asset('storage/' . $user->id_upload_path) }}" target="_blank" class="doc-link">📄 View Valid ID / License</a>
                            @endif

                            @if($user->business_permit_path)
                                <a href="{{ asset('storage/' . $user->business_permit_path) }}" target="_blank" class="doc-link">📋 View Business Permit</a>
                            @endif

                            @if($user->or_cr_upload_path)
                                <a href="{{ asset('storage/' . $user->or_cr_upload_path) }}" target="_blank" class="doc-link">🛵 View Vehicle OR/CR</a>
                            @endif
                        </td>

                        <!-- Action Forms -->
                        <td>
                            <div class="action-buttons">
                                <form action="{{ route('admin.registrations.approve', $user->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" onclick="return confirm('Approve this account?')" class="btn-approve">Approve</button>
                                </form>

                                <form action="{{ route('admin.registrations.reject', $user->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" onclick="return confirm('Reject this account?')" class="btn-reject">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty-row">
                            No pending user applications found. All registrations are processed!
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection