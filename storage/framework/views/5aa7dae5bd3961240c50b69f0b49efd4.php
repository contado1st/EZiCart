<?php $__env->startSection('content'); ?><main class="web-operations"><header><h1>Shop availability</h1><p>Let buyers know when your shop can accept new orders.</p></header><?php echo $__env->make('components.feature-feedback', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><section class="dash-panel"><?php if (isset($component)) { $__componentOriginal2a57c4884e76622b11c541e29b5abeb4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a57c4884e76622b11c541e29b5abeb4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user-activity','data' => ['user' => $seller]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user-activity'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($seller)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a57c4884e76622b11c541e29b5abeb4)): ?>
<?php $attributes = $__attributesOriginal2a57c4884e76622b11c541e29b5abeb4; ?>
<?php unset($__attributesOriginal2a57c4884e76622b11c541e29b5abeb4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a57c4884e76622b11c541e29b5abeb4)): ?>
<?php $component = $__componentOriginal2a57c4884e76622b11c541e29b5abeb4; ?>
<?php unset($__componentOriginal2a57c4884e76622b11c541e29b5abeb4); ?>
<?php endif; ?>
<form class="web-form" method="POST" action="<?php echo e(route('seller.availability.update')); ?>" data-decision="seller.availability.update" data-decision-title="Update shop availability?" data-decision-message="A closed or vacation shop pauses new web orders. Existing parcels remain in their current workflow." data-decision-label="Save availability"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><label for="shop-state">Availability<select id="shop-state" name="shop_availability"><?php $__currentLoopData = ['open' => 'Open', 'closed' => 'Temporarily closed', 'vacation' => 'On vacation', 'unavailable' => 'Temporarily unavailable']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if(old('shop_availability', $seller->shop_availability) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><label for="shop-from">Begins (blank means now)<input id="shop-from" type="datetime-local" name="shop_unavailable_from" value="<?php echo e(old('shop_unavailable_from', $seller->shop_unavailable_from?->format('Y-m-d\TH:i'))); ?>"></label><label for="shop-return">Expected return (optional)<input id="shop-return" type="datetime-local" name="shop_returns_at" value="<?php echo e(old('shop_returns_at', $seller->shop_returns_at?->format('Y-m-d\TH:i'))); ?>"></label><button class="dash-btn-primary" type="submit">Save availability</button></form><p>New orders automatically resume when the return time passes. An inactivity warning is calculated separately from this setting.</p></section></main><?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/seller/availability.blade.php ENDPATH**/ ?>