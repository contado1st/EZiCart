@props(['order', 'productId', 'review' => null])
@pushOnce('styles', 'review-features')
    <link rel="stylesheet" href="{{ asset('css/review-features.css') }}?v={{ filemtime(public_path('css/review-features.css')) }}">
@endPushOnce
@php($useOld = !$errors->has('version') && ($review || ((int)old('product_id') === (int)$productId && (int)old('review_order_id') === $order->id)))
<form class="feature-form review-editor" method="POST" action="{{ $review ? route('buyer.reviews.update', $review) : route('buyer.orders.review', $order) }}" enctype="multipart/form-data" data-decision="publish_review" data-decision-title="{{ $review ? 'Publish review changes?' : 'Publish your review?' }}" data-decision-message="Your rating, feedback and selected photos appear publicly with a masked name. Changes retain a private revision history." data-decision-label="{{ $review ? 'Save changes' : 'Publish review' }}">
    @csrf
    @if($review) @method('PATCH')<input type="hidden" name="version" value="{{ $useOld ? old('version', $review->revision_version) : $review->revision_version }}">
    @else<input type="hidden" name="product_id" value="{{ $productId }}"><input type="hidden" name="review_order_id" value="{{ $order->id }}">@endif
    <label>Rating<select name="rating" required>@for($rating=5; $rating>=1; $rating--)<option value="{{ $rating }}" @selected((int)($useOld ? old('rating', $review?->rating ?? 5) : ($review?->rating ?? 5)) === $rating)>{{ $rating }} {{ Str::plural('star', $rating) }}</option>@endfor</select></label>
    <label>Feedback (optional)<textarea name="comment" maxlength="1000" rows="3" placeholder="Write feedback (optional)...">{{ $useOld ? old('comment', $review?->comment) : $review?->comment }}</textarea></label>
    @if($review && $review->photos->isNotEmpty())<fieldset><legend>Current photos · select any to remove</legend><x-review-photos :review="$review" :photos="$review->photos" />@foreach($review->photos as $photo)<label><input type="checkbox" name="remove_photos[]" value="{{ $photo->id }}">Remove photo {{ $loop->iteration }}</label>@endforeach</fieldset>@endif
    <label>Add photos (up to five in total; 5 MB each)<input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp"></label>
    <p class="review-editor-note">JPG, PNG or WebP, up to 4096 × 4096 pixels. Photos are public with your review. Removed photos remain in your private revision history. Hosting upload limits may be lower.</p>
    <button class="feature-button" type="submit">{{ $review ? 'Save review changes' : 'Publish review' }}</button>
</form>
