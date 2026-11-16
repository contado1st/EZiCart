@extends('layouts.app')
@section('title', 'Edit my review | EZiCart')
@section('content')
<main class="feature-page">
    @php($reviewItem = $review->order->items->firstWhere('product_id', $review->product_id))
    <header><div><h1>Edit my review</h1><x-product-table-item :name="$review->purchase_context['product_name'] ?? $reviewItem?->product_name ?? 'Purchased product'" :image="$reviewItem?->purchased_image_path ?: $reviewItem?->product?->image_path">@if(!$reviewItem?->purchased_image_path && $reviewItem?->product?->image_path)<small>Current catalog photo · purchase photo not recorded</small>@endif</x-product-table-item><p>Purchased options: {{ implode(' · ', $review->purchased_variants) }}</p></div><a class="feature-button" href="{{ route('buyer.dashboard') }}">My purchases</a></header>
    @include('components.feature-feedback')
    <x-workspace-tabs :tabs="[['id' => 'review-editor', 'label' => 'Edit review'], ['id' => 'review-history', 'label' => 'Private history', 'count' => $history->total()]]" />
    <section id="review-editor" class="feature-panel"><h2>Current review · version {{ $review->revision_version }}</h2><x-review-form :order="$review->order" :product-id="$review->product_id" :review="$review" /></section>
    <section id="review-history" class="feature-panel"><h2>Private revision history</h2><p>Only your account can open this editing history. Older edits made before history recording are not reconstructed. Existing photos are retained privately after removal.</p>
        @forelse($history as $revision)<article class="review-history-entry"><h3>Version {{ $revision->version }} · {{ $revision->snapshot['rating'] }}/5</h3><time>{{ $revision->created_at->format('M d, Y h:i A') }}</time><p>{{ $revision->snapshot['comment'] ?: 'Rating without text feedback.' }}</p><x-review-photos :review="$review" :photos="$historyPhotos->only($revision->snapshot['photo_ids'] ?? [])->values()" /></article>@empty<p>History will be recorded when you save an edit.</p>@endforelse
        {{ $history->fragment('review-history')->links() }}
    </section>
</main>
@endsection
