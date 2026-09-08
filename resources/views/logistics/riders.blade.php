@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endpush

@section('content')
<div class="dash-wrapper">
    <aside class="dash-sidebar">
        <div>
            <div class="dash-profile-badge">
                <span class="dash-role-tag dash-role-logistics">Logistics Hub</span>
                <h2 class="dash-profile-title">{{ auth()->user()->business_name ?? 'Sorting Center' }}</h2>
            </div>
            <nav class="dash-nav">
                <a href="{{ route('logistics.dashboard') }}" class="dash-nav-item">
                    📦 Sorting & Dispatch
                </a>
                <a href="{{ route('logistics.riders') }}" class="dash-nav-item active">
                    🛵 Rider Management
                </a>
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
                <h1 class="dash-title">Courier & Rider Verification</h1>
                <p class="dash-subtitle">Review, approve, or deactivate delivery riders for this logistics hub[cite: 1].</p>
            </div>
        </div>

        @if(session('success'))
            <div class="dash-alert-success">✅ {{ session('success') }}</div>
        @endif

        <div class="dash-panel">
            <h2 class="courier-section-title">Pending Rider Applications ({{ $pendingRiders->count() }})</h2>
            
            @forelse($pendingRiders as $rider)
                <div class="dash-approval-card" style="margin-top: 1rem;">
                    <div class="dash-approval-row">
                        <div>
                            <div class="dash-approval-name">{{ $rider->first_name }} {{ $rider->last_name }}</div>
                            <div class="dash-approval-email">{{ $rider->email }} &bull; Contact: {{ $rider->contact_no }}</div>
                            <div style="font-size: 0.8125rem; margin-top: 0.25rem;">
                                Vehicle: <strong>{{ $rider->vehicle_type ?? 'Motorcycle' }}</strong> (Plate: {{ $rider->plate_number ?? 'N/A' }})
                            </div>
                        </div>
                        <div class="dash-approval-actions">
                            <form action="{{ route('logistics.riders.approve', $rider->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="dash-btn-sm dash-btn-success">Approve Rider[cite: 1]</button>
                            </form>
                            <form action="{{ route('logistics.riders.reject', $rider->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="dash-btn-sm dash-btn-danger">Reject</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="dash-empty-box" style="margin-top: 0.75rem;">No pending rider registrations.</div>
            @endforelse
        </div>

        <div class="dash-panel" style="margin-top: 1.5rem;">
            <h2 class="courier-section-title">Approved Fleet ({{ $approvedRiders->count() }})</h2>
            <div class="dash-table-wrapper" style="margin-top: 0.75rem;">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Rider Name</th>
                            <th>Contact</th>
                            <th>Vehicle</th>
                            <th>Plate No</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($approvedRiders as $rider)
                            <tr>
                                <td><strong>{{ $rider->first_name }} {{ $rider->last_name }}</strong></td>
                                <td>{{ $rider->contact_no }}</td>
                                <td>{{ $rider->vehicle_type ?? 'Motorcycle' }}</td>
                                <td>{{ $rider->plate_number ?? 'N/A' }}</td>
                                <td><span class="status-pill status-completed">Active</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="dash-table-empty">No active riders approved yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
@endsection