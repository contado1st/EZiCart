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

            <section class="workspace-metrics buyer-purchases-summary" aria-label="Purchase summary">
                <x-workspace-metric label="All orders" :value="$counts['all']" icon="cart1" detail="Your purchase history" />
                <x-workspace-metric label="Preparing to ship" :value="$counts['to_ship']" icon="package1" detail="Placed, confirmed or being prepared" tone="warning" />
                <x-workspace-metric label="To receive" :value="$counts['to_receive']" icon="delivery1" detail="Track your parcel and delivery" />
                <x-workspace-metric label="Completed" :value="$counts['completed']" icon="checklist" detail="Completed purchases outside an active return/refund" tone="success" />
            </section>

            <x-feature-feedback />

            @if (session('error'))
                <div class="feature-panel feature-feedback feature-feedback--error" role="alert">
                    <h2>Action not completed</h2>
                    <p>{{ session('error') }}</p>
                </div>
            @endif

            <nav class="workspace-order-tabs" aria-label="Purchase status">
                @foreach ($groupLabels as $group => $label)
                    @if($group !== 'other' || $counts['other'] > 0 || $orderGroup === 'other')
                    <a href="{{ request()->fullUrlWithQuery(['order_group' => $group, 'page' => null]) }}" @if ($orderGroup === $group) aria-current="page" @endif>{{ $label }} <span class="workspace-tab-count">{{ number_format($counts[$group]) }}</span></a>
                    @endif
                @endforeach
            </nav>
            <div class="dash-panel">
                <div class="workspace-orders-heading">
                    <h2 class="dash-panel-title">Order history</h2>
                    <form method="GET" action="{{ route('buyer.dashboard') }}" class="workspace-order-filter">
                        <input type="hidden" name="order_group" value="{{ $orderGroup }}">
                        <label for="dashboard-order-status">Status</label>
                        <select name="status" id="dashboard-order-status">
                            <option value="">All statuses</option>
                            @foreach (\App\Enums\OrderStatus::cases() as $orderStatus)
                                <option value="{{ $orderStatus->value }}" @selected(request('status') === $orderStatus->value)>{{ ucwords(strtolower(str_replace('_', ' ', $orderStatus->value))) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="dash-btn-sm dash-btn-outline">Filter</button>
                        <a class="dash-btn-sm dash-btn-outline" href="{{ route('buyer.dashboard') }}">Reset</a>
                    </form>
                </div>

                <p class="web-muted">Use a purchase tab or filter by order status. Up to 25 orders per page. Returns/refunds include open disputes and recorded refund activity.</p>
                @forelse($orders as $order)
                    <div class="order-card" style="margin-top: 1rem; border-radius: var(--dash-radius-inner);">
                        <div class="order-card-header">
                            <div>
                                <a href="{{ route('buyer.orders.show', $order->id) }}"
                                    class="order-number">{{ $order->order_number }}</a>
                                <div class="order-date">Placed on {{ $order->created_at->format('M d, Y h:i A') }} &bull;
                                    Store: <strong>{{ $order->seller->business_name ?? 'Merchant' }}</strong></div>
                            </div>
                            <span class="status-pill status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                                {{ str_replace('_', ' ', $order->operational_status) }}
                            </span>
                        </div>

                        <div class="order-item-listing">
                            @foreach ($order->items as $item)
                                <div class="order-item-row" style="font-variant-numeric: tabular-nums;">
                                    <x-order-item-preview :item="$item" />
                                    <div class="order-item-line-total">₱{{ number_format($item->item_total, 2) }}</div>
                                </div>

                                <!-- Review Form for Completed Orders -->
                                @if (($order->status === 'COMPLETED' || $order->reviews->contains('product_id', $item->product_id)) && $order->items->firstWhere('product_id', $item->product_id)->id === $item->id)
                                    @php
                                        $alreadyReviewed = $order->reviews->firstWhere('product_id', $item->product_id);
                                    @endphp

                                    @if ($alreadyReviewed)
                                        <div style="margin-top: 0.5rem; font-size: 0.8125rem; color: #b45309;">
                                            <x-shop-icon name="star" /> Rated {{ $alreadyReviewed->rating }}/5:
                                            "{{ $alreadyReviewed->comment ?? 'No comment' }}"
                                            <a href="{{ route('buyer.reviews.edit', $alreadyReviewed) }}">Edit review / private history</a>
                                        </div>
                                    @else
                                        <div class="review-modal-box">
                                            <x-review-form :order="$order" :product-id="$item->product_id" />
                                        </div>
                                    @endif
                                @endif
                            @endforeach
                        </div>

                        <div class="order-card-footer">
                            <div style="font-variant-numeric: tabular-nums;">
                                Total Amount: <strong>₱{{ number_format($order->total_amount, 2) }}</strong>
                                <span style="font-size: 0.75rem; color: var(--dash-text-muted);">({{ $order->payment_method }})</span>
                            </div>

                            <div class="order-seller-action-row">
                                @if($order->isAwaitingWalletPayment())
                                    <a class="dash-btn-sm dash-btn-outline" href="{{ route('buyer.orders.wallet-payment', $order) }}">Pay or cancel reservation</a>
                                @endif
                                @if ($order->dispute)
                                    <span
                                        class="status-pill status-badge-{{ strtolower(str_replace('_', '-', $order->dispute->status)) }}">
                                        Dispute: {{ str_replace('_', ' ', $order->dispute->status) }}
                                    </span>
                                @elseif(in_array($order->status, ['DELIVERED', 'COMPLETED']))
                                    <a href="{{ route('buyer.orders.dispute.create', $order->id) }}"
                                        class="dash-btn-sm dash-btn-outline" style="color: var(--dash-danger); border-color: #fecaca;">
                                        <x-shop-icon name="warning" /> File Dispute
                                    </a>
                                @endif

                                @if ($order->status === 'DELIVERED')
                                    <form action="{{ route('buyer.orders.confirm', $order->id) }}" method="POST" data-decision="buyer.orders.confirm" data-decision-title="Confirm receipt?" data-decision-message="Confirm only after you have received the parcel and checked your items. This records your receipt of the order." data-decision-label="Confirm receipt" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        @csrf
                                        <button type="submit" class="dash-btn-sm dash-btn-primary">
                                            Confirm Order Received
                                        </button>
                                    </form>
                                @endif

                                @if ($order->pendingCancellation)
                                    <a class="dash-btn-sm dash-btn-outline" href="{{ route('buyer.orders.show', $order) }}">Cancellation pending</a>
                                @elseif (! $order->isAwaitingWalletPayment() && in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING'], true))
                                    <form action="{{ route('buyer.orders.cancel', $order) }}" method="POST" data-decision="buyer.orders.cancel" data-decision-title="Request cancellation?" data-decision-message="The seller has 24 hours to respond before automatic approval. Cancellation is unavailable after physical pickup." data-decision-label="Request cancellation" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        @csrf
                                        <button type="submit" class="dash-btn-sm dash-btn-outline">Request
                                             cancellation</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="dash-empty-box">@if ($counts['all'] === 0)You have not placed any orders yet.@else No orders match this page and status. Choose another status or use the page controls below.@endif</div>
                @endforelse
                <div class="workspace-pagination">{{ $orders->links() }}</div>
            </div>
        </main>
    </div>
@endsection
