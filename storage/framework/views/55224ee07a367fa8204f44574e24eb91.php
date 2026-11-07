<?php $__env->startSection('title', 'Saved addresses — EZiCart'); ?>
<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/buyer-addresses.css')); ?>?v=<?php echo e(filemtime(public_path('css/buyer-addresses.css'))); ?>">
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<main class="buyer-address-page">
    <header class="dash-header">
        <div><h1 class="dash-title">Saved addresses</h1><p class="dash-subtitle">Keep home, work and other delivery locations ready for checkout.</p></div>
        <div class="buyer-address-actions">
            <?php if(session('cart')): ?><a href="<?php echo e(route('checkout.index')); ?>" class="dash-btn-sm dash-btn-outline">Back to checkout</a><?php endif; ?>
            <a href="<?php echo e(route('buyer.addresses.create')); ?>" class="dash-btn-primary"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'location']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'location']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> Add an address</a>
        </div>
    </header>
    <?php if(session('success')): ?><p class="dash-alert-success" role="status"><?php echo e(session('success')); ?></p><?php endif; ?>
    <?php if($errors->any()): ?><div class="buyer-address-error" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?>
    <div class="buyer-address-list">
        <?php $__empty_1 = true; $__currentLoopData = $addresses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $address): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <article class="dash-panel buyer-address-card">
                <div class="buyer-address-card-heading"><h2><?php echo e($address->label); ?></h2><?php if($address->is_default): ?><span class="buyer-address-default">Default</span><?php endif; ?></div>
                <?php if (isset($component)) { $__componentOriginal4c91879c9f8a347f0eecddbeb176f7fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4c91879c9f8a347f0eecddbeb176f7fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.buyer-address-summary','data' => ['address' => $address]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('buyer-address-summary'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['address' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($address)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4c91879c9f8a347f0eecddbeb176f7fe)): ?>
<?php $attributes = $__attributesOriginal4c91879c9f8a347f0eecddbeb176f7fe; ?>
<?php unset($__attributesOriginal4c91879c9f8a347f0eecddbeb176f7fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4c91879c9f8a347f0eecddbeb176f7fe)): ?>
<?php $component = $__componentOriginal4c91879c9f8a347f0eecddbeb176f7fe; ?>
<?php unset($__componentOriginal4c91879c9f8a347f0eecddbeb176f7fe); ?>
<?php endif; ?>
                <div class="buyer-address-actions">
                    <a href="<?php echo e(route('buyer.addresses.edit', $address)); ?>" class="dash-btn-sm dash-btn-outline">Edit address</a>
                    <?php if (! ($address->is_default)): ?>
                        <form action="<?php echo e(route('buyer.addresses.default', $address)); ?>" method="POST"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><button type="submit" class="dash-btn-sm dash-btn-outline">Set as default</button></form>
                    <?php endif; ?>
                    <form action="<?php echo e(route('buyer.addresses.destroy', $address)); ?>" method="POST" data-decision="buyer.addresses.destroy" data-decision-title="Remove this address?" data-decision-message="This removes the saved address. Existing orders keep their original delivery details." data-decision-label="Remove address" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button type="submit" class="buyer-address-delete">Remove</button></form>
                </div>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="dash-panel buyer-address-empty"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'location']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'location']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><h2>No saved addresses yet</h2><p>Add a delivery address to use it again at checkout. Your first saved address becomes the default.</p><a href="<?php echo e(route('buyer.addresses.create')); ?>" class="dash-btn-primary">Add your first address</a></div>
        <?php endif; ?>
    </div>
    <div class="workspace-pagination"><?php echo e($addresses->links()); ?></div>
    <p class="buyer-address-help">The default address is selected automatically at checkout. You can choose a different saved address for each purchase.</p>
</main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/buyer/addresses/index.blade.php ENDPATH**/ ?>