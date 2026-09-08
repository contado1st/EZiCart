@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
    <link rel="stylesheet" href="{{ asset('css/reviews.css') }}">
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
                <h1 class="product-detail-title">{{ $product->name }}</h1>

                <!-- Rating Quick Peek -->
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                    <span class="star-rating-stars">
                        @for($i = 1; $i <= 5; $i++)
                            {{ $i <= round($product->average_rating) ? '★' : '☆' }}
                        @endfor
                    </span>
                    <span style="font-size: 0.875rem; font-weight: 700; color: var(--slate-700);">
                        {{ $product->average_rating > 0 ? $product->average_rating : 'New' }}
                    </span>
                    <span style="font-size: 0.8125rem; color: var(--slate-400);">
                        ({{ $product->review_count }} reviews)
                    </span>
                </div>

                <!-- Seller Details -->
                <div class="product-seller-card">
                    <div class="product-seller-label">Sold By</div>
                    <div class="product-seller-name">{{ $product->seller->business_name ?? 'EZiCart Verified Merchant' }}</div>
                    <div class="product-seller-location">
                        Location: {{ $product->seller->municipality ?? 'Majayjay' }}, {{ $product->seller->province ?? 'Laguna' }}
                    </div>
                </div>

                <!-- Price Block -->
                <div class="product-detail-price" id="basePriceDisplay">
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

                        @if($product->variations->count() > 0)
                            <div style="margin-bottom: 1.25rem;">
                                <label style="display: block; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; margin-bottom: 0.5rem; color: var(--slate-700);">
                                    Select Variation (Color / Size / Option)
                                </label>
                                <select name="variation_id" id="variationSelect" class="search-input" style="width: 100%; border: 1px solid var(--slate-300);" required>
                                    <option value="">-- Choose an option --</option>
                                    @foreach($product->variations as $variation)
                                        <option value="{{ $variation->id }}" 
                                                data-adjustment="{{ $variation->price_adjustment }}"
                                                data-stock="{{ $variation->stock }}">
                                            {{ $variation->type }}: {{ $variation->value }} 
                                            @if($variation->price_adjustment != 0)
                                                ({{ $variation->price_adjustment > 0 ? '+' : '' }}₱{{ number_format($variation->price_adjustment, 2) }})
                                            @endif
                                            &bull; Stock: {{ $variation->stock }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="product-qty-row">
                            <label class="product-qty-label">Quantity:</label>
                            <input type="number" name="quantity" id="qtyInput" value="1" min="1" max="{{ $product->stock }}" class="product-qty-input">
                            <span class="product-stock-available" id="stockDisplay">
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
                <div class="role-restriction-box">
                    <span class="role-restriction-icon">💡</span>
                    <div>
                        <div class="role-restriction-title">Buyer Sign-in Required</div>
                        <div class="role-restriction-text">
                            Please <a href="{{ route('login') }}" style="color: var(--ezipink-500); font-weight: 700;">login</a> or <a href="{{ route('register') }}" style="color: var(--ezipink-500); font-weight: 700;">create a buyer account</a> to purchase this item.
                        </div>
                    </div>
                </div>
            @endauth
        </div>
    </div>

    <!-- Customer Reviews & Feedback Section -->
    <div class="reviews-section">
        <div class="reviews-header">
            <h2 class="reviews-title">Customer Reviews & Ratings</h2>
        </div>

        <div class="rating-overview-card">
            <div>
                <div class="rating-score-num">{{ $product->average_rating > 0 ? $product->average_rating : '0.0' }}</div>
                <div class="star-rating-stars">
                    @for($i = 1; $i <= 5; $i++)
                        {{ $i <= round($product->average_rating) ? '★' : '☆' }}
                    @endfor
                </div>
            </div>
            <div>
                <strong style="font-size: 0.9375rem; color: var(--slate-800);">Based on {{ $product->review_count }} verified reviews</strong>
                <p style="margin: 0; font-size: 0.8125rem; color: var(--slate-500);">Ratings submitted by verified buyers upon completed delivery.</p>
            </div>
        </div>

        @forelse($product->reviews()->with('buyer')->latest()->get() as $review)
            <div class="review-card-item">
                <div class="review-card-top">
                    <div>
                        <span class="reviewer-name">{{ $review->buyer->first_name }} {{ substr($review->buyer->last_name, 0, 1) }}.</span>
                        <span class="star-rating-stars" style="margin-left: 0.5rem;">
                            @for($i = 1; $i <= 5; $i++)
                                {{ $i <= $review->rating ? '★' : '☆' }}
                            @endfor
                        </span>
                    </div>
                    <span class="review-date">{{ $review->created_at->format('M d, Y') }}</span>
                </div>
                <div class="review-comment-body">
                    {{ $review->comment ?? 'Customer provided a rating without written commentary.' }}
                </div>
            </div>
        @empty
            <div style="text-align: center; padding: 2rem; color: var(--slate-400); font-size: 0.875rem;">
                No reviews yet for this product. Be the first to purchase and review!
            </div>
        @endforelse
    </div>
</div>

<script>
    const basePrice = {{ $product->price }};
    const variationSelect = document.getElementById('variationSelect');
    const priceDisplay = document.getElementById('basePriceDisplay');
    const stockDisplay = document.getElementById('stockDisplay');
    const qtyInput = document.getElementById('qtyInput');

    if (variationSelect) {
        variationSelect.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            if (this.value) {
                const adjustment = parseFloat(selected.dataset.adjustment) || 0;
                const newPrice = basePrice + adjustment;
                const varStock = parseInt(selected.dataset.stock) || 0;

                priceDisplay.textContent = '₱' + newPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                stockDisplay.textContent = varStock + ' variation pieces available';
                qtyInput.max = varStock > 0 ? varStock : 1;
            } else {
                priceDisplay.textContent = '₱' + basePrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                stockDisplay.textContent = '{{ $product->stock }} pieces available';
                qtyInput.max = {{ $product->stock }};
            }
        });
    }
</script>
@endsection