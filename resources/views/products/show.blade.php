@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
@endpush

@section('content')
<div class="container product-show-container">
    <a href="{{ route('home') }}" class="product-back-link">
        ← Back to Marketplace
    </a>

    <div class="product-show-layout">
        <!-- Product Image Preview -->
        <div class="product-gallery-box">
            @if($product->image_path)
                <img src="{{ asset('storage/' . $product->image_path) }}" 
                     alt="{{ $product->name }}" 
                     class="product-gallery-img">
            @else
                <div class="product-gallery-placeholder">📦</div>
            @endif
        </div>

        <!-- Product Purchase Information -->
        <div class="product-info-column">
            <div>
                <span class="product-badge">{{ $product->category }}</span>
                <h1 class="product-detail-title">
                    {{ $product->name }}
                </h1>

                <!-- Seller Details -->
                <div class="product-seller-card">
                    <div class="product-seller-label">Sold By</div>
                    <div class="product-seller-name">
                        {{ $product->seller->business_name ?? 'EZiCart Verified Merchant' }}
                    </div>
                    <div class="product-seller-location">
                        Location: {{ $product->seller->municipality ?? 'Majayjay' }}, {{ $product->seller->province ?? 'Laguna' }}
                    </div>
                </div>

                <!-- Price Block -->
                <div class="product-detail-price">
                    ₱{{ number_format($product->price, 2) }}
                </div>

                <!-- Description -->
                <div class="product-detail-desc">
                    {{ $product->description ?? 'No description provided by the seller for this item.' }}
                </div>
            </div>

            <!-- Role Guarded Cart Submission -->
            @auth
                @if(auth()->user()->role === 'buyer')
                    <form action="{{ route('cart.add', $product->id) }}" method="POST" class="product-cart-form">
                        @csrf
                        <div class="product-qty-row">
                            <label class="product-qty-label">Quantity:</label>
                            <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock }}" class="product-qty-input">
                            <span class="product-stock-available">
                                {{ $product->stock }} pieces available
                            </span>
                        </div>

                        <div class="product-actions-row">
                            <button type="submit" class="btn-primary product-btn-add-cart">
                                🛒 Add to Cart
                            </button>
                        </div>
                    </form>
                @else
                    <div class="role-restriction-box">
                        <span class="role-restriction-icon">🔒</span>
                        <div>
                            <div class="role-restriction-title">Role Restriction Active</div>
                            <div class="role-restriction-text">
                                You are signed in as a <strong>{{ ucfirst(auth()->user()->role) }}</strong>. Only Buyer accounts can purchase items and use the cart.
                            </div>
                        </div>
                    </div>
                @endif
            @else
                <form action="{{ route('cart.add', $product->id) }}" method="POST" class="product-cart-form">
                    @csrf
                    <div class="product-qty-row">
                        <label class="product-qty-label">Quantity:</label>
                        <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock }}" class="product-qty-input">
                        <span class="product-stock-available">
                            {{ $product->stock }} pieces available
                        </span>
                    </div>

                    <div class="product-actions-row">
                        <button type="submit" class="btn-primary product-btn-add-cart">
                            🛒 Add to Cart
                        </button>
                    </div>
                </form>
            @endauth
        </div>
    </div>
</div>
@endsection