<?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'product']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'product']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\storage\framework\views/db427b8080184507a080c7bbc50e4802.blade.php ENDPATH**/ ?>