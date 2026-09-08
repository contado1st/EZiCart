@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/checkout.css') }}">
@endpush

@section('content')
<div class="checkout-container">
    <div class="checkout-header">
        <h1 class="checkout-title">Checkout & Finalize Order</h1>
        <p class="checkout-subtitle">Confirm your delivery location and payment option to place your order.</p>
    </div>

    @if(session('error'))
        <div style="background-color: var(--ezipink-50); color: var(--ezipink-600); border-left: 4px solid var(--ezipink-500); padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; font-weight: 600;">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('checkout.process') }}" method="POST" class="checkout-layout">
        @csrf

        <!-- Delivery & Payment Section -->
        <div>
            <!-- Address Card -->
            <div class="checkout-card">
                <h2 class="checkout-card-title">1. Delivery Information</h2>
                <div class="checkout-form-grid">
                    <div class="checkout-form-group">
                        <label class="checkout-label">Recipient Name *</label>
                        <input type="text" name="recipient_name" class="checkout-input" value="{{ old('recipient_name', $user->first_name . ' ' . $user->last_name) }}" required>
                    </div>

                    <div class="checkout-form-group">
                        <label class="checkout-label">Contact Number *</label>
                        <input type="text" name="recipient_contact" class="checkout-input" value="{{ old('recipient_contact', $user->contact_no) }}" required>
                    </div>

                    <div class="checkout-form-group">
                        <label class="checkout-label">Province *</label>
                        <input type="text" name="province" class="checkout-input" value="{{ old('province', $user->province) }}" required>
                    </div>

                    <div class="checkout-form-group">
                        <label class="checkout-label">Municipality / City *</label>
                        <input type="text" name="municipality" class="checkout-input" value="{{ old('municipality', $user->municipality) }}" required>
                    </div>

                    <div class="checkout-form-group">
                        <label class="checkout-label">Barangay *</label>
                        <input type="text" name="barangay" class="checkout-input" value="{{ old('barangay', $user->barangay) }}" required>
                    </div>

                    <div class="checkout-form-group">
                        <label class="checkout-label">Street / House No. *</label>
                        <input type="text" name="street_address" class="checkout-input" value="{{ old('street_address', $user->street_address) }}" required>
                    </div>

                    <div class="checkout-form-group checkout-form-full">
                        <label class="checkout-label">Order Notes (Optional)</label>
                        <textarea name="notes" rows="2" class="checkout-input" placeholder="Notes for seller or courier delivery landmarks..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Payment Card -->
            <div class="checkout-card">
                <h2 class="checkout-card-title">2. Select Payment Method</h2>
                <div class="payment-method-group">
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="COD" checked>
                        <div>
                            <div class="payment-title">Cash on Delivery (COD)</div>
                            <div class="payment-desc">Pay the courier upon parcel arrival.</div>
                        </div>
                    </label>

                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="GCash">
                        <div>
                            <div class="payment-title">GCash / E-Wallet</div>
                            <div class="payment-desc">Instant digital wallet confirmation.</div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Order Summary Side Card -->
        <div class="checkout-card">
            <h2 class="checkout-card-title">Items in Order</h2>

            @foreach($cart as $item)
                <div class="checkout-item-row">
                    <div>
                        <div class="checkout-item-title">{{ $item['name'] }}</div>
                        <div class="checkout-item-meta">{{ $item['quantity'] }}x @ ₱{{ number_format($item['price'], 2) }}</div>
                    </div>
                    <div class="checkout-item-price">
                        ₱{{ number_format($item['price'] * $item['quantity'], 2) }}
                    </div>
                </div>
            @endforeach

            <div class="checkout-summary-row" style="margin-top: 1rem;">
                <span>Subtotal</span>
                <span>₱{{ number_format($subtotal, 2) }}</span>
            </div>

            <div class="checkout-summary-row">
                <span>Shipping Fee</span>
                <span>₱{{ number_format($shippingFee, 2) }}</span>
            </div>

            <div class="checkout-total-row">
                <span>Total Amount</span>
                <span class="checkout-total-amount">₱{{ number_format($total, 2) }}</span>
            </div>

            <button type="submit" class="checkout-btn-place-order">
                Place Order (₱{{ number_format($total, 2) }})
            </button>
        </div>
    </form>
</div>
@endsection