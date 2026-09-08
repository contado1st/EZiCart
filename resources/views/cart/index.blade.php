@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/cart.css') }}">
@endpush

@section('content')
<div class="cart-container">
    <div class="cart-header-row">
        <div>
            <h1 class="cart-title">My Shopping Cart</h1>
            <p class="cart-subtitle">Review selected items before proceeding to checkout.</p>
        </div>
        <a href="{{ route('home') }}" class="cart-continue-link">← Continue Shopping</a>
    </div>

    @if(session('success'))
        <div class="alert-success" style="margin-bottom: 1.5rem;">
            ✅ {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="background-color: var(--ezipink-50); color: var(--ezipink-600); border-left: 4px solid var(--ezipink-500); padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; font-weight: 600;">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    @if(empty($cart))
        <div class="cart-empty-state">
            <div class="cart-empty-icon">🛒</div>
            <h2 class="cart-empty-title">Your cart is empty</h2>
            <p class="cart-empty-desc">You haven't added any products to your shopping cart yet.</p>
            <a href="{{ route('home') }}" class="btn-primary" style="padding: 0.625rem 1.5rem; text-decoration: none;">
                Explore Marketplace
            </a>
        </div>
    @else
        <div class="cart-grid-layout">
            <!-- Items Table -->
            <div class="cart-items-card">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Unit Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cart as $item)
                            <tr>
                                <td>
                                    <div class="cart-product-cell">
                                        @if($item['image_path'])
                                            <img src="{{ asset('storage/' . $item['image_path']) }}" alt="{{ $item['name'] }}" class="cart-product-img">
                                        @else
                                            <div class="cart-product-placeholder">📦</div>
                                        @endif
                                        <div>
                                            <a href="{{ route('product.show', $item['id']) }}" class="cart-product-name">
                                                {{ $item['name'] }}
                                            </a>
                                            <div class="cart-product-seller">
                                                Sold by: <strong>{{ $item['business_name'] }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="cart-price">
                                    ₱{{ number_format($item['price'], 2) }}
                                </td>
                                <td>
                                    <form action="{{ route('cart.update', $item['id']) }}" method="POST" class="cart-qty-form">
                                        @csrf
                                        @method('PATCH')
                                        <input type="number" name="quantity" value="{{ $item['quantity'] }}" min="1" max="{{ $item['stock'] }}" class="cart-qty-input">
                                        <button type="submit" class="cart-btn-update">Update</button>
                                    </form>
                                </td>
                                <td class="cart-price">
                                    ₱{{ number_format($item['price'] * $item['quantity'], 2) }}
                                </td>
                                <td>
                                    <form action="{{ route('cart.remove', $item['id']) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="cart-btn-remove">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Order Summary -->
            <div class="cart-summary-card">
                <h3 class="cart-summary-title">Order Summary</h3>
                
                <div class="cart-summary-row">
                    <span>Items Count</span>
                    <span>{{ array_sum(array_column($cart, 'quantity')) }} items</span>
                </div>

                <div class="cart-summary-row">
                    <span>Subtotal</span>
                    <span>₱{{ number_format($subtotal, 2) }}</span>
                </div>

                <div class="cart-summary-row">
                    <span>Estimated Shipping</span>
                    <span>Calculated at checkout</span>
                </div>

                <div class="cart-summary-total-row">
                    <span>Total</span>
                    <span class="cart-summary-total-amount">₱{{ number_format($subtotal, 2) }}</span>
                </div>

                <a href="{{ route('checkout.index') }}" class="cart-btn-checkout">
                    Proceed to Checkout →
                </a>

                <a href="{{ route('home') }}" class="cart-continue-link">
                    Continue Shopping
                </a>
            </div>
        </div>
    @endif
</div>
@endsection