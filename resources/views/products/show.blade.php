@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
    <link rel="stylesheet" href="{{ asset('css/reviews.css') }}">
    <style>
        /* Carousel & Gallery Styles */
        .carousel-wrapper {
            position: relative;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--slate-200);
        }
        .carousel-main-box {
            position: relative;
            width: 100%;
            height: 380px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            user-select: none;
            touch-action: pan-y;
        }
        .carousel-main-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.2s ease, opacity 0.2s ease;
            cursor: zoom-in;
        }
        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--slate-300);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--slate-800);
            cursor: pointer;
            z-index: 10;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            transition: all 0.2s;
        }
        .carousel-btn:hover {
            background: var(--ez-primary);
            color: #fff;
            border-color: var(--ez-primary);
        }
        .carousel-btn.prev { left: 10px; }
        .carousel-btn.next { right: 10px; }

        .carousel-thumbs-strip {
            display: flex;
            gap: 8px;
            padding: 10px;
            overflow-x: auto;
            background: #fff;
            border-top: 1px solid var(--slate-100);
        }
        .carousel-thumb-item {
            width: 60px;
            height: 60px;
            flex-shrink: 0;
            border-radius: 6px;
            border: 2px solid transparent;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s;
            background: #f1f5f9;
        }
        .carousel-thumb-item.active {
            border-color: var(--ez-primary);
            box-shadow: 0 0 0 2px rgba(230, 46, 99, 0.2);
        }
        .carousel-thumb-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Variation Chips */
        .var-chip-btn {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 8px;
            border: 1.5px solid var(--slate-300);
            background: #fff;
            color: var(--slate-800);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            user-select: none;
        }
        .var-chip-btn:hover {
            border-color: var(--ez-primary);
            color: var(--ez-primary);
        }
        .var-chip-btn.active {
            border-color: var(--ez-primary);
            background: #fff0f4;
            color: var(--ez-primary);
            box-shadow: 0 0 0 2px rgba(230, 46, 99, 0.2);
        }
        .var-chip-btn.out-of-stock {
            opacity: 0.5;
            text-decoration: line-through;
            cursor: not-allowed;
        }
    </style>
@endpush

@section('content')
<div class="ez-container-fluid product-show-container">
    
    <!-- Breadcrumb -->
    <div class="product-breadcrumb">
        <a href="{{ route('home') }}" class="product-back-link">Home</a>
        <span class="breadcrumb-separator">›</span>
        <span>{{ $product->category }}</span>
        <span class="breadcrumb-separator">›</span>
        <span class="breadcrumb-current">{{ $product->name }}</span>
    </div>

    <!-- Main Product Card -->
    <div class="shopee-product-card">
        <div class="shopee-product-main">
            
            <!-- Left Column: Carousel Image Gallery -->
            <div class="shopee-gallery-col">
                <div class="carousel-wrapper">
                    @php
                        // Collect all gallery images or fallback to single image
                        $galleryImages = $product->images->count() > 0 
                            ? $product->images->pluck('image_path')->toArray() 
                            : ($product->image_path ? [$product->image_path] : []);
                    @endphp

                    <div class="carousel-main-box" id="carouselMainBox" onclick="openImageModal()">
                        @if(count($galleryImages) > 0)
                            <img id="mainProductImage" 
                                 src="{{ asset('storage/' . $galleryImages[0]) }}" 
                                 alt="{{ $product->name }}" 
                                 class="carousel-main-img">
                            
                            @if(count($galleryImages) > 1)
                                <button type="button" class="carousel-btn prev" onclick="event.stopPropagation(); navigateCarousel(-1);" aria-label="Previous image">‹</button>
                                <button type="button" class="carousel-btn next" onclick="event.stopPropagation(); navigateCarousel(1);" aria-label="Next image">›</button>
                            @endif
                            <div class="image-zoom-hint" style="position: absolute; bottom: 8px; right: 8px; font-size: 0.7rem; background: rgba(0,0,0,0.5); color: #fff; padding: 2px 6px; border-radius: 4px;">🔍 Click to enlarge</div>
                        @else
                            <div class="shopee-image-placeholder">📦</div>
                        @endif
                    </div>

                    <!-- Thumbnails row -->
                    @if(count($galleryImages) > 1)
                        <div class="carousel-thumbs-strip" id="carouselThumbnails">
                            @foreach($galleryImages as $index => $imgPath)
                                <div class="carousel-thumb-item {{ $index === 0 ? 'active' : '' }}" 
                                     id="thumb_{{ $index }}" 
                                     onclick="selectCarouselIndex({{ $index }})">
                                    <img src="{{ asset('storage/' . $imgPath) }}" alt="{{ $product->name }} thumbnail">
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right Column: Specs, Price, Discount, Voucher, Variations, Form -->
            <div class="shopee-info-col">
                <h1 class="shopee-product-title">{{ $product->name }}</h1>

                <!-- Ratings Bar -->
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

                <!-- Price Banner with Product Discount Support -->
                <div class="shopee-price-banner" style="display: flex; align-items: baseline; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: baseline;">
                        <span class="shopee-currency">₱</span>
                        <span class="shopee-price-amount" id="basePriceDisplay">{{ number_format($product->discounted_price, 2) }}</span>
                    </div>

                    @if($product->has_discount)
                        <span id="originalPriceDisplay" style="text-decoration: line-through; color: var(--slate-400); font-size: 1.1rem; margin-left: 0.5rem;">
                            ₱{{ number_format($product->price, 2) }}
                        </span>
                        <span id="discountBadgeTag" style="background: #fef2f2; color: #dc2626; font-size: 0.8rem; font-weight: 800; padding: 3px 8px; border-radius: 4px; margin-left: 0.5rem;">
                            {{ $product->discount_type === 'percent' ? $product->discount_value . '% OFF' : '₱' . number_format($product->discount_value, 2) . ' OFF' }}
                        </span>
                    @endif
                </div>

                <!-- Optional Product Promotional Voucher Banner -->
                @if($product->activeVoucher)
                    <div style="background: #fff0f4; border: 1px dashed var(--ez-primary); border-radius: 8px; padding: 0.75rem 1rem; margin: 1rem 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="font-size: 1.5rem;">🎟️</span>
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-family: monospace; font-size: 0.95rem; font-weight: 800; color: var(--ez-primary); background: white; padding: 2px 8px; border-radius: 4px; border: 1px solid rgba(230,46,99,0.3);">
                                        {{ $product->activeVoucher->code }}
                                    </span>
                                    <strong style="color: var(--slate-800); font-size: 0.85rem;">
                                        {{ $product->activeVoucher->type === 'percent' ? $product->activeVoucher->value . '% OFF' : '₱' . number_format($product->activeVoucher->value, 2) . ' OFF' }}
                                    </strong>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--slate-500); margin-top: 0.2rem;">
                                    Min. spend ₱{{ number_format($product->activeVoucher->min_spend, 2) }}
                                    @if($product->activeVoucher->max_discount) &bull; Max cap ₱{{ number_format($product->activeVoucher->max_discount, 2) }} @endif
                                    @if($product->activeVoucher->expires_at) &bull; Valid until {{ $product->activeVoucher->expires_at->format('M d, Y') }} @endif
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="copyVoucherCode('{{ $product->activeVoucher->code }}')" id="copyVoucherBtn" style="background: var(--ez-primary); color: white; border: none; padding: 0.4rem 0.85rem; border-radius: 6px; font-weight: 700; font-size: 0.75rem; cursor: pointer; transition: all 0.2s;">
                            Copy Code
                        </button>
                    </div>
                @endif

                <!-- Cart Form -->
                @auth
                    @if(auth()->user()->role === 'buyer')
                        <form action="{{ route('cart.add', $product->id) }}" method="POST" class="shopee-cart-form" id="addToCartForm">
                            @csrf

                            <!-- Interactive Product Variations (Feature 3, 4, 5) -->
                            @if($product->variations->count() > 0)
                                @php
                                    $groupedVariations = $product->variations->groupBy('type');
                                @endphp

                                @foreach($groupedVariations as $vType => $options)
                                    <div class="shopee-option-row" style="margin-bottom: 1.25rem;">
                                        <label class="shopee-row-label" style="min-width: 80px; font-weight: 700; color: var(--slate-700); font-size: 0.875rem;">
                                            {{ $vType }}
                                        </label>
                                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                            @foreach($options as $var)
                                                <button type="button" 
                                                        class="var-chip-btn {{ $var->stock <= 0 ? 'out-of-stock' : '' }}" 
                                                        id="varChip_{{ $var->id }}"
                                                        data-id="{{ $var->id }}"
                                                        data-type="{{ $var->type }}"
                                                        data-value="{{ $var->value }}"
                                                        data-orig-price="{{ $var->original_price }}"
                                                        data-discounted-price="{{ $var->discounted_price }}"
                                                        data-stock="{{ $var->stock }}"
                                                        data-image="{{ $var->image_path ? asset('storage/' . $var->image_path) : '' }}"
                                                        onclick="selectVariation({{ $var->id }})">
                                                    @if($var->image_path)
                                                        <img src="{{ asset('storage/' . $var->image_path) }}" alt="{{ $var->value }}" style="width: 22px; height: 22px; object-fit: cover; border-radius: 4px; margin-right: 6px;">
                                                    @endif
                                                    <span>{{ $var->value }}</span>
                                                    @if($product->has_discount)
                                                        <span style="font-size: 0.75rem; color: var(--ez-primary); font-weight: 700; margin-left: 4px;">
                                                            ₱{{ number_format($var->discounted_price, 2) }}
                                                        </span>
                                                    @elseif($var->original_price != $product->price)
                                                        <span style="font-size: 0.75rem; opacity: 0.75; margin-left: 4px;">
                                                            ₱{{ number_format($var->original_price, 2) }}
                                                        </span>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach

                                <!-- Hidden input for form submission -->
                                <input type="hidden" name="variation_id" id="selectedVariationId" value="" required>
                                <div id="variationValidationMessage" style="color: #dc2626; font-size: 0.8rem; margin-bottom: 0.75rem; display: none;">
                                    Please select an option before adding to cart.
                                </div>
                            @endif

                            <!-- Quantity Selector -->
                            <div class="shopee-option-row">
                                <label class="shopee-row-label" style="min-width: 80px; font-weight: 700; color: var(--slate-700);">Quantity</label>
                                <div class="shopee-qty-controls">
                                    <button type="button" class="qty-btn" onclick="adjustQty(-1)">-</button>
                                    <input type="number" name="quantity" id="qtyInput" value="1" min="1" max="{{ $product->stock }}" class="shopee-qty-input">
                                    <button type="button" class="qty-btn" onclick="adjustQty(1)">+</button>
                                    <span class="shopee-stock-label" id="stockDisplay">{{ $product->stock }} pieces available</span>
                                </div>
                            </div>

                            <div class="shopee-actions-row">
                                <button type="submit" class="shopee-btn-add-cart" id="addToCartBtn">
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

    <!-- Merchant Info Banner -->
    <div class="shopee-store-card">
        <div class="shopee-store-avatar">🏪</div>
        <div class="shopee-store-details">
            <h3 class="shopee-store-name">{{ $product->seller->business_name ?? 'EZiCart Verified Merchant' }}</h3>
            <p class="shopee-store-location">📍 {{ $product->seller->municipality ?? 'Majayjay' }}, {{ $product->seller->province ?? 'Laguna' }}</p>
        </div>
        <div class="shopee-store-actions">
            <a href="{{ route('home', ['category' => $product->category]) }}" class="ez-btn ez-btn-outline" style="font-size: 0.8rem; padding: 0.5rem 1rem;">More in {{ $product->category }}</a>
        </div>
    </div>

    <!-- Description Card -->
    <div class="shopee-section-card">
        <h3 class="shopee-card-heading">Product Specifications & Description</h3>
        <div class="shopee-description-content">
            {{ $product->description ?? "No detailed description provided for this item." }}
        </div>
    </div>

    <!-- Reviews Card -->
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

        @forelse($product->reviews as $review)
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

<!-- Lightbox Modal -->
<div id="imageModal" class="image-modal-backdrop" onclick="closeImageModal()" style="display: none;">
    <span class="image-modal-close">&times;</span>
    <img class="image-modal-content" id="modalImage" src="" alt="Enlarged product image">
</div>

<script>
    // ----------------------------------------------------
    // Product Gallery Carousel & Slider
    // ----------------------------------------------------
    const galleryList = @json(array_map(fn($p) => asset('storage/' . $p), $galleryImages));
    let currentGalleryIndex = 0;
    const mainImgElem = document.getElementById('mainProductImage');

    function updateCarouselDisplay(index) {
        if (!galleryList || galleryList.length === 0) return;
        currentGalleryIndex = (index + galleryList.length) % galleryList.length;
        if (mainImgElem) {
            mainImgElem.src = galleryList[currentGalleryIndex];
        }

        // Update active thumbnail
        const thumbs = document.querySelectorAll('.carousel-thumb-item');
        thumbs.forEach((thumb, idx) => {
            if (idx === currentGalleryIndex) {
                thumb.classList.add('active');
            } else {
                thumb.classList.remove('active');
            }
        });
    }

    function navigateCarousel(step) {
        updateCarouselDisplay(currentGalleryIndex + step);
    }

    function selectCarouselIndex(idx) {
        updateCarouselDisplay(idx);
    }

    // Touch Swipe support on Mobile
    const carouselBox = document.getElementById('carouselMainBox');
    let touchStartX = 0;
    let touchEndX = 0;

    if (carouselBox) {
        carouselBox.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        carouselBox.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipeGesture();
        }, { passive: true });
    }

    function handleSwipeGesture() {
        const threshold = 40;
        if (touchEndX < touchStartX - threshold) {
            navigateCarousel(1); // Swipe left -> Next
        } else if (touchEndX > touchStartX + threshold) {
            navigateCarousel(-1); // Swipe right -> Prev
        }
    }

    // ----------------------------------------------------
    // Pricing & Variation Selection (Feature 3, 4, 5)
    // ----------------------------------------------------
    const baseProductPrice = {{ (float) $product->price }};
    const baseDiscountedPrice = {{ (float) $product->discounted_price }};
    const hasDiscount = {{ $product->has_discount ? 'true' : 'false' }};
    const baseStock = {{ (int) $product->stock }};

    const priceDisplay = document.getElementById('basePriceDisplay');
    const originalPriceDisplay = document.getElementById('originalPriceDisplay');
    const stockDisplay = document.getElementById('stockDisplay');
    const qtyInput = document.getElementById('qtyInput');
    const selectedVarInput = document.getElementById('selectedVariationId');
    const validationMsg = document.getElementById('variationValidationMessage');

    function selectVariation(varId) {
        const chip = document.getElementById('varChip_' + varId);
        if (!chip || chip.classList.contains('out-of-stock')) return;

        // Toggle or select
        const isAlreadySelected = chip.classList.contains('active');

        // De-select siblings of same variation type
        const varType = chip.dataset.type;
        document.querySelectorAll(`.var-chip-btn[data-type="${varType}"]`).forEach(btn => {
            btn.classList.remove('active');
        });

        if (isAlreadySelected) {
            // Unselect
            if (selectedVarInput) selectedVarInput.value = '';
            priceDisplay.textContent = baseDiscountedPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (originalPriceDisplay) {
                originalPriceDisplay.textContent = '₱' + baseProductPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            stockDisplay.textContent = baseStock + ' pieces available';
            qtyInput.max = baseStock;
            return;
        }

        chip.classList.add('active');
        if (selectedVarInput) {
            selectedVarInput.value = varId;
            if (validationMsg) validationMsg.style.display = 'none';
        }

        // 1. Image Changing on Click (Feature 5)
        const varImage = chip.dataset.image;
        if (varImage && varImage.trim() !== '') {
            if (mainImgElem) {
                mainImgElem.src = varImage;
            }
            // Check if this image matches any thumbnail
            const thumbIdx = galleryList.indexOf(varImage);
            if (thumbIdx !== -1) {
                currentGalleryIndex = thumbIdx;
                updateCarouselDisplay(thumbIdx);
            }
        }

        // 2. Pricing calculation (Variation discounted price + original price)
        const finalPrice = parseFloat(chip.dataset.discountedPrice) || baseDiscountedPrice;
        const finalOrigPrice = parseFloat(chip.dataset.origPrice) || baseProductPrice;

        priceDisplay.textContent = Math.max(0, finalPrice).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (originalPriceDisplay) {
            if (hasDiscount && finalOrigPrice > finalPrice) {
                originalPriceDisplay.textContent = '₱' + Math.max(0, finalOrigPrice).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                originalPriceDisplay.style.display = 'inline';
            } else if (!hasDiscount) {
                originalPriceDisplay.style.display = 'none';
            }
        }

        // 3. Stock update
        const varStock = parseInt(chip.dataset.stock) || 0;
        stockDisplay.textContent = varStock + ' variation pieces available';
        qtyInput.max = Math.max(1, varStock);
        if (parseInt(qtyInput.value) > varStock) {
            qtyInput.value = Math.max(1, varStock);
        }
    }

    // Form submit validation for variations
    const cartForm = document.getElementById('addToCartForm');
    if (cartForm && selectedVarInput) {
        cartForm.addEventListener('submit', function(e) {
            if (!selectedVarInput.value) {
                e.preventDefault();
                if (validationMsg) validationMsg.style.display = 'block';
                return false;
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

    // ----------------------------------------------------
    // Lightbox Modal
    // ----------------------------------------------------
    function openImageModal() {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        if (modal && modalImg && mainImgElem) {
            modalImg.src = mainImgElem.src;
            modal.style.display = 'flex';
        }
    }

    function closeImageModal() {
        const modal = document.getElementById('imageModal');
        if (modal) modal.style.display = 'none';
    }

    // ----------------------------------------------------
    // Voucher Code Clipboard Copy
    // ----------------------------------------------------
    function copyVoucherCode(code) {
        navigator.clipboard.writeText(code).then(() => {
            const btn = document.getElementById('copyVoucherBtn');
            if (btn) {
                const orig = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.background = '#16a34a';
                setTimeout(() => {
                    btn.textContent = orig;
                    btn.style.background = 'var(--ez-primary)';
                }, 2000);
            }
        });
    }
</script>
@endsection