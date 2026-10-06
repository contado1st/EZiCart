@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
    @vite('resources/css/buyer/reviews.css')
    @vite('resources/css/shared/disputes.css')
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

            @if (session('success'))
                <div class="dash-alert-success">✅ {{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="dash-alert-success u-extracted-ee8231be38">
                    ⚠️ {{ session('error') }}
                </div>
            @endif

            <div class="dash-panel">
                <h2 class="courier-section-title">My Orders ({{ $orders->count() }})</h2>

                @forelse($orders as $order)
                    <div class="order-card u-extracted-dab43fb936">
                        <div class="order-card-header">
                            <div>
                                <a href="{{ route('buyer.orders.show', $order->id) }}"
                                    class="order-number">{{ $order->order_number }}</a>
                                <div class="order-date">Placed on {{ $order->created_at->format('M d, Y h:i A') }} &bull;
                                    Store: <strong>{{ $order->seller->business_name ?? 'Merchant' }}</strong></div>
                            </div>
                            <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                                {{ str_replace('_', ' ', $order->status) }}
                            </span>
                        </div>

                        <div class="u-extracted-0e37a331af">
                            @foreach ($order->items as $item)
                                <div class="u-extracted-8ad17e2bf5">
                                    <div>
                                        <strong>{{ $item->product_name }}</strong>
                                        @if ($item->variation_info)
                                            <span class="u-extracted-36eaa30988">({{ $item->variation_info }})</span>
                                        @endif
                                        <span class="u-extracted-71b8babba9">&times;
                                            {{ $item->quantity }}</span>
                                    </div>
                                    <div>₱{{ number_format($item->item_total, 2) }}</div>
                                </div>

                                <!-- Review Form for Completed Orders -->
                                @if ($order->status === 'COMPLETED')
                                    @php
                                        $alreadyReviewed = \App\Models\Review::where('order_id', $order->id)
                                            ->where('product_id', $item->product_id)
                                            ->first();
                                    @endphp

                                    @if ($alreadyReviewed)
                                        <div class="u-extracted-930b6add1c">
                                            ★ Rated {{ $alreadyReviewed->rating }}/5:
                                            "{{ $alreadyReviewed->comment ?? 'No comment' }}"
                                        </div>
                                    @else
                                        <div class="review-modal-box">
                                            <form action="{{ route('buyer.orders.review', $order->id) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="product_id" value="{{ $item->product_id }}">
                                                <div class="u-extracted-487b2c5804">
                                                    <select name="rating" class="review-form-select u-extracted-6f50c17793"
                                                        required>
                                                        <option value="5">★★★★★ (5)</option>
                                                        <option value="4">★★★★☆ (4)</option>
                                                        <option value="3">★★★☆☆ (3)</option>
                                                        <option value="2">★★☆☆☆ (2)</option>
                                                        <option value="1">★☆☆☆☆ (1)</option>
                                                    </select>
                                                    <input type="text" name="comment"
                                                        placeholder="Write feedback (optional)..."
                                                        class="review-form-select u-extracted-1c09f0c006">
                                                    <button type="submit"
                                                        class="dash-btn-sm dash-btn-primary u-extracted-1c09f0c006">
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
                                <span class="u-extracted-cf0c302441">({{ $order->payment_method }})</span>
                            </div>

                            <div class="u-extracted-487b2c5804">
                                @if ($order->dispute)
                                    <span
                                        class="status-pill status-badge-{{ strtolower(str_replace('_', '-', $order->dispute->status)) }}">
                                        Dispute: {{ str_replace('_', ' ', $order->dispute->status) }}
                                    </span>
                                @elseif(in_array($order->status, ['DELIVERED', 'COMPLETED']))
                                    <a href="{{ route('buyer.orders.dispute.create', $order->id) }}"
                                        class="dash-btn-sm u-extracted-8dddb4a915">
                                        ⚠️ File Dispute
                                    </a>
                                @endif

                                @if ($order->status === 'DELIVERED')
                                    <form action="{{ route('buyer.orders.confirm', $order->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dash-btn-sm dash-btn-success">
                                            Confirm Order Received
                                        </button>
                                    </form>
                                @endif

                                @if (in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING'], true))
                                    <form action="{{ route('buyer.orders.cancel', $order) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dash-btn-sm"
                                            onclick="return confirm('Cancel this order and restore its reserved stock?')">Cancel
                                            order</button>
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
