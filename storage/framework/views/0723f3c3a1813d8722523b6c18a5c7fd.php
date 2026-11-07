<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/logistics/logistics.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="ops-shell" data-ops-shell>
        <main class="ops-main">
            <?php if(session('success')): ?>
                <div class="ops-alert" role="status"><?php echo e(session('success')); ?></div>
            <?php endif; ?>
            <?php if(session('error')): ?>
                <div class="ops-alert ops-alert--error" role="alert"><?php echo e(session('error')); ?></div>
            <?php endif; ?>
            <?php if($errors->any()): ?>
                <div class="ops-alert ops-alert--error" role="alert"><?php echo e($errors->first()); ?></div>
            <?php endif; ?>
            <?php echo $__env->yieldContent('workspace'); ?>
        </main>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/layouts/logistics.blade.php ENDPATH**/ ?>