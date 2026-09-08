@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/riders.css') }}">
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
                <p class="dash-subtitle">Review credentials, inspect vehicle documents, and activate delivery riders.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="dash-alert-success">✅ {{ session('success') }}</div>
        @endif

        <!-- Pending Applications -->
        <div class="dash-panel riders-panel">
            <h2 class="riders-count-title">Pending Rider Applications ({{ $pendingRiders->count() }})</h2>
            
            @forelse($pendingRiders as $rider)
                <div class="rider-applicant-card">
                    <div class="rider-applicant-info">
                        <div class="rider-applicant-name">{{ $rider->first_name }} {{ $rider->last_name }}</div>
                        <div class="rider-applicant-contact">{{ $rider->email }} &bull; Contact: {{ $rider->contact_no }}</div>
                        <div class="rider-applicant-vehicle">
                            Vehicle: <strong>{{ $rider->vehicle_type ?? 'Motorcycle' }}</strong> (Plate: {{ $rider->plate_number ?? 'N/A' }})
                        </div>

                        <!-- Verification Documents -->
                        <div class="rider-docs-toolbar">
                            @if($rider->license_path)
                                <a href="{{ asset('storage/' . $rider->license_path) }}" target="_blank" class="rider-doc-badge-link">
                                    🪪 Driver's License
                                </a>
                            @endif
                            @if($rider->or_cr_path)
                                <a href="{{ asset('storage/' . $rider->or_cr_path) }}" target="_blank" class="rider-doc-badge-link">
                                    🚗 Vehicle OR/CR
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="rider-review-actions">
                        <form action="{{ route('logistics.riders.approve', $rider->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-approve-rider">Approve Rider</button>
                        </form>
                        <form action="{{ route('logistics.riders.reject', $rider->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-reject-rider">Reject</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="dash-empty-box">No pending rider applications awaiting approval.</div>
            @endforelse
        </div>

        <!-- Approved Fleet -->
        <div class="dash-panel">
            <h2 class="riders-count-title">Active Approved Fleet ({{ $approvedRiders->count() }})</h2>
            <div class="dash-table-wrapper fleet-table-container">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Rider Name</th>
                            <th>Contact</th>
                            <th>Vehicle</th>
                            <th>Plate Number</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($approvedRiders as $rider)
                            <tr>
                                <td class="fleet-rider-name">{{ $rider->first_name }} {{ $rider->last_name }}</td>
                                <td>{{ $rider->contact_no }}</td>
                                <td>{{ $rider->vehicle_type ?? 'Motorcycle' }}</td>
                                <td>{{ $rider->plate_number ?? 'N/A' }}</td>
                                <td><span class="status-pill status-completed">Active</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="fleet-empty-row">No active riders registered to this facility yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
@endsection