@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
@endpush

@section('content')
    <div class="dash-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="dash-sidebar">
            <div>
                <div class="dash-profile-badge">
                    <span class="dash-role-tag">Seller Portal</span>
                    <h2 class="dash-profile-title">{{ auth()->user()->business_name ?? 'My Store' }}</h2>
                </div>
                <nav class="dash-nav">
                    <a href="{{ route('seller.dashboard') }}" class="dash-nav-item">
                        📊 Dashboard Overview
                    </a>
                    <a href="{{ route('seller.products.index') }}" class="dash-nav-item">
                        📦 Inventory Management
                    </a>
                    <a href="{{ route('seller.orders.index') }}" class="dash-nav-item active">
                        🛍️ Order Management
                    </a>
                </nav>
            </div>

            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="dash-logout-btn">🚪 Logout</button>
            </form>
        </aside>

        <!-- Main Workspace -->
        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Order Fulfillment & Dispatch</h1>
                    <p class="dash-subtitle">Accept incoming buyer orders, pack packages, and generate waybills.</p>
                </div>
            </div>

            @if (session('success'))
                <div class="dash-alert-success">✅ {{ session('success') }}</div>
            @endif

            <!-- Filter Tabs -->
            <div class="order-tabs-bar">
                <a href="{{ route('seller.orders.index') }}"
                    class="order-tab-link {{ !request('status') ? 'active' : '' }}">
                    All Orders ({{ $counts['all'] }})
                </a>
                <a href="{{ route('seller.orders.index', ['status' => 'PLACED']) }}"
                    class="order-tab-link {{ request('status') === 'PLACED' ? 'active' : '' }}">
                    New Placed ({{ $counts['placed'] }})
                </a>
                <a href="{{ route('seller.orders.index', ['status' => 'PREPARING']) }}"
                    class="order-tab-link {{ request('status') === 'PREPARING' ? 'active' : '' }}">
                    In Packing ({{ $counts['preparing'] }})
                </a>
                <a href="{{ route('seller.orders.index', ['status' => 'READY_FOR_PICKUP']) }}"
                    class="order-tab-link {{ request('status') === 'READY_FOR_PICKUP' ? 'active' : '' }}">
                    Ready for Pickup ({{ $counts['ready'] }})
                </a>
                <a href="{{ route('seller.orders.index', ['status' => 'COMPLETED']) }}"
                    class="order-tab-link {{ request('status') === 'COMPLETED' ? 'active' : '' }}">
                    Completed ({{ $counts['completed'] }})
                </a>
            </div>

            <!-- Orders Feed -->
            @forelse($orders as $order)
                <div class="order-card">
                    <div class="order-card-header">
                        <div>
                            <a href="{{ route('seller.orders.show', $order->id) }}"
                                class="order-number">{{ $order->order_number }}</a>
                            <div class="order-date">
                                Ordered on {{ $order->created_at->format('M d, Y h:i A') }}
                            </div>
                        </div>
                        <div>
                            <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                                {{ str_replace('_', ' ', $order->status) }}
                            </span>
                        </div>
                    </div>

                    <!-- Recipient Delivery Address -->
                    <div class="order-recipient-box">
                        Deliver to: <span class="order-recipient-name">{{ $order->recipient_name }}</span>
                        ({{ $order->recipient_contact }})
                        &bull;
                        {{ $order->street_address }}, {{ $order->barangay }}, {{ $order->municipality }},
                        {{ $order->province }}
                    </div>

                    <!-- Item Line Listings -->
                    <div class="order-item-listing">
                        @foreach ($order->items as $item)
                            <div class="order-item-row">
                                <div>
                                    <strong>{{ $item->product_name }}</strong>
                                    <span class="u-extracted-1c2d63da9b">x{{ $item->quantity }}</span>
                                </div>
                                <div>₱{{ number_format($item->item_total, 2) }}</div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Footer Summary & Actions -->
                    <div class="order-card-footer">
                        <div class="order-total-block">
                            Payment: <strong>{{ $order->payment_method }}</strong> &bull;
                            Net Earnings: <strong
                                class="u-extracted-f8f1327255">₱{{ number_format($order->subtotal - $order->commission_fee, 2) }}</strong>
                            <span class="u-extracted-cf0c302441">(10% platform fee
                                deducted)</span>
                        </div>

                        <div class="order-seller-action-row">
                            <a href="{{ route('seller.orders.waybill', $order->id) }}" target="_blank"
                                class="dash-btn-sm dash-btn-outline">
                                🖨️ Waybill
                            </a>

                            @if (
                                $order->payment_method !== 'COD' &&
                                    in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true))
                                <p role="status">Fulfillment on hold: payment verification is not configured for
                                    {{ $order->payment_method }}.</p>
                            @elseif ($order->status === 'PLACED')
                                <form action="{{ route('seller.orders.updateStatus', $order->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="CONFIRMED">
                                    <button type="submit" class="dash-btn-sm dash-btn-primary">
                                        Accept Order
                                    </button>
                                </form>
                            @elseif($order->status === 'CONFIRMED')
                                <form action="{{ route('seller.orders.updateStatus', $order->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="PREPARING">
                                    <button type="submit" class="dash-btn-sm dash-btn-primary">Start Preparing</button>
                                </form>
                            @elseif($order->status === 'PREPARING')
                                <form action="{{ route('seller.orders.updateStatus', $order->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="READY_FOR_PICKUP">
                                    <button type="submit" class="dash-btn-sm dash-btn-primary">
                                        Mark Ready for Pickup
                                    </button>
                                </form>
                            @elseif($order->status === 'READY_FOR_PICKUP' && $order->pickup_arrived_at && !$order->seller_handover_at)
                                <form action="{{ route('seller.orders.confirmHandover', $order) }}" method="POST">
                                    @csrf
                                    <label for="handover-rider-badge-{{ $order->id }}">Scan assigned rider badge
                                        QR/code</label>
                                    <input id="handover-rider-badge-{{ $order->id }}" name="rider_badge" data-qr-input
                                        required maxlength="100" placeholder="EZR:…" autocomplete="off">
                                    <input type="hidden" name="method" value="manual">
                                    <button type="button" data-qr-start>Use camera</button><button type="button"
                                        data-qr-stop hidden>Stop camera</button>
                                    <video data-qr-video playsinline hidden></video>
                                    <p data-qr-status role="status">Camera, handheld scanner, or manual code entry is
                                        supported.</p>
                                    <button type="submit" class="dash-btn-sm dash-btn-primary">Confirm rider
                                        handover</button>
                                </form>
                            @elseif($order->status === 'RETURN_IN_TRANSIT')
                                @if ($order->return_handed_to_seller_at)
                                    <form action="{{ route('seller.orders.confirmReturn', $order) }}" method="POST">
                                        @csrf
                                        <label for="return-rider-badge-{{ $order->id }}">Scan return rider badge
                                            QR/code</label>
                                        <input id="return-rider-badge-{{ $order->id }}" name="rider_badge" data-qr-input
                                            required maxlength="100" placeholder="EZR:…" autocomplete="off">
                                        <input type="hidden" name="method" value="manual">
                                        <button type="button" data-qr-start>Use camera</button><button type="button"
                                            data-qr-stop hidden>Stop camera</button>
                                        <video data-qr-video playsinline hidden></video>
                                        <p data-qr-status role="status">Camera, handheld scanner, or manual entry.</p>
                                        <button type="submit" class="dash-btn-sm dash-btn-primary">Confirm scanned return
                                            received</button>
                                    </form>
                                @else
                                    <span class="text-muted-small">Awaiting courier handoff</span>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="dash-empty-box">
                    📦 No customer orders found under this status.
                </div>
            @endforelse

            <div class="u-extracted-d4f7536fd4">
                {{ $orders->links() }}
            </div>
        </main>
    </div>
@endsection
