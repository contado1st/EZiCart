<?php $__env->startSection('content'); ?>
<main class="web-operations"><header><h1>Staff profiles</h1><p>Administrator access to verified profiles in your management domain.</p></header>
<?php echo $__env->make('components.feature-feedback', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<section class="dash-panel"><div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Staff member</th><th>Role and status</th><th>Service areas</th><th>Profile</th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $staff; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $person): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($person->first_name); ?> <?php echo e($person->last_name); ?></td><td><?php echo e(str_replace('_', ' ', $person->role)); ?> · <?php echo e($person->status); ?></td><td><?php echo e($person->serviceAreas->pluck('name')->join(', ') ?: 'No area recorded'); ?></td><td><a class="dash-btn-sm" href="<?php echo e(route('staff.show', $person)); ?>">View profile</a></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="4">No related staff profiles are available.</td></tr><?php endif; ?>
</tbody></table></div></section></main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/staff/index.blade.php ENDPATH**/ ?>