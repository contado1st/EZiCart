@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/checkout.css') }}">
    <style>
        .checkout-addr-option {
            display: block;
            border: 1px solid var(--slate-200);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
        }
        .checkout-addr-option:hover {
            border-color: var(--ez-primary);
        }
        .checkout-addr-option.selected {
            border: 2px solid var(--ez-primary);
            background: #fffbfa;
        }
    </style>
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
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h2 class="checkout-card-title" style="margin: 0;">1. Delivery Address</h2>
                    <a href="{{ route('buyer.addresses.index') }}" target="_blank" style="font-size: 0.8rem; color: var(--ez-primary); font-weight: 700; text-decoration: none;">
                        Manage Saved Addresses ↗
                    </a>
                </div>

                @if($addresses->isNotEmpty())
                    <div style="margin-bottom: 1rem;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: var(--slate-700); margin-bottom: 0.5rem; display: block;">
                            Select Delivery Location:
                        </label>

                        @foreach($addresses as $index => $addr)
                            <label class="checkout-addr-option {{ ($addr->is_default || $index === 0) ? 'selected' : '' }}" id="addrOption_{{ $addr->id }}">
                                <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                                    <input type="radio" name="address_choice" value="{{ $addr->id }}" {{ ($addr->is_default || $index === 0) ? 'checked' : '' }} onchange="selectAddress({{ json_encode($addr) }})" style="margin-top: 3px;">
                                    <div style="flex: 1;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <strong style="color: var(--slate-800); font-size: 0.9rem;">
                                                {{ $addr->recipient_name }}
                                                <span style="font-weight: normal; color: var(--slate-500); margin-left: 0.5rem;">({{ $addr->phone_number }})</span>
                                            </strong>
                                            @if($addr->is_default)
                                                <span style="background: var(--ez-primary); color: white; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;">Default</span>
                                            @endif
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--slate-600); margin-top: 0.25rem; line-height: 1.4;">
                                            <span style="font-weight: 600; color: var(--slate-700);">[{{ $addr->label }}]</span>
                                            {{ $addr->street_address }}, {{ $addr->barangay }}, {{ $addr->municipality }}, {{ $addr->province }}
                                        </div>
                                    </div>
                                </div>
                            </label>
                        @endforeach

                        <div style="margin-top: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--slate-700); cursor: pointer;">
                                <input type="radio" name="address_choice" value="custom" id="customAddressRadio" onchange="toggleCustomAddress(true)">
                                <strong>+ Deliver to a different / new address</strong>
                            </label>
                        </div>
                    </div>
                @endif

                <!-- Dynamic / Hidden Address Fields Submitted with Form -->
                @php
                    $initAddr = $defaultAddress ?? $addresses->first();
                @endphp

                <div id="addressInputContainer" style="{{ $addresses->isNotEmpty() ? 'display: none;' : '' }} margin-top: 1rem; border-top: 1px dashed var(--slate-200); padding-top: 1rem;">
                    <div class="checkout-form-grid">
                        <div class="checkout-form-group">
                            <label class="checkout-label">Recipient Name *</label>
                            <input type="text" name="recipient_name" id="field_recipient_name" class="checkout-input" value="{{ old('recipient_name', $initAddr ? $initAddr->recipient_name : ($user->first_name . ' ' . $user->last_name)) }}" required>
                        </div>

                        <div class="checkout-form-group">
                            <label class="checkout-label">Contact Number *</label>
                            <input type="text" name="recipient_contact" id="field_recipient_contact" class="checkout-input" value="{{ old('recipient_contact', $initAddr ? $initAddr->phone_number : $user->contact_no) }}" required>
                        </div>

                        <div class="checkout-form-group">
                            <label class="checkout-label">Province *</label>
                            <input type="text" name="province" id="field_province" class="checkout-input" value="{{ old('province', $initAddr ? $initAddr->province : $user->province) }}" required>
                        </div>

                        <div class="checkout-form-group">
                            <label class="checkout-label">Municipality / City *</label>
                            <input type="text" name="municipality" id="field_municipality" class="checkout-input" value="{{ old('municipality', $initAddr ? $initAddr->municipality : $user->municipality) }}" required>
                        </div>

                        <div class="checkout-form-group">
                            <label class="checkout-label">Barangay *</label>
                            <input type="text" name="barangay" id="field_barangay" class="checkout-input" value="{{ old('barangay', $initAddr ? $initAddr->barangay : $user->barangay) }}" required>
                        </div>

                        <div class="checkout-form-group">
                            <label class="checkout-label">Street / House No. *</label>
                            <input type="text" name="street_address" id="field_street_address" class="checkout-input" value="{{ old('street_address', $initAddr ? $initAddr->street_address : $user->street_address) }}" required>
                        </div>
                    </div>
                </div>

                <div class="checkout-form-group checkout-form-full" style="margin-top: 1rem;">
                    <label class="checkout-label">Delivery Landmarks / Order Notes (Optional)</label>
                    <textarea name="notes" rows="2" class="checkout-input" placeholder="Nearby landmarks, special delivery instructions..."></textarea>
                </div>
            </div>

            <!-- Payment Card -->
            <div class="checkout-card">
                <h2 class="checkout-card-title">2. Select Payment Method</h2>
                <div class="payment-method-group">
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="COD" checked>
                        <div>
                            <div class="payment-title">💵 Cash on Delivery (COD)</div>
                            <div class="payment-desc">Pay directly to courier upon doorstep parcel arrival.</div>
                        </div>
                    </label>

                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="GCash">
                        <div>
                            <div class="payment-title">📱 GCash / E-Wallet</div>
                            <div class="payment-desc">Fast payment via electronic wallet.</div>
                        </div>
                    </label>

                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="Bank Transfer">
                        <div>
                            <div class="payment-title">🏦 Bank Transfer</div>
                            <div class="payment-desc">Online banking transfer reference confirmation.</div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Order Summary Side Card -->
        <div class="checkout-card" style="align-self: start;">
            <h2 class="checkout-card-title">Order Summary</h2>

            @foreach($cart as $item)
                <div class="checkout-item-row" style="padding: 0.75rem 0; border-bottom: 1px solid var(--slate-100);">
                    <div>
                        <div class="checkout-item-title" style="font-weight: 700;">{{ $item['name'] }}</div>
                        @if(!empty($item['variation_info']))
                            <div style="font-size: 0.75rem; color: var(--ez-primary); font-weight: 600;">
                                {{ $item['variation_info'] }}
                            </div>
                        @endif
                        <div class="checkout-item-meta" style="font-size: 0.8rem; color: var(--slate-500);">
                            {{ $item['quantity'] }} &times; ₱{{ number_format($item['price'], 2) }}
                        </div>
                    </div>
                    <div class="checkout-item-price" style="font-weight: 700;">
                        ₱{{ number_format($item['price'] * $item['quantity'], 2) }}
                    </div>
                </div>
            @endforeach

            <div class="checkout-summary-row" style="margin-top: 1rem; display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--slate-600); margin-bottom: 0.4rem;">
                <span>Merchandise Subtotal:</span>
                <span>₱{{ number_format($subtotal, 2) }}</span>
            </div>

            @if(!empty($appliedVoucher))
                <div class="checkout-summary-row" style="display: flex; justify-content: space-between; font-size: 0.85rem; color: #059669; margin-bottom: 0.4rem;">
                    <span>Voucher Discount ({{ $appliedVoucher['code'] }}):</span>
                    <span>-₱{{ number_format($discount, 2) }}</span>
                </div>
            @endif

            <div class="checkout-summary-row" style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--slate-600); margin-bottom: 0.75rem;">
                <span>Shipping Fee:</span>
                <span>₱{{ number_format($shippingFee, 2) }}</span>
            </div>

            <div class="checkout-total-row" style="border-top: 1px solid var(--slate-200); padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <span style="font-weight: 700; font-size: 1rem;">Total Payment:</span>
                <span class="checkout-total-amount" style="font-weight: 800; font-size: 1.3rem; color: var(--ez-primary);">
                    ₱{{ number_format($total, 2) }}
                </span>
            </div>

            <button type="submit" class="checkout-btn-place-order">
                Place Order (₱{{ number_format($total, 2) }})
            </button>
        </div>
    </form>
</div>

<script>
    function selectAddress(addr) {
        document.querySelectorAll('.checkout-addr-option').forEach(el => el.classList.remove('selected'));
        const optEl = document.getElementById('addrOption_' + addr.id);
        if (optEl) optEl.classList.add('selected');

        document.getElementById('field_recipient_name').value = addr.recipient_name;
        document.getElementById('field_recipient_contact').value = addr.phone_number;
        document.getElementById('field_province').value = addr.province;
        document.getElementById('field_municipality').value = addr.municipality;
        document.getElementById('field_barangay').value = addr.barangay;
        document.getElementById('field_street_address').value = addr.street_address;

        document.getElementById('addressInputContainer').style.display = 'none';
    }

    function toggleCustomAddress(show) {
        document.querySelectorAll('.checkout-addr-option').forEach(el => el.classList.remove('selected'));
        document.getElementById('addressInputContainer').style.display = show ? 'block' : 'none';
        if (show) {
            document.getElementById('field_recipient_name').value = '';
            document.getElementById('field_recipient_contact').value = '';
            document.getElementById('field_province').value = '';
            document.getElementById('field_municipality').value = '';
            document.getElementById('field_barangay').value = '';
            document.getElementById('field_street_address').value = '';
        }
    }
</script>
@endsection