<a class="pdp-product-card" href="{{ route('product.show', $item) }}">
    <div class="pdp-product-image">@if($item->image_path)<img src="{{ asset('storage/'.$item->image_path) }}" alt="{{ $item->name }}" loading="lazy">@else<x-shop-icon name="package1" />@endif</div>
    <div class="pdp-product-copy"><h3>{{ $item->name }}</h3><strong>₱{{ number_format($item->price, 2) }}</strong><span>@if($item->reviews_count)<x-shop-icon name="star" /> {{ number_format($item->display_rating, 1) }} · {{ $item->reviews_count }} {{ Str::plural('review', $item->reviews_count) }}@else New listing @endif</span>
        @if($showShop ?? false)
            <span class="pdp-recommendation-shop">{{ $item->seller->profile_display_name ?: $item->seller->business_name ?: 'Seller shop' }}</span>
            @if($item->seller->municipality || $item->seller->province)<span class="pdp-recommendation-location"><x-shop-icon name="location" />{{ implode(', ', array_filter([$item->seller->municipality, $item->seller->province])) }}</span>@endif
        @endif
    </div>
</a>
