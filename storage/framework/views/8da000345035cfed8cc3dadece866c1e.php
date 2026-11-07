<?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'login']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'login']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\storage\framework\views/1c9ac80f38e16fb69b4bb0fb3ffc85bf.blade.php ENDPATH**/ ?>