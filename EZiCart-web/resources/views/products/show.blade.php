@extends('layouts.app')
@section('title', $product->name.' | EZiCart')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/product-detail.css') }}?v={{ filemtime(public_path('css/product-detail.css')) }}">
@endpush
@section('content')
@php
    $seller = $product->seller;
    $shopName = $seller->profile_display_name ?: $seller->business_name ?: 'Seller shop';
    $publicShop = $seller->role === 'seller' && $seller->status === 'approved';
    $location = implode(', ', array_filter([$seller->municipality, $seller->province]));
@endphp
<main class="pdp">
    <nav class="pdp-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">EZiCart</a><span aria-hidden="true">/</span><a href="{{ route('search.index', ['category' => $product->category]) }}">{{ $product->category_label }}</a><span aria-hidden="true">/</span><span>{{ $product->name }}</span></nav>
    @if(session('success'))<p class="pdp-feedback" role="status">{{ session('success') }}</p>@endif
    @if(session('error') || $errors->any())<div class="pdp-feedback pdp-error" role="alert">{{ session('error') }}@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="pdp-overview" aria-labelledby="product-title">
        <div class="pdp-media">
            <x-product-gallery :product="$product" />
            <div class="pdp-media-actions"><button type="button" class="pdp-text-button" data-product-share>Share product</button><span data-share-status role="status"></span>@if(auth()->user()?->role === 'buyer' && $publicShop)<form method="POST" action="{{ route('shops.follow', $seller) }}">@csrf<input type="hidden" name="following" value="1"><button class="pdp-text-button" type="submit">Follow shop</button></form>@endif</div>
        </div>
        <div class="pdp-purchase">
            <h1 id="product-title">{{ $product->name }}</h1>
            <div class="pdp-rating-line"><a href="#product-ratings"><strong>{{ $reviewCount ? number_format($averageRating, 1) : 'New' }}</strong><span class="pdp-stars" aria-label="{{ $averageRating }} out of 5 stars">@for($i=1; $i<=5; $i++)<x-shop-icon name="star" @class(['is-filled' => $i <= round($averageRating)]) />@endfor</span></a><a href="#product-ratings">{{ number_format($reviewCount) }} Ratings</a><span>{{ $product->stock > 0 ? 'In stock' : 'Out of stock' }}</span></div>
            <div class="pdp-price-band"><strong id="basePriceDisplay">?{{ number_format($product->price, 2) }}</strong><span>Cash on delivery</span></div>
            <dl class="pdp-purchase-facts">
                @if($vouchers->isNotEmpty())<div><dt>Shop vouchers</dt><dd><a class="pdp-voucher-tag" href="#shop-vouchers">{{ $vouchers->first()->type === 'percent' ? $vouchers->first()->value.'%' : '?'.number_format($vouchers->first()->value) }} off � View vouchers</a></dd></div>@endif
                <div><dt>Shipping</dt><dd><x-shop-icon name="delivery1" /><div>Ships from {{ $location ?: 'the seller�s registered address' }}<small>Delivery availability and shipping fees are shown at checkout.</small></div></dd></div>
                <div><dt>Payment</dt><dd><x-shop-icon name="shield" /><div>Pay when your order arrives<small>Cash on delivery is the available payment method.</small></div></dd></div>
            </dl>
            <x-user-activity :user="$seller" />
            @if(auth()->user()?->role === 'buyer')
                <form action="{{ route('cart.add', $product) }}" method="POST" class="pdp-cart-form" data-product-selection data-stock="{{ $product->stock }}" data-price="{{ $product->price }}" data-shop-open="{{ $seller->acceptsWebOrders() ? '1' : '0' }}">
                    @csrf
                    @if($product->variations->isNotEmpty())
                        <div class="pdp-option-row"><label for="variationSelect">Variation</label><div>
                            <select name="variation_id" id="variationSelect" required aria-describedby="variation-hint"><option value="">Choose an option</option>@foreach($product->variations as $variation)<option value="{{ $variation->id }}" data-adjustment="{{ $variation->price_adjustment }}" data-image="{{ $variation->image_path ? asset('storage/'.$variation->image_path) : '' }}" data-stock="{{ $variation->stock }}" @disabled($variation->stock < 1)>{{ $variation->type }}: {{ $variation->value }} � {{ $variation->stock }} available</option>@endforeach</select>
                            <div class="pdp-variation-buttons" data-variation-buttons hidden>@foreach($product->variations as $variation)<button type="button" data-variation-choice="{{ $variation->id }}" aria-pressed="false" @disabled($variation->stock < 1)>@if($variation->image_path)<img src="{{ asset('storage/'.$variation->image_path) }}" alt="">@endif{{ $variation->value }}</button>@endforeach</div>
                            <small id="variation-hint">Choose a variation to see its price and available stock.</small><img data-variation-image class="pdp-variation-image" hidden alt="Selected variation">
                        </div></div>
                    @else<img data-variation-image hidden alt="Selected variation">@endif
                    <div class="pdp-option-row"><label for="qtyInput">Quantity</label><div class="pdp-quantity-line"><div class="pdp-quantity-control"><button type="button" data-quantity-step="-1" aria-label="Decrease quantity">-</button><input type="number" name="quantity" id="qtyInput" value="1" min="1" max="{{ min(20, $product->stock) }}" required><button type="button" data-quantity-step="1" aria-label="Increase quantity">+</button></div><span id="stockDisplay">{{ $product->stock }} pieces available</span></div></div>
                    <div class="pdp-buy-actions"><button type="submit" class="pdp-button pdp-button-soft" @disabled(! $seller->acceptsWebOrders())><x-shop-icon name="cart2" /> Add to cart</button><button type="submit" name="intent" value="buy_now" class="pdp-button pdp-button-primary" @disabled(! $seller->acceptsWebOrders())>Buy now</button></div><p class="pdp-checkout-note">Buy now checks out this product separately. Add to cart saves it for later.</p>
                    <p class="pdp-cart-feedback" data-cart-feedback role="status" aria-live="polite" hidden></p>
                </form>
                <div class="pdp-stock-actions"><form method="POST" action="{{ route('buyer.stock.watch', $product) }}">@csrf<button class="pdp-text-button" type="submit"><x-shop-icon name="bell" /> Watch stock availability</button></form><a href="{{ route('buyer.stock.index') }}">Manage stock alerts</a></div>
            @elseif(auth()->check())<p class="pdp-sign-in">You are signed in as {{ str_replace('_', ' ', auth()->user()->role) }}. A buyer account is required to purchase.</p>
            @else<p class="pdp-sign-in"><a href="{{ route('login') }}">Sign in</a> or <a href="{{ route('register') }}">create a buyer account</a> to choose options and purchase.</p>@endif
        </div>
    </section>
    <section class="pdp-shop" aria-label="Seller information"><div class="pdp-shop-identity"><x-profile-avatar :user="$seller" /><div><h2>{{ $shopName }}</h2><span>{{ $location ?: 'EZiCart seller' }}</span><div class="pdp-shop-actions">@if(auth()->user()?->role === 'buyer' && $publicShop)<form action="{{ route('chat.start', $seller) }}" method="POST">@csrf<button class="pdp-button pdp-button-soft" type="submit"><x-shop-icon name="chat" /> Chat now</button></form>@elseif(!auth()->check())<a class="pdp-button pdp-button-soft" href="{{ route('login') }}">Chat with seller</a>@endif @if($publicShop)<a class="pdp-button" href="{{ route('shops.show', $seller) }}"><x-shop-icon name="package1" /> View shop</a>@endif</div></div></div><dl class="pdp-shop-stats"><div><dt>Products</dt><dd>{{ number_format($shopProductCount) }}</dd></div><div><dt>Followers</dt><dd>{{ number_format($followerCount) }}</dd></div><div><dt>Joined</dt><dd>{{ $seller->created_at?->format('M Y') ?: 'Not recorded' }}</dd></div></dl></section>
    <div @class(['pdp-content-grid', 'pdp-content-grid--full' => $vouchers->isEmpty() && $sameShop->isEmpty()])><div class="pdp-details">
        <section class="pdp-panel"><h2>Product specifications</h2><dl class="pdp-specifications"><div><dt>Category</dt><dd>{{ $product->category_label }}</dd></div><div><dt>Stock</dt><dd>{{ number_format($product->stock) }} available</dd></div><div><dt>Weight per unit</dt><dd>{{ \App\Services\ParcelLoadService::kg($product->weight_grams) }}</dd></div><div><dt>Shop</dt><dd>{{ $shopName }}</dd></div><div><dt>Ships from</dt><dd>{{ $location ?: 'Not specified' }}</dd></div>@if($product->variations->isNotEmpty())<div><dt>Available options</dt><dd>{{ $product->variations->pluck('value')->join(', ') }}</dd></div>@endif</dl><h2>Product description</h2><div class="pdp-description">{{ $product->description ?: 'The seller has not added a description for this product.' }}</div></section>
        <section class="pdp-panel" id="product-ratings"><h2>Product ratings</h2><div class="pdp-review-overview"><div class="pdp-review-score"><strong>{{ number_format($averageRating, 1) }}</strong><span>out of 5</span><small>{{ $reviewCount }} {{ Str::plural('review', $reviewCount) }}</small></div><nav class="pdp-review-filters" aria-label="Filter product reviews"><a href="{{ request()->fullUrlWithQuery(['rating' => null, 'comments' => null, 'reviews_page' => null]) }}#product-ratings" @class(['is-active' => !request('rating') && !request()->boolean('comments')])>All ({{ $reviewCount }})</a>@for($i=5; $i>=1; $i--)<a href="{{ request()->fullUrlWithQuery(['rating' => $i, 'comments' => null, 'reviews_page' => null]) }}#product-ratings" @class(['is-active' => (int)request('rating') === $i])>{{ $i }} star ({{ $ratingCounts[$i] ?? 0 }})</a>@endfor<a href="{{ request()->fullUrlWithQuery(['rating' => null, 'comments' => 1, 'reviews_page' => null]) }}#product-ratings" @class(['is-active' => request()->boolean('comments')])>With comments</a></nav></div>
            @forelse($reviews as $review)<article class="pdp-review"><x-profile-avatar :user="$review->buyer" /><div><strong>{{ $review->buyer->first_name }} {{ mb_substr($review->buyer->last_name, 0, 1) }}.</strong><div class="pdp-stars" aria-label="{{ $review->rating }} out of 5">@for($i=1; $i<=5; $i++)<x-shop-icon name="star" @class(['is-filled' => $i <= $review->rating]) />@endfor</div><time datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->format('d M Y, h:i A') }}</time><p>{{ $review->comment ?: 'The buyer left a rating without a comment.' }}</p></div></article>@empty<p class="pdp-empty">{{ $reviewCount ? 'No reviews match this filter. Choose another rating or view all reviews.' : 'No reviews yet. Reviews appear after buyers receive and review their orders.' }}</p>@endforelse
            {{ $reviews->fragment('product-ratings')->links() }}
        </section>
    </div><aside class="pdp-sidebar">@if($vouchers->isNotEmpty())<section class="pdp-panel" id="shop-vouchers"><h2>Shop vouchers</h2>@foreach($vouchers as $voucher)<article class="pdp-voucher"><strong>{{ $voucher->type === 'percent' ? $voucher->value.'%' : '?'.number_format($voucher->value, 2) }} off</strong><span>Min. spend ?{{ number_format($voucher->min_spend, 2) }}</span><code>{{ $voucher->code }}</code>@if($voucher->expires_at)<small>Valid until {{ $voucher->expires_at->format('d M Y') }}</small>@endif @if(auth()->user()?->role === 'buyer')<form method="POST" action="{{ route('buyer.vouchers.claim', $voucher) }}">@csrf<button class="pdp-button pdp-button-primary" type="submit">Save voucher</button></form>@else<a href="{{ route('login') }}">Sign in to save</a>@endif</article>@endforeach</section>@endif @if($sameShop->isNotEmpty())<section class="pdp-panel"><h2>More from this shop</h2><div class="pdp-sidebar-products">@foreach($sameShop->take(3) as $item)@include('products.partials.recommendation', ['item' => $item])@endforeach</div></section>@endif</aside></div>
    @if($sameShop->isNotEmpty())<section class="pdp-recommendations"><div class="pdp-section-heading"><h2>From the same shop</h2>@if($publicShop)<a href="{{ route('shops.show', $seller) }}">View all</a>@endif</div><div class="pdp-product-grid">@foreach($sameShop as $item)@include('products.partials.recommendation', ['item' => $item])@endforeach</div></section>@endif
    <section class="pdp-recommendations pdp-other-shops" id="you-may-also-like" aria-labelledby="other-shops-title">
        <div class="pdp-section-heading"><h2 id="other-shops-title">You may also like</h2></div>
        @if($suggestions->isNotEmpty())
            <div class="pdp-product-grid">
                @foreach($suggestions as $item)
                    @include('products.partials.recommendation', ['item' => $item, 'showShop' => true])
                @endforeach
            </div>
            <nav class="pdp-recommendation-pages" aria-label="More products from other shops">
                @if($suggestions->onFirstPage() === false)<a class="pdp-button" href="{{ $suggestions->previousPageUrl() }}">Previous products</a>@endif
                @if($suggestions->hasMorePages())<a class="pdp-button" href="{{ $suggestions->nextPageUrl() }}">See more</a>@endif
            </nav>
        @else
            <p class="pdp-empty pdp-recommendation-empty">Products from other shops will appear here when approved, in-stock listings are available.</p>
        @endif
    </section>
</main>
<script src="{{ asset('js/product-selection.js') }}?v={{ filemtime(public_path('js/product-selection.js')) }}" defer></script>
<script src="{{ asset('js/product-detail.js') }}?v={{ filemtime(public_path('js/product-detail.js')) }}" defer></script>
<script src="{{ asset('js/cart-feedback.js') }}?v={{ filemtime(public_path('js/cart-feedback.js')) }}" defer></script>
@endsection
