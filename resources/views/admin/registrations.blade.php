@extends('layouts.app')

@section('content')
<div class="container" style="padding: 2rem 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--slate-900);">Account Approval Center</h1>
            <p style="font-size: 0.875rem; color: var(--slate-500);">Review pending applications, inspect uploaded credentials, and approve or reject user accounts[cite: 1].</p>
        </div>
        <span style="background-color: var(--ezipink-50); color: var(--ezipink-600); padding: 0.5rem 1rem; border-radius: 9999px; font-weight: 700; font-size: 0.875rem;">
            Pending Approvals: {{ $pendingUsers->count() }}
        </span>
    </div>

    @if(session('success'))
        <div class="form-notice" style="border-left-color: #10b981; background-color: #ecfdf5; color: #065f46; margin-bottom: 1.5rem;">
            {{ session('success') }}
        </div>
    @endif

    <div style="background-color: #ffffff; border: 1px solid var(--slate-200); border-radius: 0.75rem; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
            <thead style="background-color: var(--slate-50); border-bottom: 1px solid var(--slate-200);">
                <tr>
                    <th style="padding: 1rem;">Applicant</th>
                    <th style="padding: 1rem;">Role</th>
                    <th style="padding: 1rem;">Contact & Address</th>
                    <th style="padding: 1rem;">Role Specifics</th>
                    <th style="padding: 1rem;">Submitted Verification Docs</th>
                    <th style="padding: 1rem; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pendingUsers as $user)
                    <tr style="border-bottom: 1px solid var(--slate-100);">
                        <!-- Applicant Name -->
                        <td style="padding: 1rem;">
                            <div style="font-weight: 700; color: var(--slate-800);">
                                {{ $user->first_name }} {{ $user->middle_initial }} {{ $user->last_name }}
                            </div>
                            <div style="font-size: 0.75rem; color: var(--slate-500);">
                                Age: {{ $user->age }} | Sex: {{ $user->sex }}
                            </div>
                        </td>

                        <!-- Role Badge -->
                        <td style="padding: 1rem;">
                            <span style="display: inline-block; padding: 0.25rem 0.625rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
                                background-color: {{ $user->role === 'seller' ? '#fef3c7' : ($user->role === 'courier' ? '#e0f2fe' : '#f3e8ff') }};
                                color: {{ $user->role === 'seller' ? '#92400e' : ($user->role === 'courier' ? '#075985' : '#6b21a8') }};">
                                {{ $user->role }}
                            </span>
                        </td>

                        <!-- Contact & Address -->
                        <td style="padding: 1rem;">
                            <div>{{ $user->email }}</div>
                            <div style="font-size: 0.75rem; color: var(--slate-500);">{{ $user->contact_no }}</div>
                            <div style="font-size: 0.75rem; color: var(--slate-500);">
                                {{ $user->street_address }}, {{ $user->barangay }}, {{ $user->municipality }}, {{ $user->province }}
                            </div>
                        </td>

                        <!-- Role Details -->
                        <td style="padding: 1rem;">
                            @if($user->role === 'seller')
                                <div><strong>Store:</strong> {{ $user->business_name }}</div>
                                <div style="font-size: 0.75rem; color: var(--slate-500);">Category: {{ $user->line_of_business }}</div>
                            @elseif($user->role === 'courier')
                                <div><strong>Vehicle:</strong> {{ $user->vehicle_type }}</div>
                                <div style="font-size: 0.75rem; color: var(--slate-500);">Plate: {{ $user->plate_number }}</div>
                            @else
                                <span style="color: var(--slate-400);">Standard Buyer Account</span>
                            @endif
                        </td>

                        <!-- Document Upload Links -->
                        <td style="padding: 1rem;">
                            <div style="display: flex; flex-direction: column; gap: 0.375rem;">
                                @if($user->id_upload_path)
                                    <a href="{{ asset('storage/' . $user->id_upload_path) }}" target="_blank" style="color: var(--ezipink-600); font-size: 0.75rem; font-weight: 600; text-decoration: underline;">
                                        📄 View Valid ID / License
                                    </a>
                                @endif

                                @if($user->business_permit_path)
                                    <a href="{{ asset('storage/' . $user->business_permit_path) }}" target="_blank" style="color: var(--ezipink-600); font-size: 0.75rem; font-weight: 600; text-decoration: underline;">
                                        📋 View Business Permit
                                    </a>
                                @endif

                                @if($user->or_cr_upload_path)
                                    <a href="{{ asset('storage/' . $user->or_cr_upload_path) }}" target="_blank" style="color: var(--ezipink-600); font-size: 0.75rem; font-weight: 600; text-decoration: underline;">
                                        🛵 View Vehicle OR/CR
                                    </a>
                                @endif
                            </div>
                        </td>

                        <!-- Approve / Reject Forms -->
                        <td style="padding: 1rem; text-align: right;">
                            <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                                <form action="{{ route('admin.registrations.approve', $user->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" style="background-color: #10b981; color: #ffffff; border: none; padding: 0.375rem 0.75rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 700; cursor: pointer;">
                                        Approve
                                    </button>
                                </form>

                                <form action="{{ route('admin.registrations.reject', $user->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" style="background-color: #ef4444; color: #ffffff; border: none; padding: 0.375rem 0.75rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 700; cursor: pointer;">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 3rem; text-align: center; color: var(--slate-400);">
                            No pending registrations found. All user applications have been processed!
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection