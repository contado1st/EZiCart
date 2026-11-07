@props(['product', 'compact' => false])
@php
    $slides = collect();
    if ($product->image_path) $slides->push(['kind' => 'image', 'path' => $product->image_path, 'mime_type' => 'image/jpeg']);
    foreach ($product->media as $item) $slides->push($item->only(['kind', 'path', 'mime_type']));
    $galleryId = 'product-media-'.$product->id.'-'.Illuminate\Support\Str::random(6);
@endphp
@pushOnce('styles', 'product-media-assets')
    <link rel="stylesheet" href="{{ asset('css/product-media.css') }}?v={{ filemtime(public_path('css/product-media.css')) }}">
    <script src="{{ asset('js/product-media.js') }}?v={{ filemtime(public_path('js/product-media.js')) }}" defer></script>
@endPushOnce
<section {{ $attributes->class(['product-media-gallery', 'product-media-gallery--compact' => $compact]) }} data-product-gallery role="region" aria-roledescription="carousel" aria-label="{{ $product->name }} media">
    @if($slides->isNotEmpty())
        <div class="product-media-stage" id="{{ $galleryId }}" data-gallery-track>
            @foreach($slides as $slide)
                <div class="product-media-slide" data-gallery-slide data-kind="{{ $slide['kind'] }}" role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $slides->count() }}, {{ $slide['kind'] }}">
                    @if($slide['kind'] === 'video')
                        <video controls playsinline preload="none" aria-label="{{ $product->name }} product video"><source src="{{ asset('storage/'.$slide['path']) }}" type="{{ $slide['mime_type'] }}">Your browser cannot play this video. <a href="{{ asset('storage/'.$slide['path']) }}">Open the product video</a>.</video>
                    @else
                        <img src="{{ asset('storage/'.$slide['path']) }}" alt="{{ $product->name }} — photo {{ $loop->iteration }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}" draggable="false">
                    @endif
                </div>
            @endforeach
        </div>
        <div class="product-media-navigation" data-gallery-controls hidden>
            <button type="button" data-gallery-previous aria-label="Previous product media" aria-controls="{{ $galleryId }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 6-6 6 6 6" /></svg></button>
            <span data-gallery-status role="status" aria-live="polite" aria-atomic="true">1 / {{ $slides->count() }}</span>
            <button type="button" data-gallery-next aria-label="Next product media" aria-controls="{{ $galleryId }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg></button>
        </div>
        <div class="product-media-thumbnails" data-gallery-thumbnails role="group" aria-label="Choose product media" hidden>
            @foreach($slides as $slide)
                <button type="button" data-gallery-thumbnail="{{ $loop->index }}" aria-label="Show {{ $slide['kind'] }} {{ $loop->iteration }}" aria-controls="{{ $galleryId }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                    @if($slide['kind'] === 'video')<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 11 7-11 7z" /></svg><span>Video</span>@else<img src="{{ asset('storage/'.$slide['path']) }}" alt="" loading="lazy">@endif
                </button>
            @endforeach
        </div>
    @else
        <div class="product-media-empty"><x-shop-icon name="package1" /><span>No product media yet</span></div>
    @endif
</section>
