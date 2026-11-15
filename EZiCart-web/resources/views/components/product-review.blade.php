@props(['review'])
<article class="pdp-review"><x-shop-icon name="account" /><div><strong>{{ $review->public_buyer_name }}</strong>
    <div class="pdp-stars" aria-label="{{ $review->rating }} out of 5">@for($i=1; $i<=5; $i++)<x-shop-icon name="star" @class(['is-filled' => $i <= $review->rating]) />@endfor</div>
    <time datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->format('d M Y, h:i A') }}</time>@if($review->revision_version > 1)<small> · Edited {{ $review->updated_at->format('d M Y') }}</small>@endif
    <p>Purchased options: {{ $review->purchased_variants ? implode(' · ', $review->purchased_variants) : 'Not recorded' }}</p>
    <p>{{ $review->comment ?: 'The buyer left a rating without a comment.' }}</p>
    @if($review->reply)<blockquote><strong>Seller response</strong><p>{{ $review->reply->body }}</p><small>{{ $review->reply->updated_at->format('M d, Y') }}</small></blockquote>@endif
    <x-review-photos :review="$review" :photos="$review->photos" />
    @if(auth()->id() === (int)$review->buyer_id)<a href="{{ route('buyer.reviews.edit', $review) }}">Edit my review</a>@endif
</div></article>
