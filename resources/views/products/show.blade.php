@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
    <link rel="stylesheet" href="{{ asset('css/reviews.css') }}">
@endpush

@section('content')
<div class="ez-container-fluid product-show-container">
    
    <!-- Breadcrumb / Back Navigation -->
    <div class="product-breadcrumb">
        <a href="{{ route('home') }}" class="product-back-link">Home</a>
        <span class="breadcrumb-separator">›</span>
        <span>{{ $product->category }}</span>
        <span class="breadcrumb-separator">›</span>
        <span class="breadcrumb-current">{{ $product->name }}</span>
    </div>

    <!-- Main Shopee-Style Product Card -->
    <div class="shopee-product-card">
        <div class="shopee-product-main">
            
            <!-- Left Column: Fixed-Size Clickable Image Gallery -->
            <div class="shopee-gallery-col">
                <div class="shopee-image-wrapper" onclick="openImageModal()">
                    @if($product->image_path)
                        <img id="mainProductImage" 
                             src="{{ asset('storage/' . $product->image_path) }}" 
                             alt="{{ $product->name }}" 
                             class="shopee-main-img">
                        <div class="image-zoom-hint">🔍 Click to enlarge</div>
                    @else
                        <div class="shopee-image-placeholder">📦</div>
                    @endif
                </div>
            </div>

            <!-- Right Column: Product Specs & Purchase Options -->
            <div class="shopee-info-col">
                <h1 class="shopee-product-title">{{ $product->name }}</h1>

                <!-- Shopee Rating Bar (Star | Ratings | Sold) -->
                <div class="shopee-metrics-bar">
                    <div class="shopee-metric-item">
                        <span class="shopee-score-text">{{ $product->average_rating > 0 ? number_format($product->average_rating, 1) : '5.0' }}</span>
                        <div class="star-rating-stars">
                            @for($i = 1; $i <= 5; $i++)
                                {{ $i <= round($product->average_rating ?: 5) ? '★' : '☆' }}
                            @endfor
                        </div>
                    </div>
                    <div class="shopee-metric-divider"></div>
                    <div class="shopee-metric-item">
                        <span class="shopee-metric-value">{{ $product->review_count ?? 0 }}</span>
                        <span class="shopee-metric-label">Ratings</span>
                    </div>
                    <div class="shopee-metric-divider"></div>
                    <div class="shopee-metric-item">
                        <span class="shopee-metric-value">{{ $product->sold_count ?? 0 }}</span>
                        <span class="shopee-metric-label">Sold</span>
                    </div>
                </div>

                <!-- Price Display Banner -->
                <div class="shopee-price-banner">
                    <span class="shopee-currency">₱</span>
                    <span class="shopee-price-amount" id="basePriceDisplay">{{ number_format($product->price, 2) }}</span>
                </div>

                <!-- Role Guarded Cart Form -->
                @auth
                    @if(auth()->user()->role === 'buyer')
                        <form action="{{ route('cart.add', $product->id) }}" method="POST" class="shopee-cart-form">
                            @csrf

                            @if($product->variations->count() > 0)
                                <div class="shopee-option-row">
                                    <label class="shopee-row-label">Option</label>
                                    <div class="shopee-variations-grid">
                                        <select name="variation_id" id="variationSelect" class="shopee-select-control" required>
                                            <option value="">-- Choose Option --</option>
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
                                </div>
                            @endif

                            <div class="shopee-option-row">
                                <label class="shopee-row-label">Quantity</label>
                                <div class="shopee-qty-controls">
                                    <button type="button" class="qty-btn" onclick="adjustQty(-1)">-</button>
                                    <input type="number" name="quantity" id="qtyInput" value="1" min="1" max="{{ $product->stock }}" class="shopee-qty-input">
                                    <button type="button" class="qty-btn" onclick="adjustQty(1)">+</button>
                                    <span class="shopee-stock-label" id="stockDisplay">{{ $product->stock }} pieces available</span>
                                </div>
                            </div>

                            <div class="shopee-actions-row">
                                <button type="submit" class="shopee-btn-add-cart">
                                    🛒 Add To Cart
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="role-restriction-box" style="margin-top: 1.5rem;">
                            <span class="role-restriction-icon">🔒</span>
                            <div>
                                <div class="role-restriction-title">Role Restriction Active</div>
                                <div class="role-restriction-text">
                                    Signed in as <strong>{{ ucfirst(auth()->user()->role) }}</strong>. Only Buyer accounts can add items to cart.
                                </div>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="role-restriction-box" style="margin-top: 1.5rem;">
                        <span class="role-restriction-icon">💡</span>
                        <div>
                            <div class="role-restriction-title">Buyer Sign-in Required</div>
                            <div class="role-restriction-text">
                                Please <a href="{{ route('login') }}" style="color: var(--ez-primary); font-weight: 700;">login</a> or <a href="{{ route('register') }}" style="color: var(--ez-primary); font-weight: 700;">sign up</a> to purchase this item.
                            </div>
                        </div>
                    </div>
                @endauth
            </div>
        </div>
    </div>

    <!-- Store / Merchant Info Banner (Positioned Below) -->
    <div class="shopee-store-card">
        <div class="shopee-store-avatar">🏪</div>
        <div class="shopee-store-details">
            <h3 class="shopee-store-name">{{ $product->seller->business_name ?? 'EZiCart Verified Merchant' }}</h3>
            <p class="shopee-store-location">📍 {{ $product->seller->municipality ?? 'Majayjay' }}, {{ $product->seller->province ?? 'Laguna' }}</p>
        </div>
        <div class="shopee-store-actions">
            <a href="#" class="ez-btn ez-btn-outline" style="font-size: 0.8rem; padding: 0.5rem 1rem;">View Shop</a>
        </div>
    </div>

    <!-- Product Description Card (Preserving Shift+Enter Formatting) -->
    <div class="shopee-section-card">
        <h3 class="shopee-card-heading">Product Specifications & Description</h3>
        <div class="shopee-description-content">
            {{ $product->description ?? "No detailed description provided for this item." }}
        </div>
    </div>

    <!-- Customer Reviews & Ratings -->
    <div class="shopee-section-card">
        <h3 class="shopee-card-heading">Product Ratings & Reviews</h3>

        <div class="rating-overview-card">
            <div>
                <div class="rating-score-num">{{ $product->average_rating > 0 ? number_format($product->average_rating, 1) : '5.0' }}</div>
                <div class="star-rating-stars">
                    @for($i = 1; $i <= 5; $i++)
                        {{ $i <= round($product->average_rating ?: 5) ? '★' : '☆' }}
                    @endfor
                </div>
            </div>
            <div>
                <strong style="font-size: 0.9375rem; color: var(--ez-dark);">Based on {{ $product->review_count ?? 0 }} verified reviews</strong>
                <p style="margin: 0; font-size: 0.8125rem; color: var(--ez-text-gray);">Ratings submitted by verified buyers upon completed delivery.</p>
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
                    {!! nl2br(e($review->comment ?? 'Customer provided a rating without written commentary.')) !!}
                </div>
            </div>
        @empty
            <div style="text-align: center; padding: 2.5rem; color: var(--ez-text-gray); font-size: 0.875rem;">
                No reviews yet for this product. Be the first to purchase and review!
            </div>
        @endforelse
    </div>
</div>

<!-- Lightbox Image Modal -->
@if($product->image_path)
    <div id="imageModal" class="image-modal-backdrop" onclick="closeImageModal()">
        <span class="image-modal-close">&times;</span>
        <img class="image-modal-content" id="modalImage" src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}">
    </div>
@endif

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

                priceDisplay.textContent = newPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                stockDisplay.textContent = varStock + ' variation pieces available';
                qtyInput.max = varStock > 0 ? varStock : 1;
            } else {
                priceDisplay.textContent = basePrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                stockDisplay.textContent = '{{ $product->stock }} pieces available';
                qtyInput.max = {{ $product->stock }};
            }
        });
    }

    function adjustQty(amount) {
        if (!qtyInput) return;
        let current = parseInt(qtyInput.value) || 1;
        let maxStock = parseInt(qtyInput.max) || 999;
        let nextVal = current + amount;
        if (nextVal >= 1 && nextVal <= maxStock) {
            qtyInput.value = nextVal;
        }
    }

    function openImageModal() {
        const modal = document.getElementById('imageModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeImageModal() {
        const modal = document.getElementById('imageModal');
        if (modal) modal.style.display = 'none';
    }
</script>
@endsection