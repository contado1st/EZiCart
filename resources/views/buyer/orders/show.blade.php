@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/reviews.css') }}">
    <link rel="stylesheet" href="{{ asset('css/disputes.css') }}">
    <style>
        .tracking-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 2rem 0;
        }
        .tracking-timeline::before {
            content: '';
            position: absolute;
            top: 18px;
            left: 20px;
            right: 20px;
            height: 4px;
            background: var(--slate-200);
            z-index: 1;
        }
        .timeline-step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }
        .step-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: white;
            border: 3px solid var(--slate-300);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem auto;
            font-size: 0.9rem;
            color: var(--slate-400);
            font-weight: 700;
        }
        .timeline-step.completed .step-icon {
            border-color: #059669;
            background: #ecfdf5;
            color: #059669;
        }
        .timeline-step.active .step-icon {
            border-color: var(--ez-primary);
            background: #fff1f2;
            color: var(--ez-primary);
            box-shadow: 0 0 0 4px rgba(230, 46, 99, 0.2);
        }
        .step-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--slate-600);
        }
        .timeline-step.active .step-label {
            color: var(--ez-primary);
        }
        .timeline-step.completed .step-label {
            color: #059669;
        }
    </style>
@endpush

@section('content')
<div class="dash-wrapper">
    <!-- Sidebar -->
    <aside class="dash-sidebar">
        <div>
            <div class="dash-profile-badge">
                <span class="dash-role-tag">Buyer Portal</span>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.5rem;">
                    <img src="{{ auth()->user()->profile_photo_url }}" alt="Profile" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover;">
                    <div>
                        <h2 class="dash-profile-title" style="margin: 0; font-size: 1rem;">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h2>
                        <p class="dash-profile-subtitle" style="margin: 0;">Member since {{ auth()->user()->created_at->format('M Y') }}</p>
                    </div>
                </div>
            </div>

            <nav class="dash-nav">
                <a href="{{ route('buyer.dashboard') }}" class="dash-nav-item active">
                    📦 My Orders
                </a>
                <a href="{{ route('cart.index') }}" class="dash-nav-item">
                    🛒 My Cart
                </a>
                <a href="{{ route('buyer.profile') }}" class="dash-nav-item">
                    👤 My Profile
                </a>
                <a href="{{ route('buyer.addresses.index') }}" class="dash-nav-item">
                    📍 Delivery Addresses
                </a>
                <a href="{{ route('buyer.password') }}" class="dash-nav-item">
                    🔒 Change Password
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
                <a href="{{ route('buyer.dashboard') }}" style="color: var(--slate-500); text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem; margin-bottom: 0.5rem;">
                    ← Back to Orders
                </a>
                <h1 class="dash-title">Order #{{ $order->order_number }}</h1>
                <p class="dash-subtitle">Placed on {{ $order->created_at->format('F d, Y at h:i A') }} &bull; Sold by <strong>{{ $order->seller->business_name ?? 'Merchant' }}</strong></p>
            </div>
            <div>
                <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}" style="font-size: 0.95rem; padding: 0.4rem 0.9rem;">
                    Status: {{ str_replace('_', ' ', $order->status) }}
                </span>
            </div>
        </div>

        @if(session('success'))
            <div class="dash-alert-success">✅ {{ session('success') }}</div>
        @endif

        @php
            $statusRank = match($order->status) {
                'PLACED' => 1,
                'CONFIRMED', 'PREPARING' => 2,
                'READY_FOR_PICKUP' => 3,
                'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED' => 4,
                'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY' => 5,
                'DELIVERED' => 6,
                'COMPLETED' => 7,
                default => 0,
            };
        @endphp

        <!-- Read-Only Shipment Milestones Display -->
        <div class="dash-panel" style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 800; color: var(--slate-800); margin-bottom: 1rem;">
                🚚 Delivery Tracking
            </h3>

            @if(in_array($order->status, ['CANCELLED', 'DELIVERY_FAILED', 'RETURNED']))
                <div style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 1rem; border-radius: 8px; font-weight: 600;">
                    ⚠️ This order is {{ str_replace('_', ' ', $order->status) }}.
                </div>
            @else
                <div class="tracking-timeline">
                    <div class="timeline-step {{ $statusRank > 1 ? 'completed' : ($statusRank === 1 ? 'active' : '') }}">
                        <div class="step-icon">1</div>
                        <div class="step-label">Order Placed</div>
                    </div>
                    <div class="timeline-step {{ $statusRank > 2 ? 'completed' : ($statusRank === 2 ? 'active' : '') }}">
                        <div class="step-icon">2</div>
                        <div class="step-label">Prepared by Seller</div>
                    </div>
                    <div class="timeline-step {{ $statusRank > 3 ? 'completed' : ($statusRank === 3 ? 'active' : '') }}">
                        <div class="step-icon">3</div>
                        <div class="step-label">Ready for Pickup</div>
                    </div>
                    <div class="timeline-step {{ $statusRank > 4 ? 'completed' : ($statusRank === 4 ? 'active' : '') }}">
                        <div class="step-icon">4</div>
                        <div class="step-label">In Transit</div>
                    </div>
                    <div class="timeline-step {{ $statusRank > 5 ? 'completed' : ($statusRank === 5 ? 'active' : '') }}">
                        <div class="step-icon">5</div>
                        <div class="step-label">Out for Delivery</div>
                    </div>
                    <div class="timeline-step {{ $statusRank >= 6 ? 'completed' : '' }}">
                        <div class="step-icon">✓</div>
                        <div class="step-label">Delivered</div>
                    </div>
                </div>
            @endif
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
            <!-- Left: Order Items & Actions -->
            <div>
                <div class="dash-panel" style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1rem; font-weight: 800; color: var(--slate-800); margin-bottom: 1rem;">
                        Purchased Items ({{ $order->items->count() }})
                    </h3>

                    @foreach($order->items as $item)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid var(--slate-100);">
                            <div style="display: flex; gap: 0.85rem; align-items: center;">
                                @if($item->product && $item->product->image_path)
                                    <img src="{{ asset('storage/' . $item->product->image_path) }}" alt="{{ $item->product_name }}" style="width: 56px; height: 56px; object-fit: cover; border-radius: 8px; border: 1px solid var(--slate-200);">
                                @else
                                    <div style="width: 56px; height: 56px; background: var(--slate-100); border-radius: 8px; display: flex; align-items: center; justify-content: center;">📦</div>
                                @endif
                                <div>
                                    <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--slate-800); margin: 0;">{{ $item->product_name }}</h4>
                                    @if($item->variation_info)
                                        <div style="font-size: 0.8rem; color: var(--ez-primary); font-weight: 600; margin-top: 0.2rem;">
                                            Variation: {{ $item->variation_info }}
                                        </div>
                                    @endif
                                    <div style="font-size: 0.8rem; color: var(--slate-500); margin-top: 0.2rem;">
                                        Unit Price: ₱{{ number_format($item->price, 2) }} &times; {{ $item->quantity }}
                                    </div>
                                </div>
                            </div>
                            <div style="font-weight: 800; font-size: 1rem; color: var(--slate-800);">
                                ₱{{ number_format($item->subtotal ?? $item->item_total, 2) }}
                            </div>
                        </div>

                        <!-- Review Box if Delivered or Completed -->
                        @if(in_array($order->status, ['DELIVERED', 'COMPLETED']))
                            @php
                                $alreadyReviewed = \App\Models\Review::where('order_id', $order->id)->where('product_id', $item->product_id)->first();
                            @endphp

                            @if($alreadyReviewed)
                                <div style="font-size: 0.8rem; color: #059669; background: #ecfdf5; padding: 0.5rem 0.85rem; border-radius: 6px; margin: 0.5rem 0 1rem 0;">
                                    ★ Your Review: <strong>{{ $alreadyReviewed->rating }}/5</strong> &bull; "{{ $alreadyReviewed->comment ?? 'No comment provided' }}"
                                </div>
                            @else
                                <div style="background: var(--slate-50); border: 1px dashed var(--slate-300); border-radius: 6px; padding: 0.75rem 1rem; margin: 0.5rem 0 1rem 0;">
                                    <form action="{{ route('buyer.orders.review', $order->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $item->product_id }}">
                                        <div style="font-size: 0.85rem; font-weight: 700; margin-bottom: 0.5rem;">Rate & Review this product:</div>
                                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                            <select name="rating" class="form-control" style="max-width: 140px; font-size: 0.85rem;" required>
                                                <option value="5">★★★★★ (5)</option>
                                                <option value="4">★★★★☆ (4)</option>
                                                <option value="3">★★★☆☆ (3)</option>
                                                <option value="2">★★☆☆☆ (2)</option>
                                                <option value="1">★☆☆☆☆ (1)</option>
                                            </select>
                                            <input type="text" name="comment" placeholder="Share your experience with this item..." class="form-control" style="flex: 1; min-width: 200px; font-size: 0.85rem;">
                                            <button type="submit" class="dash-btn-sm dash-btn-primary">Submit</button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        @endif
                    @endforeach

                    @if($order->status === 'DELIVERED')
                        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--slate-200); display: flex; justify-content: flex-end;">
                            <form action="{{ route('buyer.orders.confirm', $order->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="dash-btn-primary" style="padding: 0.6rem 1.25rem; font-size: 0.9rem; font-weight: 700; background: #059669; border-color: #059669;">
                                    ✓ Confirm Order Received
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right: Address & Pricing Summary -->
            <div>
                <!-- Shipping Address Card -->
                <div class="dash-panel" style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1rem; font-weight: 800; color: var(--slate-800); margin-bottom: 0.75rem;">
                        📍 Delivery Address
                    </h3>
                    <div style="font-weight: 700; color: var(--slate-800); font-size: 0.9rem;">
                        {{ $order->recipient_name }}
                    </div>
                    <div style="font-size: 0.85rem; color: var(--slate-500); margin-top: 0.2rem;">
                        📞 {{ $order->recipient_contact }}
                    </div>
                    <div style="font-size: 0.85rem; color: var(--slate-700); margin-top: 0.4rem; line-height: 1.4;">
                        {{ $order->street_address }}, {{ $order->barangay }}, {{ $order->municipality }}, {{ $order->province }}
                    </div>
                </div>

                <!-- Price Breakdown -->
                <div class="dash-panel">
                    <h3 style="font-size: 1rem; font-weight: 800; color: var(--slate-800); margin-bottom: 1rem;">
                        💳 Payment Summary
                    </h3>

                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--slate-600); margin-bottom: 0.5rem;">
                        <span>Merchandise Subtotal:</span>
                        <span>₱{{ number_format($order->subtotal, 2) }}</span>
                    </div>

                    @if($order->discount_amount > 0)
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: #059669; margin-bottom: 0.5rem;">
                            <span>Voucher Discount ({{ $order->voucher_code }}):</span>
                            <span>-₱{{ number_format($order->discount_amount, 2) }}</span>
                        </div>
                    @endif

                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--slate-600); margin-bottom: 0.5rem;">
                        <span>Standard Shipping:</span>
                        <span>₱{{ number_format($order->shipping_fee, 2) }}</span>
                    </div>

                    <div style="height: 1px; background: var(--slate-200); margin: 0.75rem 0;"></div>

                    <div style="display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 800; color: var(--ez-primary); margin-bottom: 0.5rem;">
                        <span>Total Paid:</span>
                        <span>₱{{ number_format($order->total_amount, 2) }}</span>
                    </div>

                    <div style="font-size: 0.8rem; color: var(--slate-500); margin-top: 0.5rem;">
                        Payment Method: <strong>{{ $order->payment_method }}</strong>
                    </div>

                    @if(in_array($order->status, ['DELIVERED', 'COMPLETED']) && !$order->dispute)
                        <div style="margin-top: 1.25rem;">
                            <a href="{{ route('buyer.orders.dispute.create', $order->id) }}" class="dash-btn-sm" style="display: block; text-align: center; border: 1px solid var(--dash-danger); color: var(--dash-danger); text-decoration: none; padding: 0.5rem;">
                                ⚠️ File a Dispute / Refund Request
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>
</div>
@endsection

