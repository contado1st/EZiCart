@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endpush

@section('content')
<div class="dash-wrapper">
    <!-- Sidebar -->
    <aside class="dash-sidebar">
        <div>
            <div class="dash-profile-badge">
                <span class="dash-role-tag dash-role-logistics">Logistics Hub</span>
                <h2 class="dash-profile-title">{{ auth()->user()->business_name ?? 'Sorting Center' }}</h2>
                <p class="dash-profile-subtitle">{{ auth()->user()->municipality }}, {{ auth()->user()->province }}</p>
            </div>

            <nav class="dash-nav">
                <a href="{{ route('logistics.dashboard') }}" class="dash-nav-item active">
                    📦 Sorting & Dispatch
                </a>
                <a href="{{ route('logistics.riders') }}" class="dash-nav-item">
                    🛵 Rider Management
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
                <h1 class="dash-title">Logistics & Parcel Sorting Center</h1>
                <p class="dash-subtitle">Receive inbound pickups, sort by destination area, and dispatch to riders[cite: 2].</p>
            </div>
        </div>

        @if(session('success'))
            <div class="dash-alert-success">✅ {{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="dash-alert-success" style="border-left-color: var(--dash-danger); background-color: var(--dash-danger-bg); color: var(--dash-danger);">
                ⚠️ {{ session('error') }}
            </div>
        @endif

        <!-- Stats Grid -->
        <div class="dash-stats-grid">
            <div class="dash-stat-card">
                <div class="dash-stat-label">Inbound from Sellers</div>
                <div class="dash-stat-value primary">{{ $stats['inbound'] }}</div>
                <div class="dash-stat-subtext">Parcels carried by pickup riders[cite: 2]</div>
            </div>

            <div class="dash-stat-card">
                <div class="dash-stat-label">At Sorting Center</div>
                <div class="dash-stat-value warning">{{ $stats['at_center'] }}</div>
                <div class="dash-stat-subtext">Awaiting area categorization[cite: 2]</div>
            </div>

            <div class="dash-stat-card">
                <div class="dash-stat-label">Sorted Parcels</div>
                <div class="dash-stat-value">{{ $stats['sorted'] }}</div>
                <div class="dash-stat-subtext">Ready for rider assignment[cite: 2]</div>
            </div>

            <div class="dash-stat-card">
                <div class="dash-stat-label">Out on Delivery</div>
                <div class="dash-stat-value success">{{ $stats['dispatched'] }}</div>
                <div class="dash-stat-subtext success">Assigned or traveling to buyer[cite: 2]</div>
            </div>
        </div>

        <!-- 1. Incoming Parcels (Scan Arrival) -->
        <div class="dash-panel">
            <h2 class="courier-section-title">1. Inbound Parcels En Route to Hub ({{ $inboundParcels->count() }})</h2>
            <p style="font-size: 0.8125rem; color: var(--dash-text-muted); margin-bottom: 1rem;">
                Parcels picked up from merchants. Click "Scan & Receive" upon arrival[cite: 2].
            </p>

            @forelse($inboundParcels as $order)
                <div class="logistics-card">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong>{{ $order->order_number }}</strong>
                            <div style="font-size: 0.75rem; color: var(--dash-text-muted);">
                                Merchant: {{ $order->seller->business_name }} &bull; Courier: {{ $order->pickupCourier->first_name ?? 'N/A' }}
                            </div>
                        </div>
                        <form action="{{ route('logistics.orders.receive', $order->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="dash-btn-sm dash-btn-primary">
                                📥 Scan & Receive at Hub
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="dash-empty-box">No inbound shipments currently in transit to this facility.</div>
            @endforelse
        </div>

        <!-- 2. Sort by Destination Area -->
        <div class="dash-panel" style="margin-top: 1.5rem;">
            <h2 class="courier-section-title">2. Sort Parcels by Destination ({{ $atCenterParcels->count() }})</h2>
            <p style="font-size: 0.8125rem; color: var(--dash-text-muted); margin-bottom: 1rem;">
                Read delivery address and assign an Area Zone (e.g., Area A - Santa Cruz)[cite: 2].
            </p>

            @forelse($atCenterParcels as $order)
                <div class="logistics-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <strong>{{ $order->order_number }}</strong>
                            <div style="font-size: 0.8125rem; margin-top: 0.25rem;">
                                Destination: <strong>{{ $order->recipient_name }}</strong>, {{ $order->street_address }}, {{ $order->barangay }}, {{ $order->municipality }}, {{ $order->province }}
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 0.75rem; border-top: 1px solid var(--dash-border); padding-top: 0.75rem;">
                        <form action="{{ route('logistics.orders.sort', $order->id) }}" method="POST" class="logistics-form-inline">
                            @csrf
                            <label style="font-size: 0.8125rem; font-weight: 700;">Assign Area:</label>
                            <select name="delivery_area" class="logistics-select" required>
                                <option value="">-- Choose Area Zone --</option>
                                <option value="Area A - Santa Cruz">Area A - Santa Cruz[cite: 2]</option>
                                <option value="Area B - Pagsanjan">Area B - Pagsanjan[cite: 2]</option>
                                <option value="Area C - Los Baños">Area C - Los Baños[cite: 2]</option>
                                <option value="Area D - Majayjay">Area D - Majayjay</option>
                                <option value="Area E - Magdalena">Area E - Magdalena</option>
                            </select>
                            <button type="submit" class="dash-btn-sm dash-btn-primary">
                                Set Sorted Area
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="dash-empty-box">All parcels at center have been sorted.</div>
            @endforelse
        </div>

        <!-- 3. Assign Parcel to Area Delivery Rider -->
        <div class="dash-panel" style="margin-top: 1.5rem;">
            <h2 class="courier-section-title">3. Assign Sorted Parcels to Riders ({{ $sortedParcels->count() }})</h2>
            <p style="font-size: 0.8125rem; color: var(--dash-text-muted); margin-bottom: 1rem;">
                Dispatch sorted parcels to riders servicing that specific area[cite: 2].
            </p>

            @forelse($sortedParcels as $order)
                <div class="logistics-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <strong>{{ $order->order_number }}</strong>
                            <span class="area-tag" style="margin-left: 0.5rem;">{{ $order->delivery_area }}</span>
                            <div style="font-size: 0.8125rem; margin-top: 0.25rem;">
                                Buyer: {{ $order->recipient_name }} ({{ $order->recipient_contact }}) &bull; {{ $order->municipality }}
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 0.75rem; border-top: 1px solid var(--dash-border); padding-top: 0.75rem;">
                        <form action="{{ route('logistics.orders.assignRider', $order->id) }}" method="POST" class="logistics-form-inline">
                            @csrf
                            <label style="font-size: 0.8125rem; font-weight: 700;">Assign Delivery Rider:</label>
                            <select name="delivery_courier_id" class="logistics-select" required>
                                <option value="">-- Choose Approved Rider --</option>
                                @foreach($riders as $rider)
                                    <option value="{{ $rider->id }}">
                                        {{ $rider->first_name }} {{ $rider->last_name }} ({{ $rider->vehicle_type ?? 'Motorcycle' }})
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="dash-btn-sm dash-btn-primary">
                                🛵 Hand Over to Rider
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="dash-empty-box">No sorted parcels are awaiting rider assignment.</div>
            @endforelse
        </div>
    </main>
</div>
@endsection