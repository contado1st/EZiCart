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
                <span class="dash-role-tag">Courier Portal</span>
                <h2 class="dash-profile-title">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h2>
                <p class="dash-profile-subtitle">Vehicle: {{ auth()->user()->vehicle_type ?? 'Motorcycle' }} ({{ auth()->user()->plate_number ?? 'N/A' }})</p>
            </div>

            <nav class="dash-nav">
                <a href="{{ route('courier.dashboard') }}" class="dash-nav-item active">
                    🚚 Dispatch Board
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

    <!-- Main Dispatch Board -->
    <main class="dash-main">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Courier Dispatch Board</h1>
                <p class="dash-subtitle">Handle merchant pickups and doorstep deliveries assigned by the Sorting Center.</p>
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

        <!-- Courier Metrics Grid -->
        <div class="dash-stats-grid">
            <div class="dash-stat-card">
                <div class="dash-stat-label">Available Pickups</div>
                <div class="dash-stat-value primary">{{ $stats['available_pickups'] }}</div>
                <div class="dash-stat-subtext">Waiting at seller shops</div>
            </div>

            <div class="dash-stat-card">
                <div class="dash-stat-label">Parcels En Route to Hub</div>
                <div class="dash-stat-value warning">{{ $stats['in_transit_hub'] }}</div>
                <div class="dash-stat-subtext">Delivering to Sorting Center</div>
            </div>

            <div class="dash-stat-card">
                <div class="dash-stat-label">Doorstep Assignments</div>
                <div class="dash-stat-value primary">{{ $stats['assigned_delivery'] }}</div>
                <div class="dash-stat-subtext">Assigned by Sorting Center</div>
            </div>

            <div class="dash-stat-card">
                <div class="dash-stat-label">Completed Drops</div>
                <div class="dash-stat-value success">{{ $stats['completed'] }}</div>
                <div class="dash-stat-subtext success">Successful deliveries</div>
            </div>
        </div>

        <div class="courier-grid">
            <!-- SECTION 1: DOORSTEP DELIVERIES ASSIGNED TO THIS RIDER -->
            <div class="dash-panel">
                <div class="courier-section-header">
                    <h2 class="courier-section-title">🛵 Doorstep Delivery Assignments ({{ $myDeliveryAssignments->count() }})</h2>
                </div>
                <p style="font-size: 0.8125rem; color: var(--dash-text-muted); margin-bottom: 1rem;">
                    Parcels sorted and assigned to you by the Logistics Center for customer drop-off.
                </p>

                @forelse($myDeliveryAssignments as $order)
                    <div class="order-card">
                        <div class="order-card-header">
                            <div>
                                <span class="order-number">{{ $order->order_number }}</span>
                                <span class="area-tag" style="margin-left: 0.5rem;">{{ $order->delivery_area }}</span>
                            </div>
                            <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                                {{ str_replace('_', ' ', $order->status) }}
                            </span>
                        </div>

                        <div class="courier-route-block">
                            <div class="courier-route-col">
                                <span class="courier-route-label">Customer Recipient</span>
                                <span class="courier-route-name">{{ $order->recipient_name }}</span>
                                <span>{{ $order->street_address }}, {{ $order->barangay }}, {{ $order->municipality }}</span>
                                <span>Contact: {{ $order->recipient_contact }}</span>
                            </div>
                            <div class="courier-route-col">
                                <span class="courier-route-label">Collect Amount</span>
                                <span class="courier-route-name">₱{{ number_format($order->total_amount, 2) }}</span>
                                <span>Payment Method: <strong>{{ $order->payment_method }}</strong></span>
                            </div>
                        </div>

                        <div class="order-card-footer">
                            <div>
                                Notes: {{ $order->notes ?? 'Standard delivery.' }}
                            </div>
                            <div class="courier-actions-wrap">
                                @if($order->status === 'ASSIGNED_TO_RIDER')
                                    <form action="{{ route('courier.orders.startDelivery', $order->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dash-btn-sm dash-btn-primary">
                                            🛵 Pick Up from Hub & Start Delivery
                                        </button>
                                    </form>
                                @elseif($order->status === 'OUT_FOR_DELIVERY')
                                    <form action="{{ route('courier.orders.completeDelivery', $order->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="DELIVERED">
                                        <button type="submit" class="dash-btn-sm dash-btn-primary">
                                            ✅ Mark Delivered
                                        </button>
                                    </form>

                                    <form action="{{ route('courier.orders.completeDelivery', $order->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="DELIVERY_FAILED">
                                        <button type="submit" class="dash-btn-sm dash-btn-danger">
                                            ❌ Delivery Failed
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="dash-empty-box">No delivery assignments from the Sorting Center right now.</div>
                @endforelse
            </div>

            <!-- SECTION 2: MY ACTIVE PICKUPS EN ROUTE TO SORTING CENTER -->
            <div class="dash-panel">
                <div class="courier-section-header">
                    <h2 class="courier-section-title">📦 Seller Pickups in Your Custody ({{ $myActivePickups->count() }})</h2>
                </div>
                <p style="font-size: 0.8125rem; color: var(--dash-text-muted); margin-bottom: 1rem;">
                    Parcels collected from merchants that must be brought to the Sorting Center.
                </p>

                @forelse($myActivePickups as $order)
                    <div class="order-card">
                        <div class="order-card-header">
                            <div>
                                <span class="order-number">{{ $order->order_number }}</span>
                                <div class="order-date">Collected from {{ $order->seller->business_name }}</div>
                            </div>
                            <span class="status-pill status-picked-up">In Transit to Hub</span>
                        </div>
                        <div style="font-size: 0.8125rem; color: var(--dash-text-muted);">
                            Bring this package to the Logistics / Sorting Center for barcode scanning and destination sorting.
                        </div>
                    </div>
                @empty
                    <div class="dash-empty-box">You have no parcels in transit to the hub.</div>
                @endforelse
            </div>

            <!-- SECTION 3: AVAILABLE PICKUPS FROM MERCHANTS -->
            <div class="dash-panel">
                <div class="courier-section-header">
                    <h2 class="courier-section-title">📍 Available Seller Pickups ({{ $availablePickups->count() }})</h2>
                </div>
                <p style="font-size: 0.8125rem; color: var(--dash-text-muted); margin-bottom: 1rem;">
                    Merchants who have packed orders and generated waybills awaiting courier collection.
                </p>

                @forelse($availablePickups as $order)
                    <div class="order-card">
                        <div class="order-card-header">
                            <div>
                                <span class="order-number">{{ $order->order_number }}</span>
                                <div class="order-date">Store: <strong>{{ $order->seller->business_name }}</strong></div>
                            </div>
                            <span class="status-pill status-ready-for-pickup">Ready for Pickup</span>
                        </div>

                        <div class="courier-route-block">
                            <div class="courier-route-col">
                                <span class="courier-route-label">Merchant Address</span>
                                <span>{{ $order->seller->street_address }}, {{ $order->seller->barangay }}, {{ $order->seller->municipality }}</span>
                                <span>Contact: {{ $order->seller->contact_no }}</span>
                            </div>
                            <div class="courier-route-col">
                                <span class="courier-route-label">Destination Municipality</span>
                                <span>{{ $order->municipality }}, {{ $order->province }}</span>
                            </div>
                        </div>

                        <div class="order-card-footer">
                            <div>{{ $order->items->count() }} item(s) in package</div>
                            <form action="{{ route('courier.orders.claim', $order->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="dash-btn-sm dash-btn-primary">
                                    Claim & Pick Up from Seller
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="dash-empty-box">No seller pickup requests available at the moment.</div>
                @endforelse
            </div>
        </div>
    </main>
</div>
@endsection