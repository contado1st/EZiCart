@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/reviews.css') }}">
    <link rel="stylesheet" href="{{ asset('css/disputes.css') }}">
@endpush

@section('content')
<div class="dash-wrapper">
    <aside class="dash-sidebar">
        <div>
            <div class="dash-profile-badge">
                <span class="dash-role-tag">Buyer Portal</span>
                <h2 class="dash-profile-title">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</h2>
                <p class="dash-profile-subtitle">Member since {{ auth()->user()->created_at->format('M Y') }}</p>
            </div>
            <nav class="dash-nav">
                <a href="{{ route('buyer.dashboard') }}" class="dash-nav-item active">📦 My Orders</a>
                <a href="{{ route('cart.index') }}" class="dash-nav-item">🛒 View Cart</a>
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
                <h1 class="dash-title">Order History & Activity</h1>
                <p class="dash-subtitle">Track parcel progress, confirm receipt, and review delivered purchases.</p>
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

        <div class="dash-panel">
            <h2 class="courier-section-title">My Orders ({{ $orders->count() }})</h2>

            @forelse($orders as $order)
                <div class="order-card" style="margin-top: 1rem;">
                    <div class="order-card-header">
                        <div>
                            <span class="order-number">{{ $order->order_number }}</span>
                            <div class="order-date">Placed on {{ $order->created_at->format('M d, Y h:i A') }} &bull; Store: <strong>{{ $order->seller->business_name ?? 'Merchant' }}</strong></div>
                        </div>
                        <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                            {{ str_replace('_', ' ', $order->status) }}
                        </span>
                    </div>

                    <div style="padding: 1rem 0; border-top: 1px solid var(--dash-border); border-bottom: 1px solid var(--dash-border);">
                        @foreach($order->items as $item)
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <div>
                                    <strong>{{ $item->product_name }}</strong>
                                    @if($item->variation_info)
                                        <span style="font-size: 0.75rem; color: var(--dash-primary); margin-left: 0.5rem;">({{ $item->variation_info }})</span>
                                    @endif
                                    <span style="color: var(--dash-text-muted); font-size: 0.8125rem;">&times; {{ $item->quantity }}</span>
                                </div>
                                <div>₱{{ number_format($item->subtotal ?? $item->item_total, 2) }}</div>
                            </div>

                            <!-- Review Form for Completed Orders -->
                            @if($order->status === 'COMPLETED')
                                @php
                                    $alreadyReviewed = \App\Models\Review::where('order_id', $order->id)->where('product_id', $item->product_id)->first();
                                @endphp

                                @if($alreadyReviewed)
                                    <div style="font-size: 0.75rem; color: #059669; margin-top: 0.25rem;">
                                        ★ Rated {{ $alreadyReviewed->rating }}/5: "{{ $alreadyReviewed->comment ?? 'No comment' }}"
                                    </div>
                                @else
                                    <div class="review-modal-box">
                                        <form action="{{ route('buyer.orders.review', $order->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $item->product_id }}">
                                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                                <select name="rating" class="review-form-select" style="max-width: 140px;" required>
                                                    <option value="5">★★★★★ (5)</option>
                                                    <option value="4">★★★★☆ (4)</option>
                                                    <option value="3">★★★☆☆ (3)</option>
                                                    <option value="2">★★☆☆☆ (2)</option>
                                                    <option value="1">★☆☆☆☆ (1)</option>
                                                </select>
                                                <input type="text" name="comment" placeholder="Write feedback (optional)..." class="review-form-select" style="margin-bottom: 0.5rem;">
                                                <button type="submit" class="dash-btn-sm dash-btn-primary" style="margin-bottom: 0.5rem;">
                                                    Rate
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            @endif
                        @endforeach
                    </div>

                    <div class="order-card-footer">
                        <div>
                            Total Amount: <strong>₱{{ number_format($order->total_amount, 2) }}</strong>
                            <span style="font-size: 0.75rem; color: var(--dash-text-muted);">({{ $order->payment_method }})</span>
                        </div>

                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            @if($order->dispute)
                                <span class="status-pill status-badge-{{ strtolower(str_replace('_', '-', $order->dispute->status)) }}">
                                    Dispute: {{ str_replace('_', ' ', $order->dispute->status) }}
                                </span>
                            @elseif(in_array($order->status, ['DELIVERED', 'COMPLETED']))
                                <a href="{{ route('buyer.orders.dispute.create', $order->id) }}" class="dash-btn-sm" style="border: 1px solid var(--dash-danger); color: var(--dash-danger); text-decoration: none;">
                                    ⚠️ File Dispute
                                </a>
                            @endif

                            @if($order->status === 'DELIVERED')
                                <form action="{{ route('buyer.orders.confirm', $order->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dash-btn-sm dash-btn-success">
                                        Confirm Order Received
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="dash-empty-box">You have not placed any orders yet.</div>
            @endforelse
        </div>
    </main>
</div>
@endsection