@props(['review', 'photos'])
@pushOnce('styles', 'review-features')
    <link rel="stylesheet" href="{{ asset('css/review-features.css') }}?v={{ filemtime(public_path('css/review-features.css')) }}">
@endPushOnce
<div class="review-photo-grid">@foreach($photos as $photo)<a href="{{ route('reviews.photos.show', [$review, $photo]) }}" target="_blank" rel="noopener" aria-label="View review photo {{ $loop->iteration }}"><img src="{{ route('reviews.photos.show', [$review, $photo]) }}" alt="Review photo {{ $loop->iteration }}" width="96" height="96" loading="lazy"></a>@endforeach</div>
