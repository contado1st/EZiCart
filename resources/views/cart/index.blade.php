@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/cart.css') }}">
@endpush

@section('content')
<div class="ez-container-fluid cart-container">
    <div class="cart-header-row">
        <div>
            <h1 class="cart-title">My Shopping Cart</h1>
            <p class="cart-subtitle">Select the items you want to purchase right now.</p>
        </div>
        <a href="{{ route('home') }}" class="cart-continue-link">← Continue Shopping</a>
    </div>

    @if(session('success'))
        <div class="alert-success" style="margin-bottom: 1.5rem;">
            ✅ {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="background-color: var(--ezipink-50, #fff0f4); color: var(--ezipink-600, #c91e50); border-left: 4px solid var(--ezipink-500, #e62e63); padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; font-weight: 600;">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    @if(empty($cart))
        <div class="cart-empty-state">
            <div class="cart-empty-icon">🛒</div>
            <h2 class="cart-empty-title">Your cart is empty</h2>
            <p class="cart-empty-desc">You haven't added any products to your shopping cart yet.</p>
            <a href="{{ route('home') }}" class="btn-primary" style="padding: 0.625rem 1.5rem; text-decoration: none; border-radius: 50px;">
                Explore Marketplace
            </a>
        </div>
    @else
        <form action="{{ route('checkout.index') }}" method="GET" id="checkoutForm">
            <div class="cart-grid-layout">
                <!-- Items Table Card -->
                <div class="cart-items-card">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">
                                    <input type="checkbox" id="selectAllCheckbox" checked style="transform: scale(1.2); cursor: pointer;" title="Select All">
                                </th>
                                <th>Product</th>
                                <th>Unit Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cart as $cartKey =>$item)
                                <tr class="cart-item-row">
                                    <td style="text-align: center;">
                                        <input 
                                            type="checkbox" 
                                            name="selected_items[]" 
                                            value="{{ $cartKey }}" 
                                            class="item-checkbox" 
                                            checked 
                                            data-price="{{ $item['price'] }}"
                                            data-qty="{{ $item['quantity'] }}"
                                            style="transform: scale(1.2); cursor: pointer;"
                                        >
                                    </td>
                                    <td>
                                        <div class="cart-product-cell">
                                            @if(!empty($item['image_path']))
                                                <img src="{{ asset('storage/' . $item['image_path']) }}" alt="{{ $item['name'] }}" class="cart-product-img">
                                            @else
                                                <div class="cart-product-placeholder">📦</div>
                                            @endif
                                            <div>
                                                <a href="{{ route('product.show', $item['product_id'] ?? $item['id']) }}" class="cart-product-name">
                                                    {{ $item['name'] }}
                                                </a>
                                                
                                                @if(!empty($item['variation_info']))
                                                    <div class="cart-product-variation">
                                                        Option: {{ $item['variation_info'] }}
                                                    </div>
                                                @endif

                                                <div class="cart-product-seller">
                                                    Sold by: <strong>{{ $item['business_name'] ?? 'Merchant' }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="cart-price">
                                        ₱{{ number_format($item['price'], 2) }}
                                    </td>
                                    <td>
                                        <div class="cart-qty-form">
                                            <input 
                                                type="number" 
                                                value="{{ $item['quantity'] }}" 
                                                min="1" 
                                                max="{{ $item['stock'] ?? 99 }}" 
                                                class="cart-qty-input"
                                                onchange="updateCartQuantity('{{ $cartKey }}', this.value)"
                                            >
                                        </div>
                                    </td>
                                    <td class="cart-price cart-subtotal-price">
                                        ₱{{ number_format($item['price'] *$item['quantity'], 2) }}
                                    </td>
                                    <td>
                                        <button type="button" onclick="removeItem('{{ $cartKey }}')" class="cart-btn-remove">Remove</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Order Summary Card -->
                <div class="cart-summary-card">
                    <h3 class="cart-summary-title">Order Summary</h3>
                    
                    <div class="cart-summary-row">
                        <span>Selected Items</span>
                        <span id="selectedCountDisplay">0 items</span>
                    </div>

                    <div class="cart-summary-row">
                        <span>Subtotal</span>
                        <span id="subtotalDisplay">₱0.00</span>
                    </div>

                    <div class="cart-summary-row">
                        <span>Estimated Shipping</span>
                        <span>Calculated at checkout</span>
                    </div>

                    <div class="cart-summary-total-row">
                        <span>Total</span>
                        <span class="cart-summary-total-amount" id="totalDisplay">₱0.00</span>
                    </div>

                    <button type="submit" class="cart-btn-checkout" id="checkoutBtn">
                        Proceed to Checkout →
                    </button>

                    <a href="{{ route('home') }}" class="cart-continue-link" style="margin-top: 1rem;">
                        Continue Shopping
                    </a>
                </div>
            </div>
        </form>

        <!-- Hidden Forms for AJAX/JS Quantity Update & Delete -->
        <form id="updateQtyForm" action="" method="POST" style="display: none;">
            @csrf
            @method('PATCH')
            <input type="hidden" name="quantity" id="updateQtyInput">
        </form>

        <form id="removeItemForm" action="" method="POST" style="display: none;">
            @csrf
            @method('DELETE')
        </form>
    @endif
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('selectAllCheckbox');
        const checkboxes = document.querySelectorAll('.item-checkbox');
        const selectedCountDisplay = document.getElementById('selectedCountDisplay');
        const subtotalDisplay = document.getElementById('subtotalDisplay');
        const totalDisplay = document.getElementById('totalDisplay');
        const checkoutBtn = document.getElementById('checkoutBtn');

        function calculateTotals() {
            let totalAmount = 0;
            let totalCount = 0;

            checkboxes.forEach(cb => {
                if (cb.checked) {
                    const price = parseFloat(cb.dataset.price) || 0;
                    const qty = parseInt(cb.dataset.qty) || 0;
                    totalAmount += (price * qty);
                    totalCount += qty;
                }
            });

            selectedCountDisplay.textContent = totalCount + (totalCount === 1 ? ' item' : ' items');
            subtotalDisplay.textContent = '₱' + totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            totalDisplay.textContent = '₱' + totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            if (totalCount === 0) {
                checkoutBtn.disabled = true;
                checkoutBtn.style.opacity = '0.5';
                checkoutBtn.style.cursor = 'not-allowed';
            } else {
                checkoutBtn.disabled = false;
                checkoutBtn.style.opacity = '1';
                checkoutBtn.style.cursor = 'pointer';
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes.forEach(cb => cb.checked = this.checked);
                calculateTotals();
            });
        }

        checkboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                if (!this.checked) selectAll.checked = false;
                if (Array.from(checkboxes).every(c => c.checked)) selectAll.checked = true;
                calculateTotals();
            });
        });

        calculateTotals();
    });

    function updateCartQuantity(cartKey, newQty) {
        if (newQty < 1) return;
        const form = document.getElementById('updateQtyForm');
        form.action = `/cart/update/${cartKey}`;
        document.getElementById('updateQtyInput').value = newQty;
        form.submit();
    }

    function removeItem(cartKey) {
        if (confirm('Are you sure you want to remove this item from your cart?')) {
            const form = document.getElementById('removeItemForm');
            form.action = `/cart/remove/${cartKey}`;
            form.submit();
        }
    }
</script>
@endsection