@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/reviews.css') }}">
    <link rel="stylesheet" href="{{ asset('css/disputes.css') }}">
    <style>
        .buyer-tab-strip {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid var(--slate-200);
            margin-bottom: 1.5rem;
            overflow-x: auto;
            padding-bottom: 2px;
        }
        .buyer-tab-item {
            padding: 0.6rem 1rem;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--slate-600);
            text-decoration: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .buyer-tab-item:hover {
            color: var(--ez-primary);
        }
        .buyer-tab-item.active {
            color: var(--ez-primary);
            border-bottom-color: var(--ez-primary);
        }
        .buyer-tab-badge {
            background: var(--slate-100);
            color: var(--slate-700);
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 0.75rem;
            margin-left: 4px;
        }
        .buyer-tab-item.active .buyer-tab-badge {
            background: var(--ez-primary);
            color: white;
        }
    </style>
@endpush

@section('content')
<div class="dash-wrapper">
    <!-- Buyer Sidebar -->
    <aside class="dash-sidebar">
        <div>
            <div class="dash-profile-badge">
                <span class="dash-role-tag">Buyer Portal</span>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.5rem;">
                    <img src="{{ auth()->user()->profile_photo_url }}" alt="Profile" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
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
                <h1 class="dash-title">My Orders & Activity</h1>
                <p class="dash-subtitle">Track deliveries, confirm receipt, and rate purchases.</p>
            </div>
            <div>
                <a href="{{ route('home') }}" class="dash-btn-primary" style="text-decoration: none;">
                    🛍️ Continue Shopping
                </a>
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

        <!-- Filter Status Tabs -->
        <div class="buyer-tab-strip">
            <a href="{{ route('buyer.dashboard', ['tab' => 'all']) }}" class="buyer-tab-item {{ ($statusTab ?? 'all') === 'all' ? 'active' : '' }}">
                All Orders <span class="buyer-tab-badge">{{ $counts['all'] }}</span>
            </a>
            <a href="{{ route('buyer.dashboard', ['tab' => 'to_ship']) }}" class="buyer-tab-item {{ ($statusTab ?? '') === 'to_ship' ? 'active' : '' }}">
                To Ship <span class="buyer-tab-badge">{{ $counts['to_ship'] }}</span>
            </a>
            <a href="{{ route('buyer.dashboard', ['tab' => 'to_receive']) }}" class="buyer-tab-item {{ ($statusTab ?? '') === 'to_receive' ? 'active' : '' }}">
                To Receive <span class="buyer-tab-badge">{{ $counts['to_receive'] }}</span>
            </a>
            <a href="{{ route('buyer.dashboard', ['tab' => 'delivered']) }}" class="buyer-tab-item {{ ($statusTab ?? '') === 'delivered' ? 'active' : '' }}">
                Delivered <span class="buyer-tab-badge">{{ $counts['delivered'] }}</span>
            </a>
            <a href="{{ route('buyer.dashboard', ['tab' => 'completed']) }}" class="buyer-tab-item {{ ($statusTab ?? '') === 'completed' ? 'active' : '' }}">
                Completed <span class="buyer-tab-badge">{{ $counts['completed'] }}</span>
            </a>
            <a href="{{ route('buyer.dashboard', ['tab' => 'cancelled']) }}" class="buyer-tab-item {{ ($statusTab ?? '') === 'cancelled' ? 'active' : '' }}">
                Cancelled <span class="buyer-tab-badge">{{ $counts['cancelled'] }}</span>
            </a>
        </div>

        <div class="dash-panel">
            @forelse($orders as $order)
                <div class="order-card" style="margin-top: 1rem; border: 1px solid var(--slate-200); border-radius: 10px; overflow: hidden; background: white;">
                    <div class="order-card-header" style="background: var(--slate-50); padding: 0.85rem 1.25rem;">
                        <div>
                            <span class="order-number" style="font-weight: 800; font-size: 0.95rem;">{{ $order->order_number }}</span>
                            <div class="order-date" style="font-size: 0.75rem; color: var(--slate-500);">
                                Placed {{ $order->created_at->format('M d, Y h:i A') }} &bull; Store: <strong>{{ $order->seller->business_name ?? 'Merchant' }}</strong>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                                {{ str_replace('_', ' ', $order->status) }}
                            </span>
                            <a href="{{ route('buyer.orders.show', $order->id) }}" class="dash-btn-sm" style="background: white; border: 1px solid var(--slate-300); color: var(--slate-700); text-decoration: none;">
                                View Details 👁️
                            </a>
                        </div>
                    </div>

                    <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--slate-100); border-bottom: 1px solid var(--slate-100);">
                        @foreach($order->items as $item)
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    @if($item->product && $item->product->image_path)
                                        <img src="{{ asset('storage/' . $item->product->image_path) }}" alt="{{ $item->product_name }}" style="width: 44px; height: 44px; object-fit: cover; border-radius: 6px; border: 1px solid var(--slate-200);">
                                    @else
                                        <div style="width: 44px; height: 44px; background: var(--slate-100); border-radius: 6px; display: flex; align-items: center; justify-content: center;">📦</div>
                                    @endif
                                    <div>
                                        <div style="font-weight: 700; color: var(--slate-800); font-size: 0.9rem;">{{ $item->product_name }}</div>
                                        @if($item->variation_info)
                                            <span style="font-size: 0.75rem; color: var(--ez-primary); font-weight: 600;">Variation: {{ $item->variation_info }}</span>
                                        @endif
                                        <div style="color: var(--slate-500); font-size: 0.8rem;">Qty: {{ $item->quantity }} &times; ₱{{ number_format($item->price, 2) }}</div>
                                    </div>
                                </div>
                                <div style="font-weight: 700; color: var(--slate-800); font-size: 0.9rem;">
                                    ₱{{ number_format($item->subtotal ?? $item->item_total, 2) }}
                                </div>
                            </div>

                            <!-- Review Form for Completed Orders -->
                            @if(in_array($order->status, ['DELIVERED', 'COMPLETED']))
                                @php
                                    $alreadyReviewed = \App\Models\Review::where('order_id', $order->id)->where('product_id', $item->product_id)->first();
                                @endphp

                                @if($alreadyReviewed)
                                    <div style="font-size: 0.8rem; color: #059669; background: #ecfdf5; padding: 0.4rem 0.75rem; border-radius: 6px; margin: 0.5rem 0;">
                                        ★ Your Rating: <strong>{{ $alreadyReviewed->rating }}/5</strong> &bull; "{{ $alreadyReviewed->comment ?? 'No comment provided' }}"
                                    </div>
                                @else
                                    <div style="background: var(--slate-50); border: 1px dashed var(--slate-300); border-radius: 6px; padding: 0.6rem 0.85rem; margin: 0.5rem 0;">
                                        <form action="{{ route('buyer.orders.review', $order->id) }}" method="POST" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $item->product_id }}">
                                            <span style="font-size: 0.8rem; font-weight: 700; color: var(--slate-700);">Rate Item:</span>
                                            <select name="rating" class="form-control" style="max-width: 130px; font-size: 0.8rem; padding: 0.3rem 0.5rem;" required>
                                                <option value="5">★★★★★ (5)</option>
                                                <option value="4">★★★★☆ (4)</option>
                                                <option value="3">★★★☆☆ (3)</option>
                                                <option value="2">★★☆☆☆ (2)</option>
                                                <option value="1">★☆☆☆☆ (1)</option>
                                            </select>
                                            <input type="text" name="comment" placeholder="Write feedback (optional)..." class="form-control" style="flex: 1; min-width: 180px; font-size: 0.8rem; padding: 0.3rem 0.5rem;">
                                            <button type="submit" class="dash-btn-sm dash-btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                                                Submit Review
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            @endif
                        @endforeach
                    </div>

                    <div class="order-card-footer" style="padding: 0.85rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <span style="color: var(--slate-600); font-size: 0.85rem;">Order Total: </span>
                            <strong style="color: var(--ez-primary); font-size: 1.05rem;">₱{{ number_format($order->total_amount, 2) }}</strong>
                            <span style="font-size: 0.75rem; color: var(--slate-400); margin-left: 0.25rem;">({{ $order->payment_method }})</span>
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
                                    <button type="submit" class="dash-btn-sm dash-btn-success" style="font-weight: 700;">
                                        ✓ Confirm Order Received
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="dash-empty-box" style="padding: 3rem 1rem; text-align: center; color: var(--slate-500);">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📦</div>
                    <h3 style="color: var(--slate-700); margin-bottom: 0.25rem;">No orders found in this section</h3>
                    <p style="font-size: 0.85rem;">Check out our marketplace to find great products.</p>
                    <a href="{{ route('home') }}" class="dash-btn-primary" style="display: inline-block; margin-top: 1rem; text-decoration: none;">
                        Browse Marketplace
                    </a>
                </div>
            @endforelse

            <div style="margin-top: 1.5rem;">
                {{ $orders->links() }}
            </div>
        </div>
    </main>
</div>
@endsection