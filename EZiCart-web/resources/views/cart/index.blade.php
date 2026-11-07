@extends('layouts.app')
@section('title', 'Shopping cart | EZiCart')
@push('styles')
    @vite('resources/css/buyer/cart.css')
@endpush
@section('content')
<main class="cart-container cart-page" data-cart-selection data-buyer="{{ auth()->id() }}">
    <header class="cart-heading"><div><h1>Shopping cart</h1><p>Buy several products from one shop per checkout. Everything else stays saved for later.</p></div><a href="{{ route('home') }}">Continue shopping</a></header>
    @include('components.feature-feedback')
    @if(session('error'))<p class="cart-feedback" role="alert">{{ session('error') }}</p>@endif
    @if(empty($cart))
        <section class="cart-empty"><x-shop-icon name="cart2" /><h2>Your next find is waiting</h2><p>Your saved cart is empty. Explore products from EZiCart shops.</p><a class="cart-primary" href="{{ route('home') }}">Explore marketplace</a></section>
    @else
        @php
            $available = array_filter($cart, fn ($item) => $item['available']);
            $firstShop = $available ? reset($available)['seller_id'] : null;
            $selectedKeys = session('checkout_context.mode') === 'cart' ? array_keys(session('checkout_context.items', [])) : array_keys(array_filter($available, fn ($item) => $item['seller_id'] === $firstShop));
            $selected = array_filter($cart, fn ($key) => in_array($key, $selectedKeys), ARRAY_FILTER_USE_KEY);
            $selected = array_filter($selected, fn ($item) => $item['available']);
            $selectedShop = $selected ? reset($selected)['seller_id'] : null;
            $selected = array_filter($selected, fn ($item) => $item['seller_id'] === $selectedShop);
            $selectedCents = array_sum(array_map(fn ($item) => $item['unit_cents'] * $item['quantity'], $selected));
        @endphp
        <form id="cart-checkout-selection" method="POST" action="{{ route('cart.checkout') }}">@csrf</form>
        <div class="cart-column-head"><strong>Product</strong><span>Unit price</span><span>Quantity</span><span>Item total</span><span>Actions</span></div>
        <div class="cart-shop-list">
        @foreach($groups as $sellerId => $items)
            <section class="cart-shop" data-shop="{{ $sellerId }}" aria-label="{{ $items->first()['seller_name'] }} products">
                <header class="cart-shop-heading"><label><input type="checkbox" data-select-shop aria-label="Select available products from {{ $items->first()['seller_name'] }}"><x-shop-icon name="package1" /><strong>{{ $items->first()['seller_name'] }}</strong></label>@if($sellerId)<a href="{{ route('shops.show', $sellerId) }}">Visit shop</a>@endif</header>
                @foreach($items as $key => $item)
                    <article @class(['cart-line', 'cart-line-unavailable' => !$item['available']])>
                        <div class="cart-product">
                            <input type="checkbox" name="selected_keys[]" value="{{ $key }}" form="cart-checkout-selection" data-cart-item data-shop-id="{{ $sellerId }}" data-cents="{{ $item['unit_cents'] }}" data-quantity="{{ $item['quantity'] }}" @checked(isset($selected[$key])) @disabled(!$item['available']) aria-label="Buy {{ $item['name'] }}">
                            @if($item['image_path'])<img src="{{ asset('storage/'.$item['image_path']) }}" alt="" loading="lazy">@else<span class="cart-product-placeholder"><x-shop-icon name="package1" /></span>@endif
                            <div><a class="cart-product-title" href="{{ route('product.show', $item['product_id']) }}">{{ $item['name'] }}</a>@if($item['variation_info'])<p>{{ $item['variation_info'] }}</p>@endif<p class="cart-unit-mobile">&#8369;{{ number_format($item['price'], 2) }} each</p>@if($item['unavailable_reason'])<p class="cart-line-warning">{{ $item['unavailable_reason'] }}</p>@endif</div>
                        </div>
                        <span class="cart-unit-price">&#8369;{{ number_format($item['price'], 2) }}</span>
                        <form class="cart-quantity" method="POST" action="{{ route('cart.update', $key) }}">@csrf @method('PATCH')<div><button type="button" data-qty-step="-1" aria-label="Decrease {{ $item['name'] }} quantity">&minus;</button><label class="cart-sr-only" for="quantity-{{ $key }}">Quantity for {{ $item['name'] }}</label><input id="quantity-{{ $key }}" type="number" name="quantity" value="{{ $item['quantity'] }}" min="1" max="20" required><button type="button" data-qty-step="1" aria-label="Increase {{ $item['name'] }} quantity">+</button></div><button class="cart-text-button" type="submit">Update</button></form>
                        <strong class="cart-line-total">&#8369;{{ number_format($item['price'] * $item['quantity'], 2) }}</strong>
                        <div class="cart-line-actions"><form method="POST" action="{{ route('cart.remove', $key) }}" data-decision="cart.remove" data-decision-title="Remove this item?" data-decision-message="This removes the item from your saved cart." data-decision-label="Remove item" data-decision-tone="danger">@csrf @method('DELETE')<button class="cart-text-button" type="submit">Remove</button></form><a href="{{ route('search.index', ['search' => $item['name']]) }}">Find similar</a></div>
                    </article>
                @endforeach
                <footer class="cart-shop-footer"><x-shop-icon name="delivery1" /><span>Shipping and eligible vouchers are calculated for your selected items at checkout.</span></footer>
            </section>
        @endforeach
        </div>
        <footer class="cart-selection-bar">
            <strong>One shop per checkout</strong>
            <p class="cart-saved-note"><x-shop-icon name="shield" /> Saved to your account, even after logout</p>
            <div class="cart-selection-total" aria-live="polite"><span><strong data-selected-count>{{ count($selected) }}</strong> products selected</span><strong data-selected-total>&#8369;{{ number_format($selectedCents / 100, 2) }}</strong><small>Before shipping and vouchers</small></div>
            <button class="cart-primary" type="submit" form="cart-checkout-selection">Check out <span data-selected-button-count>({{ count($selected) }})</span></button>
        </footer>
        <p class="cart-selection-hint" data-selection-hint>Select multiple products within one shop. Selecting another shop switches your selection. Other products stay saved in your cart.</p>
    @endif
</main>
<script src="{{ asset('js/cart-selection.js') }}?v={{ filemtime(public_path('js/cart-selection.js')) }}" defer></script>
@endsection
