<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <script src="<?php echo e(asset('js/tab-sessions.js')); ?>?v=<?php echo e(filemtime(public_path('js/tab-sessions.js'))); ?>" data-tab-login-url="<?php echo e(route('login')); ?>" data-tab-authenticated="<?php echo e(auth()->check() ? '1' : '0'); ?>" defer></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $__env->yieldContent('title', 'Messages | EZiCart'); ?></title>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/app.css'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/js/app.js'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/loading-skeleton.css')); ?>?v=<?php echo e(filemtime(public_path('css/loading-skeleton.css'))); ?>">
    <script src="<?php echo e(asset('js/page-loading.js')); ?>?v=<?php echo e(filemtime(public_path('js/page-loading.js'))); ?>" defer></script>
    <?php echo $__env->yieldPushContent('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/web-operations.css')); ?>?v=<?php echo e(filemtime(public_path('css/web-operations.css'))); ?>">
</head>
<body class="<?php echo \Illuminate\Support\Arr::toCssClasses(['communications-embedded', 'communications-embedded--logistics' => in_array(auth()->user()?->role, ['courier', 'sorting_center', 'logistics_admin'], true)]); ?>">
    <div data-page-content><?php echo $__env->yieldContent('content'); ?></div>
    <?php if (isset($component)) { $__componentOriginald33f2de6723ee7b34defb10d4515258c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald33f2de6723ee7b34defb10d4515258c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-loading','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-loading'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald33f2de6723ee7b34defb10d4515258c)): ?>
<?php $attributes = $__attributesOriginald33f2de6723ee7b34defb10d4515258c; ?>
<?php unset($__attributesOriginald33f2de6723ee7b34defb10d4515258c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald33f2de6723ee7b34defb10d4515258c)): ?>
<?php $component = $__componentOriginald33f2de6723ee7b34defb10d4515258c; ?>
<?php unset($__componentOriginald33f2de6723ee7b34defb10d4515258c); ?>
<?php endif; ?>
</body>
</html>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/layouts/message-panel.blade.php ENDPATH**/ ?>