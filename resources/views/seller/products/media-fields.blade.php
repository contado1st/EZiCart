@php($editingProduct = $product ?? null)
@pushOnce('styles', 'product-media-assets')
    <link rel="stylesheet" href="{{ asset('css/product-media.css') }}?v={{ filemtime(public_path('css/product-media.css')) }}">
    <script src="{{ asset('js/product-media.js') }}?v={{ filemtime(public_path('js/product-media.js')) }}" defer></script>
@endPushOnce
<fieldset class="product-media-editor" data-product-media-editor data-image-limit="{{ config('product-media.gallery_image_limit') }}" data-image-max="{{ config('product-media.image_max_kb') * 1024 }}" data-video-max="{{ config('product-media.video_max_kb') * 1024 }}" data-server-max="{{ ini_get('upload_max_filesize') }}" data-post-max="{{ ini_get('post_max_size') }}" data-existing-images="{{ $editingProduct?->media->where('kind', 'image')->count() ?? 0 }}" data-has-cover="{{ $editingProduct?->image_path ? '1' : '0' }}">
    <legend>Product photos &amp; video</legend>
    <p>Add close-ups, different angles, and a product video. The cover stays first in the carousel and appears on product cards.</p>
    <div class="product-media-upload-grid">
        <div><label for="gallery-images">Gallery images</label><input id="gallery-images" name="gallery_images[]" type="file" multiple accept="image/jpeg,image/png,image/webp" data-media-upload="images" aria-describedby="gallery-images-help"><small id="gallery-images-help">Up to {{ config('product-media.gallery_image_limit') }} gallery photos, {{ config('product-media.image_max_kb') / 1024 }} MB each. JPEG, PNG or WebP. Without a separate cover, your first selected photo becomes the cover.</small>@error('gallery_images')<p class="product-media-error">{{ $message }}</p>@enderror</div>
        <div><label for="product-video">Product video</label><input id="product-video" name="product_video" type="file" accept="video/mp4,video/webm" data-media-upload="video" aria-describedby="product-video-help"><small id="product-video-help">One MP4 or WebM video, up to {{ config('product-media.video_max_kb') / 1024 }} MB. A new video replaces the current video. Videos play when the viewer presses play.</small>@error('product_video')<p class="product-media-error">{{ $message }}</p>@enderror</div>
    </div>
    <p class="product-media-server-hint">This server currently allows {{ ini_get('upload_max_filesize') }} per file and {{ ini_get('post_max_size') }} per request.</p>
    <p class="product-media-error" data-media-message role="status" aria-live="polite"></p>
    <div class="product-media-upload-previews" data-media-previews aria-label="New media previews"></div>
    @if($editingProduct)
        @if($editingProduct->image_path)<label class="product-media-remove-cover"><input type="checkbox" name="remove_cover" value="1" @checked(old('remove_cover'))> Remove the current cover image</label>@endif
        @if($editingProduct->media->isNotEmpty())
            <h3>Saved gallery media</h3><p>Select files to remove when saving. New gallery images are added after the remaining images.</p>
            <div class="product-media-saved">
                @foreach($editingProduct->media as $item)
                    <label class="product-media-saved-item">
                        @if($item->kind === 'image')<img src="{{ asset('storage/'.$item->path) }}" alt="Gallery photo {{ $loop->iteration }}" loading="lazy">@else<span class="product-media-video-label">Product video</span>@endif
                        <span><input type="checkbox" name="remove_media[]" value="{{ $item->id }}" data-media-remove="{{ $item->kind }}" @checked(in_array($item->id, (array) old('remove_media', [])))> Remove {{ $item->kind }}</span>
                    </label>
                @endforeach
            </div>
        @endif
        <details class="product-media-current-preview"><summary>Preview saved product media</summary><x-product-gallery :product="$editingProduct" compact /></details>
    @endif
    <p class="product-media-review-hint">Adding, replacing, or removing media sends a reviewed listing back to product compliance.</p>
</fieldset>
