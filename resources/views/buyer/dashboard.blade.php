@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
    @vite('resources/css/buyer/reviews.css')
    @vite('resources/css/shared/disputes.css')
    <link rel="stylesheet" href="{{ asset('css/buyer-account.css') }}?v={{ filemtime(public_path('css/buyer-account.css')) }}">
@endpush

@section('content')
    <div class="buyer-account-page buyer-orders-layout">

        <main class="dash-main buyer-account-content buyer-orders-content">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">My purchases</h1>
                    <p class="dash-subtitle">Track parcel progress, confirm receipt, and review delivered purchases.</p>
                </div>
            </div>

            <section class="workspace-metrics" aria-label="Purchase summary">
                <x-workspace-metric label="All orders" :value="$counts['all']" icon="cart1" detail="Your purchase history" />
                <x-workspace-metric label="Preparing to ship" :value="$counts['to_ship']" icon="package1" detail="Placed, confirmed or being prepared" tone="warning" />
                <x-workspace-metric label="To receive" :value="$counts['to_receive']" icon="delivery1" detail="Track your parcel and delivery" />
                <x-workspace-metric label="Closed orders" :value="$counts['completed']" icon="checklist" detail="Completed, cancelled or returned" tone="success" />
            </section>

            @if (session('success'))
                <div class="dash-alert-success"><x-shop-icon name="checklist" /> {{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="dash-alert-success u-extracted-ee8231be38">
                    <x-shop-icon name="warning" /> {{ session('error') }}
                </div>
            @endif

            <div class="dash-panel">
                <div class="workspace-orders-heading">
                    <h2 class="dash-panel-title">Order history</h2>
                    <form method="GET" action="{{ route('buyer.dashboard') }}" class="workspace-order-filter">
                        <label for="dashboard-order-status">Status</label>
                        <select name="status" id="dashboard-order-status">
                            <option value="">All statuses</option>
                            @foreach (\App\Enums\OrderStatus::cases() as $orderStatus)
                                <option value="{{ $orderStatus->value }}" @selected(request('status') === $orderStatus->value)>{{ ucwords(strtolower(str_replace('_', ' ', $orderStatus->value))) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="dash-btn-sm dash-btn-outline">Filter</button>
                    </form>
                </div>

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
                                        <x-shop-icon name="warning" /> File Dispute
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
                <div class="workspace-pagination">{{ $orders->links() }}</div>
            </div>
        </main>
    </div>
@endsection
