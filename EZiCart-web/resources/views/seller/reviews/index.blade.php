@extends('layouts.app')
@section('title', 'Customer reviews | EZiCart')
@section('content')
<main class="feature-page"><header><div><h1>Customer reviews</h1><p>Reviews of purchases from your shop, including retired products. Buyer names are masked; only buyers can edit their feedback.</p></div></header>
    @include('components.feature-feedback')
    <form class="feature-panel feature-form" method="GET">
        <label>Rating<select name="rating"><option value="">All ratings</option>@for($i=5; $i>=1; $i--)<option value="{{ $i }}" @selected((int)($filters['rating'] ?? 0) === $i)>{{ $i }} stars</option>@endfor</select></label>
        <label>Feedback text<select name="text"><option value="">All</option><option value="with" @selected(($filters['text'] ?? '') === 'with')>With comments</option><option value="without" @selected(($filters['text'] ?? '') === 'without')>Rating only</option></select></label>
        <label>Photos<select name="media"><option value="">All</option><option value="with" @selected(($filters['media'] ?? '') === 'with')>With photos</option><option value="without" @selected(($filters['media'] ?? '') === 'without')>Without photos</option></select></label>
        <label>Posted from<input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}"></label><label>Posted to<input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}"></label>
        <button class="feature-button" type="submit">Filter reviews</button><a class="feature-button" href="{{ route('seller.reviews.index') }}">Reset</a>
    </form>
    <section class="feature-panel"><h2>{{ $reviews->total() }} matching reviews</h2><p>25 per page. Date filtering uses the original posting date; edits show their updated time.</p>
        <div class="feature-table-scroll"><table class="feature-table"><thead><tr><th>Purchased product / options</th><th>Buyer</th><th>Rating</th><th>Feedback / photos</th><th>Posted / edited</th></tr></thead><tbody>
            @forelse($reviews as $review)<tr><td>@php($reviewItem = $review->order->items->firstWhere('product_id', $review->product_id))<x-product-table-item :name="$review->purchase_context['product_name'] ?? $reviewItem?->product_name ?? $review->product?->name ?? 'Historical product'" :image="$reviewItem?->purchased_image_path" missing-label="Purchase image not recorded" /><br>{{ implode(' · ', $review->purchased_variants) }}</td><td>{{ $review->public_buyer_name }}</td><td>{{ $review->rating }}/5</td><td>{{ $review->comment ?: 'Rating only' }}<x-review-photos :review="$review" :photos="$review->photos" />
<details><summary>{{ $review->reply ? 'Edit seller response' : 'Reply as seller' }}</summary>
    @if($review->reply)<p><strong>Seller response:</strong> {{ $review->reply->body }}</p>@endif
    <form class="feature-form" method="POST" action="{{ $review->reply ? route('seller.reviews.reply.update', $review) : route('seller.reviews.reply', $review) }}">@csrf
        @if($review->reply)@method('PATCH')<input type="hidden" name="version" value="{{ $review->reply->version }}">@endif
        <label>Seller response (up to 1,000 characters)<textarea name="body" maxlength="1000" required>{{ $review->reply?->body }}</textarea></label>
        <button class="feature-button" type="submit">{{ $review->reply ? 'Save response' : 'Post response' }}</button>
    </form>
</details></td><td>{{ $review->created_at->format('M d, Y h:i A') }}@if($review->revision_version > 1)<br>Edited {{ $review->updated_at->format('M d, Y h:i A') }}@endif</td></tr>@empty<tr><td colspan="5">No reviews match these filters.</td></tr>@endforelse
        </tbody></table></div>{{ $reviews->links() }}
    </section>
</main>
@endsection
