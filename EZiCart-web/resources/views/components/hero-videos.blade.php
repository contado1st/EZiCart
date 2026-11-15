@pushOnce('styles', 'hero-video-assets')
    <link rel="stylesheet" href="{{ asset('css/hero-videos.css') }}?v={{ filemtime(public_path('css/hero-videos.css')) }}">
    <script src="{{ asset('js/hero-videos.js') }}?v={{ filemtime(public_path('js/hero-videos.js')) }}" defer></script>
@endPushOnce

<div {{ $attributes->class(['hero-video-background']) }} data-hero-media aria-hidden="true">
    <img data-hero-poster src="{{ asset('videos/hero/luxury-shop.jpg') }}" alt="" width="1280" height="720" fetchpriority="high">
    @foreach (['luxury-shop', 'luxury-podium'] as $scene)
        <video data-hero-video data-src="{{ asset('videos/hero/'.$scene.'.mp4') }}"
            data-poster="{{ asset('videos/hero/'.$scene.'.jpg') }}" poster="{{ asset('videos/hero/'.$scene.'.jpg') }}"
            muted playsinline preload="none" tabindex="-1" disablepictureinpicture></video>
    @endforeach
</div>
<div class="hero-video-controls" data-hero-video-controls hidden>
    <button type="button" data-hero-video-toggle>Pause video</button>
    <button type="button" data-hero-video-next aria-label="Next hero scene">Next scene</button>
    <span class="sr-only" data-hero-video-status role="status" aria-live="polite"></span>
</div>
