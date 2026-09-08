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
                <h1 class="dash-title">Logistics & Delivery Dispatch</h1>
                <p class="dash-subtitle">Accept packages from sellers and manage doorstep drop-offs.</p>
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
                <div class="dash-stat-value primary">{{ $stats['available'] }}</div>
                <div class="dash-stat-subtext">Waiting at seller hubs</div>
            </div>

            <div class="dash-stat-card">
                <div class="dash-stat-label">In My Custody</div>
                <div class="dash-stat-value warning">{{ $stats['active'] }}</div>
                <div class="dash-stat-subtext">Currently out in transit</div>
            </div>

            <div class="dash-stat-card">
                <div class="dash-stat-label">Successful Deliveries</div>
                <div class="dash-stat-value success">{{ $stats['delivered'] }}</div>
                <div class="dash-stat-subtext success">Completed drop-offs</div>
            </div>

            <div class="dash-stat-card">
                <div class="dash-stat-label">Failed Deliveries</div>
                <div class="dash-stat-value danger">{{ $stats['failed'] }}</div>
                <div class="dash-stat-subtext danger">Unsuccessful attempts</div>
            </div>
        </div>

        <div class="courier-grid">
            <!-- 1. Active Deliveries Assigned to Current Courier -->
            <div class="dash-panel">
                <div class="courier-section-header">
                    <h2 class="courier-section-title">🚚 Active Deliveries in My Custody ({{ $activeDeliveries->count() }})</h2>
                </div>

                @forelse($activeDeliveries as $order)
                    <div class="order-card">
                        <div class="order-card-header">
                            <div>
                                <span class="order-number">{{ $order->order_number }}</span>
                                <div class="order-date">Claimed on {{ $order->updated_at->format('M d, Y h:i A') }}</div>
                            </div>
                            <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                                {{ str_replace('_', ' ', $order->status) }}
                            </span>
                        </div>

                        <div class="courier-route-block">
                            <div class="courier-route-col">
                                <span class="courier-route-label">Pickup Hub (Seller)</span>
                                <span class="courier-route-name">{{ $order->seller->business_name ?? 'Seller Store' }}</span>
                                <span>{{ $order->seller->street_address }}, {{ $order->seller->barangay }}, {{ $order->seller->municipality }}</span>
                                <span>Contact: {{ $order->seller->contact_no }}</span>
                            </div>

                            <div class="courier-route-col">
                                <span class="courier-route-label">Delivery Destination (Buyer)</span>
                                <span class="courier-route-name">{{ $order->recipient_name }}</span>
                                <span>{{ $order->street_address }}, {{ $order->barangay }}, {{ $order->municipality }}</span>
                                <span>Contact: {{ $order->recipient_contact }}</span>
                            </div>
                        </div>

                        <div class="order-card-footer">
                            <div class="order-total-block">
                                Collect Payment: <strong>₱{{ number_format($order->total_amount, 2) }} ({{ $order->payment_method }})</strong>
                            </div>

                            <div class="courier-actions-wrap">
                                @if($order->status === 'PICKED_UP')
                                    <form action="{{ route('courier.orders.updateStatus', $order->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="OUT_FOR_DELIVERY">
                                        <button type="submit" class="dash-btn-sm dash-btn-primary">
                                            Start Delivery Trip
                                        </button>
                                    </form>
                                @elseif($order->status === 'OUT_FOR_DELIVERY')
                                    <form action="{{ route('courier.orders.updateStatus', $order->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="DELIVERED">
                                        <button type="submit" class="dash-btn-sm dash-btn-primary">
                                            ✅ Mark as Delivered
                                        </button>
                                    </form>

                                    <form action="{{ route('courier.orders.updateStatus', $order->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="DELIVERY_FAILED">
                                        <button type="submit" class="dash-btn-sm dash-btn-danger">
                                            ❌ Failed Attempt
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="dash-empty-box">
                        📦 You currently have no parcels in transit. Claim available pickups below to start delivering.
                    </div>
                @endforelse
            </div>

            <!-- 2. Available Pickups Queue -->
            <div class="dash-panel">
                <div class="courier-section-header">
                    <h2 class="courier-section-title">📍 Ready for Pickup Queue ({{ $availablePickups->count() }})</h2>
                </div>

                @forelse($availablePickups as $order)
                    <div class="order-card">
                        <div class="order-card-header">
                            <div>
                                <span class="order-number">{{ $order->order_number }}</span>
                                <div class="order-date">Packed & Ready since {{ $order->updated_at->format('M d, Y h:i A') }}</div>
                            </div>
                            <span class="status-pill status-ready-for-pickup">
                                Ready for Pickup
                            </span>
                        </div>

                        <div class="courier-route-block">
                            <div class="courier-route-col">
                                <span class="courier-route-label">Merchant / Pickup Address</span>
                                <span class="courier-route-name">{{ $order->seller->business_name ?? 'Seller Store' }}</span>
                                <span>{{ $order->seller->street_address }}, {{ $order->seller->barangay }}, {{ $order->seller->municipality }}</span>
                            </div>

                            <div class="courier-route-col">
                                <span class="courier-route-label">Buyer / Delivery Destination</span>
                                <span class="courier-route-name">{{ $order->recipient_name }}</span>
                                <span>{{ $order->municipality }}, {{ $order->province }}</span>
                            </div>
                        </div>

                        <div class="order-card-footer">
                            <div class="order-total-block">
                                Package Value: <strong>₱{{ number_format($order->total_amount, 2) }}</strong> &bull; Items: {{ $order->items->count() }}
                            </div>

                            <form action="{{ route('courier.orders.claim', $order->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="dash-btn-sm dash-btn-primary">
                                    Claim & Pick Up Package
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="dash-empty-box">
                        ✅ No packages are awaiting courier pickup right now.
                    </div>
                @endforelse
            </div>
        </div>
    </main>
</div>
@endsection