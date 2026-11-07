<?php ($editingProduct = $product ?? null); ?>
<?php if (! $__env->hasRenderedOnce('product-media-assets')): $__env->markAsRenderedOnce('product-media-assets');
$__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/product-media.css')); ?>?v=<?php echo e(filemtime(public_path('css/product-media.css'))); ?>">
    <script src="<?php echo e(asset('js/product-media.js')); ?>?v=<?php echo e(filemtime(public_path('js/product-media.js'))); ?>" defer></script>
<?php $__env->stopPush(); endif; ?>
<fieldset class="product-media-editor" data-product-media-editor data-image-limit="<?php echo e(config('product-media.gallery_image_limit')); ?>" data-image-max="<?php echo e(config('product-media.image_max_kb') * 1024); ?>" data-video-max="<?php echo e(config('product-media.video_max_kb') * 1024); ?>" data-server-max="<?php echo e(ini_get('upload_max_filesize')); ?>" data-post-max="<?php echo e(ini_get('post_max_size')); ?>" data-existing-images="<?php echo e($editingProduct?->media->where('kind', 'image')->count() ?? 0); ?>" data-has-cover="<?php echo e($editingProduct?->image_path ? '1' : '0'); ?>">
    <legend>Product photos &amp; video</legend>
    <p>Add close-ups, different angles, and a product video. The cover stays first in the carousel and appears on product cards.</p>
    <div class="product-media-upload-grid">
        <div><label for="gallery-images">Gallery images</label><input id="gallery-images" name="gallery_images[]" type="file" multiple accept="image/jpeg,image/png,image/webp" data-media-upload="images" aria-describedby="gallery-images-help"><small id="gallery-images-help">Up to <?php echo e(config('product-media.gallery_image_limit')); ?> gallery photos, <?php echo e(config('product-media.image_max_kb') / 1024); ?> MB each. JPEG, PNG or WebP. Without a separate cover, your first selected photo becomes the cover.</small><?php $__errorArgs = ['gallery_images'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="product-media-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
        <div><label for="product-video">Product video</label><input id="product-video" name="product_video" type="file" accept="video/mp4,video/webm" data-media-upload="video" aria-describedby="product-video-help"><small id="product-video-help">One MP4 or WebM video, up to <?php echo e(config('product-media.video_max_kb') / 1024); ?> MB. A new video replaces the current video. Videos play when the viewer presses play.</small><?php $__errorArgs = ['product_video'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="product-media-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
    </div>
    <p class="product-media-server-hint">This server currently allows <?php echo e(ini_get('upload_max_filesize')); ?> per file and <?php echo e(ini_get('post_max_size')); ?> per request.</p>
    <p class="product-media-error" data-media-message role="status" aria-live="polite"></p>
    <div class="product-media-upload-previews" data-media-previews aria-label="New media previews"></div>
    <?php if($editingProduct): ?>
        <?php if($editingProduct->image_path): ?><label class="product-media-remove-cover"><input type="checkbox" name="remove_cover" value="1" <?php if(old('remove_cover')): echo 'checked'; endif; ?>> Remove the current cover image</label><?php endif; ?>
        <?php if($editingProduct->media->isNotEmpty()): ?>
            <h3>Saved gallery media</h3><p>Select files to remove when saving. New gallery images are added after the remaining images.</p>
            <div class="product-media-saved">
                <?php $__currentLoopData = $editingProduct->media; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label class="product-media-saved-item">
                        <?php if($item->kind === 'image'): ?><img src="<?php echo e(asset('storage/'.$item->path)); ?>" alt="Gallery photo <?php echo e($loop->iteration); ?>" loading="lazy"><?php else: ?><span class="product-media-video-label">Product video</span><?php endif; ?>
                        <span><input type="checkbox" name="remove_media[]" value="<?php echo e($item->id); ?>" data-media-remove="<?php echo e($item->kind); ?>" <?php if(in_array($item->id, (array) old('remove_media', []))): echo 'checked'; endif; ?>> Remove <?php echo e($item->kind); ?></span>
                    </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
        <details class="product-media-current-preview"><summary>Preview saved product media</summary><?php if (isset($component)) { $__componentOriginal3e02afc58783df8e9f8209bbd7eced29 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3e02afc58783df8e9f8209bbd7eced29 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.product-gallery','data' => ['product' => $editingProduct,'compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('product-gallery'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['product' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($editingProduct),'compact' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3e02afc58783df8e9f8209bbd7eced29)): ?>
<?php $attributes = $__attributesOriginal3e02afc58783df8e9f8209bbd7eced29; ?>
<?php unset($__attributesOriginal3e02afc58783df8e9f8209bbd7eced29); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3e02afc58783df8e9f8209bbd7eced29)): ?>
<?php $component = $__componentOriginal3e02afc58783df8e9f8209bbd7eced29; ?>
<?php unset($__componentOriginal3e02afc58783df8e9f8209bbd7eced29); ?>
<?php endif; ?></details>
    <?php endif; ?>
    <p class="product-media-review-hint">Adding, replacing, or removing media sends a reviewed listing back to product compliance.</p>
</fieldset>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/seller/products/media-fields.blade.php ENDPATH**/ ?>