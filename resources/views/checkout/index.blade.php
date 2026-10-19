@extends('layouts.app')

@push('styles')
    @vite('resources/css/buyer/checkout.css')
@endpush

@section('content')
    <div class="checkout-container">
        <div class="checkout-header">
            <h1 class="checkout-title">Checkout & Finalize Order</h1>
            <p class="checkout-subtitle">Confirm your delivery location and payment option to place your order.</p>
        </div>

        @if (session('error'))
            <div class="u-extracted-a243a89dcf">
                <x-shop-icon name="warning" /> {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('checkout.process') }}" method="POST" class="checkout-layout">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $token }}">
            <input type="hidden" name="quote_hash" value="{{ $quote['hash'] }}">

            <!-- Delivery & Payment Section -->
            <div>
                <!-- Address Card -->
                <div class="checkout-card">
                    <h2 class="checkout-card-title">1. Delivery Information</h2>
                    <div class="checkout-form-grid">
                        <div class="checkout-form-group">
                            <label class="checkout-label">Recipient Name *</label>
                            <input type="text" name="recipient_name" class="checkout-input"
                                value="{{ old('recipient_name', $user->first_name . ' ' . $user->last_name) }}" required>
                        </div>

                        <div class="checkout-form-group">
                            <label class="checkout-label">Contact Number *</label>
                            <input type="text" name="recipient_contact" class="checkout-input"
                                value="{{ old('recipient_contact', $user->contact_no) }}" required>
                        </div>

                        <div class="checkout-form-full">
                            @include('components.psgc-address-fields', ['prefix' => 'checkout-address', 'address' => $user, 'postalRequired' => false])
                        </div>

                        <div class="checkout-form-group checkout-form-full">
                            <label class="checkout-label">Order Notes (Optional)</label>
                            <textarea name="notes" rows="2" maxlength="200" class="checkout-input"
                                placeholder="Notes for seller or courier delivery landmarks...">{{ old('notes') }}</textarea>
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

                        <p class="payment-desc" role="status">Cash on delivery is the available payment method while payment verification for other methods is being configured.</p>
                    </div>
                </div>
            </div>

            <!-- Order Summary Side Card -->
            <div class="checkout-card">
                <h2 class="checkout-card-title">Items in Order</h2>

                @foreach ($cart as $item)
                    <div class="checkout-item-row">
                        <div>
                            <div class="checkout-item-title">{{ $item['name'] }}</div>
                            <div class="checkout-item-meta">{{ $item['quantity'] }}x @
                                ₱{{ number_format($item['price'], 2) }}</div>
                        </div>
                        <div class="checkout-item-price">
                            ₱{{ number_format($item['price'] * $item['quantity'], 2) }}
                        </div>
                    </div>
                @endforeach

                <div class="checkout-summary-row u-extracted-dab43fb936">
                    <span>Subtotal</span>
                    <span>₱{{ number_format($subtotal, 2) }}</span>
                </div>

                <div class="checkout-summary-row">
                    <span>Shipping ({{ count($quote['groups']) }} seller shipments at ₱{{ number_format(config('commerce.shipping_per_seller_cents') / 100, 2) }} each)</span>
                    <span>₱{{ number_format($shippingFee, 2) }}</span>
                </div>

                @if ($discount > 0)
                    <div class="checkout-summary-row"><span>Voucher discount</span><span>−₱{{ number_format($discount, 2) }}</span></div>
                @endif

                @if ($errors->any())
                    <div role="alert">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>
                @endif

                <div class="checkout-total-row">
                    <span>Total Amount</span>
                    <span class="checkout-total-amount">₱{{ number_format($total, 2) }}</span>
                </div>

                <button type="submit" class="checkout-btn-place-order">
                    Place Order (₱{{ number_format($total, 2) }})
                </button>
            </div>
        </form>
        <form method="POST" action="{{ route('checkout.voucher.apply') }}" class="checkout-card">
            @csrf
            <label for="voucher-code">Voucher code</label>
            <input id="voucher-code" name="voucher_code" maxlength="50" class="checkout-input" required value="{{ old('voucher_code', $appliedVoucher['code'] ?? '') }}">
            <button class="checkout-btn-place-order" type="submit">Apply voucher</button>
        </form>
        @if ($appliedVoucher)
            <form method="POST" action="{{ route('checkout.voucher.remove') }}">@csrf @method('DELETE')<button type="submit">Remove voucher</button></form>
        @endif
    </div>
@endsection
