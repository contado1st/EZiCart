<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['variant' => null]));

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

foreach (array_filter((['variant' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $loadingProfiles = config('page-loading.profiles');
    $loadingProfileFor = static function ($name) use ($loadingProfiles) {
        foreach ($loadingProfiles as $shape => $names) {
            if ($name && \Illuminate\Support\Str::is($names, $name)) return $shape;
        }
        return 'content';
    };
    $loadingRoutes = [];
    foreach (\Illuminate\Support\Facades\Route::getRoutes() as $loadingRoute) {
        if (!in_array('GET', $loadingRoute->methods(), true)) continue;
        $shape = $loadingProfileFor($loadingRoute->getName());
        if ($shape !== 'content') $loadingRoutes[] = ['path' => request()->getBaseUrl().'/'.ltrim($loadingRoute->uri(), '/'), 'shape' => $shape];
    }
    $currentShape = $variant ?? $loadingProfileFor(request()->route()?->getName());
?>
<script type="application/json" data-loading-routes><?php echo json_encode($loadingRoutes, 15, 512) ?></script>
<div class="ezi-page-loading" data-page-loading data-loading-variant="<?php echo e($currentShape); ?>" data-loading-current="<?php echo e($currentShape); ?>" data-loading-scope="<?php echo e(substr(hash('sha256', session()->getId().'|'.(auth()->id() ?? 'guest')), 0, 16)); ?>" hidden>
    <p class="ezi-loading-status ezi-loading-sr" role="status" aria-live="polite">Loading page…</p>
    <div data-loading-stage aria-hidden="true"></div>
    <?php $__currentLoopData = [...array_keys($loadingProfiles), 'content']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shape): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <template data-loading-template="<?php echo e($shape); ?>"><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => $shape]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($shape)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?></template>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/page-loading.blade.php ENDPATH**/ ?>