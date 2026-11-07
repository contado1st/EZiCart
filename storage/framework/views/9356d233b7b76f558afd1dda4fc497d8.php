<?php ($process = app(\App\Services\OrderProgressService::class)->forViewer($order, auth()->user())); ?>
<section class="order-process" data-order-progress-id="<?php echo e($order->id); ?>" data-process-status="<?php echo e($process['status']); ?>" data-process-owner="<?php echo e($process['owner']); ?>" aria-label="Parcel progress">
    <div class="order-process-heading"><strong><?php echo e($order->order_number); ?> progress</strong><span data-process-owner><?php echo e($process['requires_action'] ? 'Your action is required' : ($process['owner'] ? 'Waiting for '.$process['owner_label'] : 'No further action required')); ?></span></div>
    <?php if (isset($component)) { $__componentOriginal2969209a6e703f0dcf1ea87165624c74 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2969209a6e703f0dcf1ea87165624c74 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.parcel-weight','data' => ['order' => $order]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('parcel-weight'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['order' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2969209a6e703f0dcf1ea87165624c74)): ?>
<?php $attributes = $__attributesOriginal2969209a6e703f0dcf1ea87165624c74; ?>
<?php unset($__attributesOriginal2969209a6e703f0dcf1ea87165624c74); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2969209a6e703f0dcf1ea87165624c74)): ?>
<?php $component = $__componentOriginal2969209a6e703f0dcf1ea87165624c74; ?>
<?php unset($__componentOriginal2969209a6e703f0dcf1ea87165624c74); ?>
<?php endif; ?>
    <ol class="order-process-steps"><?php $__currentLoopData = \App\Services\OrderProgressService::STEPS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li data-process-step="<?php echo e($loop->index); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-current' => $process['stage'] === $loop->index, 'is-complete' => $process['stage'] !== null && $process['stage'] > $loop->index]); ?>" <?php if($process['stage'] === $loop->index): ?> aria-current="step" <?php endif; ?>><?php echo e($step); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ol>
    <p data-process-message role="status" aria-live="polite"><?php echo e($process['message']); ?></p>
    <button class="dash-btn-sm dash-btn-outline" type="button" data-process-reload hidden>Workflow updated: refresh to load current controls</button>
</section>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/order-progress.blade.php ENDPATH**/ ?>