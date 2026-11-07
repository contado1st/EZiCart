<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['product', 'compact' => false]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['product', 'compact' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $slides = collect();
    if ($product->image_path) $slides->push(['kind' => 'image', 'path' => $product->image_path, 'mime_type' => 'image/jpeg']);
    foreach ($product->media as $item) $slides->push($item->only(['kind', 'path', 'mime_type']));
    $galleryId = 'product-media-'.$product->id.'-'.Illuminate\Support\Str::random(6);
?>
<?php if (! $__env->hasRenderedOnce('product-media-assets')): $__env->markAsRenderedOnce('product-media-assets');
$__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/product-media.css')); ?>?v=<?php echo e(filemtime(public_path('css/product-media.css'))); ?>">
    <script src="<?php echo e(asset('js/product-media.js')); ?>?v=<?php echo e(filemtime(public_path('js/product-media.js'))); ?>" defer></script>
<?php $__env->stopPush(); endif; ?>
<section <?php echo e($attributes->class(['product-media-gallery', 'product-media-gallery--compact' => $compact])); ?> data-product-gallery role="region" aria-roledescription="carousel" aria-label="<?php echo e($product->name); ?> media">
    <?php if($slides->isNotEmpty()): ?>
        <div class="product-media-stage" id="<?php echo e($galleryId); ?>" data-gallery-track>
            <?php $__currentLoopData = $slides; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slide): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="product-media-slide" data-gallery-slide data-kind="<?php echo e($slide['kind']); ?>" role="group" aria-roledescription="slide" aria-label="<?php echo e($loop->iteration); ?> of <?php echo e($slides->count()); ?>, <?php echo e($slide['kind']); ?>">
                    <?php if($slide['kind'] === 'video'): ?>
                        <video controls playsinline preload="none" aria-label="<?php echo e($product->name); ?> product video"><source src="<?php echo e(asset('storage/'.$slide['path'])); ?>" type="<?php echo e($slide['mime_type']); ?>">Your browser cannot play this video. <a href="<?php echo e(asset('storage/'.$slide['path'])); ?>">Open the product video</a>.</video>
                    <?php else: ?>
                        <img src="<?php echo e(asset('storage/'.$slide['path'])); ?>" alt="<?php echo e($product->name); ?> — photo <?php echo e($loop->iteration); ?>" loading="<?php echo e($loop->first ? 'eager' : 'lazy'); ?>" draggable="false">
                    <?php endif; ?>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <div class="product-media-navigation" data-gallery-controls hidden>
            <button type="button" data-gallery-previous aria-label="Previous product media" aria-controls="<?php echo e($galleryId); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 6-6 6 6 6" /></svg></button>
            <span data-gallery-status role="status" aria-live="polite" aria-atomic="true">1 / <?php echo e($slides->count()); ?></span>
            <button type="button" data-gallery-next aria-label="Next product media" aria-controls="<?php echo e($galleryId); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg></button>
        </div>
        <div class="product-media-thumbnails" data-gallery-thumbnails role="group" aria-label="Choose product media" hidden>
            <?php $__currentLoopData = $slides; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slide): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <button type="button" data-gallery-thumbnail="<?php echo e($loop->index); ?>" aria-label="Show <?php echo e($slide['kind']); ?> <?php echo e($loop->iteration); ?>" aria-controls="<?php echo e($galleryId); ?>" aria-pressed="<?php echo e($loop->first ? 'true' : 'false'); ?>">
                    <?php if($slide['kind'] === 'video'): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 11 7-11 7z" /></svg><span>Video</span><?php else: ?><img src="<?php echo e(asset('storage/'.$slide['path'])); ?>" alt="" loading="lazy"><?php endif; ?>
                </button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php else: ?>
        <div class="product-media-empty"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'package1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'package1']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><span>No product media yet</span></div>
    <?php endif; ?>
</section>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/product-gallery.blade.php ENDPATH**/ ?>