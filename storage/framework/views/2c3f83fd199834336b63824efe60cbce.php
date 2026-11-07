<?php $__env->startPush('styles'); ?> <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?> <?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<main class="container workflow-admin-page"><h1>Platform vouchers</h1>
    <?php if(session('success')): ?><p role="status"><?php echo e(session('success')); ?></p><?php endif; ?>
    <?php if($errors->any()): ?><div role="alert"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><?php echo e($message); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
    <form method="POST" action="<?php echo e(route('admin.vouchers.store')); ?>" class="dash-panel"><?php echo csrf_field(); ?>
        <label>Code<input name="code" required maxlength="50" value="<?php echo e(old('code')); ?>"></label>
        <label>Discount type<select name="type" required><option value="fixed">Fixed amount</option><option value="percent">Percentage</option></select></label>
        <label>Value<input name="value" type="number" min="0.01" step="0.01" required value="<?php echo e(old('value')); ?>"></label>
        <label>Minimum eligible spend<input name="min_spend" type="number" min="0" step="0.01" required value="<?php echo e(old('min_spend', 0)); ?>"></label>
        <label>Maximum discount (optional)<input name="max_discount" type="number" min="0.01" step="0.01" value="<?php echo e(old('max_discount')); ?>"></label>
        <label>Usage limit (optional)<input name="usage_limit" type="number" min="1" value="<?php echo e(old('usage_limit')); ?>"></label>
        <label>Expiry (optional)<input name="expires_at" type="datetime-local" value="<?php echo e(old('expires_at')); ?>"></label>
        <button class="dash-btn-sm" type="submit">Create platform voucher</button>
    </form>
    <?php $__empty_1 = true; $__currentLoopData = $vouchers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $voucher): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><article class="dash-panel"><h2><?php echo e($voucher->code); ?></h2><p><?php echo e($voucher->type); ?>: <?php echo e($voucher->value); ?> · <?php echo e($voucher->used_count); ?> uses · <?php echo e($voucher->is_active ? 'Active' : 'Disabled'); ?></p>
        <form method="POST" action="<?php echo e(route('admin.vouchers.toggle', $voucher)); ?>" data-decision="admin.vouchers.toggle" data-decision-title="Change platform voucher availability?" data-decision-message="This changes whether buyers can use the selected platform voucher." data-decision-label="Change availability" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><button class="dash-btn-sm" type="submit"><?php echo e($voucher->is_active ? 'Disable' : 'Enable'); ?> voucher</button></form>
    </article><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No platform vouchers.</p><?php endif; ?><?php echo e($vouchers->links()); ?>

</main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/admin/vouchers.blade.php ENDPATH**/ ?>