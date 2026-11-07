@extends('layouts.app')
@section('title', 'Checkout | EZiCart')
@push('styles')
    @vite('resources/css/buyer/checkout.css')
    <link rel="stylesheet" href="{{ asset('css/buyer-addresses.css') }}?v={{ filemtime(public_path('css/buyer-addresses.css')) }}">
@endpush
@section('content')
<main class="checkout-container checkout-page">
    <header class="checkout-heading"><div><h1>{{ $checkoutMode === 'buy_now' ? 'Buy Now checkout' : 'Checkout' }}</h1><p>{{ $checkoutMode === 'buy_now' ? 'Just this product and its selected variation. Your saved cart stays as it is.' : 'Review your selected items, delivery address and final total.' }}</p></div><form method="POST" action="{{ route('checkout.cancel') }}">@csrf<button class="checkout-text-button" type="submit">{{ $checkoutMode === 'buy_now' ? 'Cancel Buy Now' : 'Back to cart' }}</button></form></header>
    @include('components.feature-feedback')
    @if(session('error'))<p class="checkout-feedback" role="alert">{{ session('error') }}</p>@endif
    <section class="checkout-address-card" aria-labelledby="delivery-title">
        <header><h2 id="delivery-title"><x-shop-icon name="location" /> Delivery address</h2>@if($selectedAddress?->is_default)<span class="checkout-default-tag">Default address</span>@endif</header>
        @if($selectedAddress)<x-buyer-address-summary :address="$selectedAddress" />@else<p>Enter your delivery details below.</p>@endif
        <details @if(!$selectedAddress) open @endif><summary>{{ $selectedAddress ? 'Change address' : 'Choose a saved address' }}</summary><form method="GET" action="{{ route('checkout.index') }}" class="checkout-address-picker"><label for="checkout-saved-address">Saved addresses<select id="checkout-saved-address" name="address_id">@foreach($addresses as $savedAddress)<option value="{{ $savedAddress->id }}" @selected($selectedAddress?->id === $savedAddress->id)>{{ $savedAddress->label }} - {{ $savedAddress->municipality }}{{ $savedAddress->is_default ? ' (default)' : '' }}</option>@endforeach<option value="new" @selected($selectedAddress === null)>Enter a different address</option></select></label><button class="checkout-secondary" type="submit">Use this address</button><a href="{{ route('buyer.addresses.index') }}">Manage addresses</a></form></details>
    </section>

    <form id="checkout-voucher-form" method="POST" action="{{ route('checkout.voucher.apply') }}">@csrf</form>
    <form id="checkout-remove-voucher" method="POST" action="{{ route('checkout.voucher.remove') }}">@csrf @method('DELETE')</form>
    <form id="place-order" method="POST" action="{{ route('checkout.process') }}" class="checkout-order-form" data-decision="checkout.process" data-decision-title="Place this order?" data-decision-message="Confirm your delivery address, selected products and cash-on-delivery total before placing your order." data-decision-label="Place order" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
        @csrf
        <input type="hidden" name="checkout_token" value="{{ $token }}"><input type="hidden" name="quote_hash" value="{{ $quote['hash'] }}"><input type="hidden" name="saved_address_id" value="{{ $selectedAddress?->id }}">
        @unless($selectedAddress)
            <section class="checkout-section"><h2>Delivery details</h2><div class="checkout-form-grid">
                <label for="checkout-recipient-name">Recipient name<input id="checkout-recipient-name" name="recipient_name" maxlength="255" value="{{ old('recipient_name', $user->first_name.' '.$user->last_name) }}" required></label>
                <label for="checkout-recipient-contact">Contact number<input id="checkout-recipient-contact" type="tel" name="recipient_contact" maxlength="13" pattern="(09[0-9]{9}|\+639[0-9]{9})" value="{{ old('recipient_contact', $user->contact_no) }}" required></label>
                <div class="checkout-form-full">@include('components.psgc-address-fields', ['prefix' => 'checkout-address', 'address' => $user, 'postalRequired' => false])</div>
            </div></section>
        @endunless

        <section class="checkout-section checkout-products" aria-labelledby="ordered-title">
            <header class="checkout-product-columns"><h2 id="ordered-title">Products ordered</h2><span>Unit price</span><span>Quantity</span><span>Item subtotal</span></header>
            @foreach($quote['groups'] as $sellerId => $group)
                @php($shopLine = reset($group['lines']))
                <section class="checkout-shop" aria-label="{{ $shopLine['seller_name'] }} shipment">
                    <header class="checkout-shop-heading"><x-shop-icon name="package1" /><a href="{{ route('shops.show', $sellerId) }}">{{ $shopLine['seller_name'] }}</a><span>Shop shipment</span></header>
                    @foreach($group['lines'] as $item)
                        <article class="checkout-product-line"><div>@if($item['image_path'])<img src="{{ asset('storage/'.$item['image_path']) }}" alt="">@else<span class="checkout-image-placeholder"><x-shop-icon name="package1" /></span>@endif<div><a href="{{ route('product.show', $item['product_id']) }}">{{ $item['name'] }}</a>@if($item['variation_info'])<p>{{ $item['variation_info'] }}</p>@endif</div></div><span data-label="Unit price">&#8369;{{ number_format($item['price'], 2) }}</span><span data-label="Quantity">{{ $item['quantity'] }}</span><strong data-label="Item subtotal">&#8369;{{ number_format($item['item_cents'] / 100, 2) }}</strong></article>
                    @endforeach
                    <div class="checkout-shipping-row"><div><x-shop-icon name="delivery1" /><strong>Standard delivery</strong><span>Shipping for this shop</span></div><strong>&#8369;{{ number_format($group['shipping_cents'] / 100, 2) }}</strong></div>
                    @if($group['discount_cents'] > 0)<div class="checkout-shop-discount"><span>Voucher savings on this shipment</span><strong>&minus;&#8369;{{ number_format($group['discount_cents'] / 100, 2) }}</strong></div>@endif
                    <footer class="checkout-shop-total">Shop order total <strong>&#8369;{{ number_format($group['total_cents'] / 100, 2) }}</strong></footer>
                </section>
            @endforeach
        </section>

        <section class="checkout-section checkout-voucher"><div><h2><x-shop-icon name="invoice" /> Voucher</h2><p>Applied to eligible selected items only.</p></div><div class="checkout-voucher-controls"><label class="checkout-sr-only" for="voucher-code">Voucher code</label><input id="voucher-code" name="voucher_code" form="checkout-voucher-form" maxlength="50" placeholder="Enter voucher code" required value="{{ old('voucher_code', $appliedVoucher['code'] ?? '') }}"><button class="checkout-secondary" type="submit" form="checkout-voucher-form">Apply voucher</button>@if($appliedVoucher)<button class="checkout-text-button" form="checkout-remove-voucher" type="submit">Remove {{ $appliedVoucher['code'] }}</button>@endif</div></section>

        <section class="checkout-section checkout-notes"><label for="checkout-notes"><strong>Message for your seller</strong><span>Optional delivery landmarks or instructions for this shop's order.</span></label><textarea id="checkout-notes" name="notes" rows="2" maxlength="200" placeholder="Leave a message...">{{ old('notes') }}</textarea></section>
        <section class="checkout-section checkout-payment"><div><h2>Payment method</h2><p>Pay your courier when your parcel arrives.</p></div><label><input type="radio" name="payment_method" value="COD" checked> Cash on delivery</label></section>
        <section class="checkout-section checkout-final" aria-labelledby="summary-title"><div class="checkout-shipment-preview"><h2 id="summary-title">Your shipment preview</h2><p>{{ count($cart) }} selected {{ Str::plural('product', count($cart)) }} from {{ count($quote['groups']) }} {{ Str::plural('shop', count($quote['groups'])) }}.</p><p>{{ $checkoutMode === 'buy_now' ? 'This purchase is separate from your saved cart.' : 'Unchecked products stay saved in your cart.' }}</p></div><dl class="checkout-totals"><div><dt>Merchandise subtotal</dt><dd>&#8369;{{ number_format($subtotal, 2) }}</dd></div><div><dt>Shipping subtotal</dt><dd>&#8369;{{ number_format($shippingFee, 2) }}</dd></div>@if($discount > 0)<div><dt>Voucher discount</dt><dd>&minus;&#8369;{{ number_format($discount, 2) }}</dd></div>@endif<div class="checkout-grand-total"><dt>Total payment</dt><dd>&#8369;{{ number_format($total, 2) }}</dd></div></dl><footer class="checkout-place-row"><span>Review your items and address before confirming.</span><button class="checkout-primary" type="submit">Place order <span>&#8369;{{ number_format($total, 2) }}</span></button></footer></section>
    </form>
</main>
@endsection
