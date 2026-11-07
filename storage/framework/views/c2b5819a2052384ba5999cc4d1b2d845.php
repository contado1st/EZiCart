<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['values', 'label' => 'Order status', 'description' => null]));

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

foreach (array_filter((['values', 'label' => 'Order status', 'description' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $distributionTotal = array_sum($values);
    $distributionOffset = 0;
?>
<section class="workspace-distribution" aria-label="<?php echo e($label); ?>">
    <h2><?php echo e($label); ?></h2>
    <?php if($description): ?><p class="workspace-panel-description"><?php echo e($description); ?></p><?php endif; ?>
    <div class="workspace-distribution-body">
        <div class="workspace-donut">
            <svg viewBox="0 0 160 160" aria-hidden="true" focusable="false">
                <circle class="workspace-donut-track" cx="80" cy="80" r="60" />
                <?php $__currentLoopData = $values; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $statusLabel => $statusCount): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $distributionPercent = $distributionTotal > 0 ? $statusCount / $distributionTotal * 100 : 0; ?>
                    <circle class="workspace-chart-color-<?php echo e($loop->index % 6); ?>" cx="80" cy="80" r="60" pathLength="100" stroke-dasharray="<?php echo e($distributionPercent); ?> <?php echo e(100 - $distributionPercent); ?>" stroke-dashoffset="<?php echo e(-$distributionOffset); ?>" />
                    <?php $distributionOffset += $distributionPercent; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </svg>
            <div><strong><?php echo e($distributionTotal); ?></strong><span>orders</span></div>
        </div>
        <dl class="workspace-chart-legend">
            <?php $__empty_1 = true; $__currentLoopData = $values; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $statusLabel => $statusCount): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div><dt><span class="workspace-chart-dot workspace-chart-color-<?php echo e($loop->index % 6); ?>" aria-hidden="true"></span><?php echo e(ucwords(strtolower(str_replace('_', ' ', $statusLabel)))); ?></dt><dd><?php echo e($statusCount); ?></dd></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div><dt>No orders to display yet.</dt><dd>0</dd></div>
            <?php endif; ?>
        </dl>
    </div>
</section>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/workspace-distribution.blade.php ENDPATH**/ ?>