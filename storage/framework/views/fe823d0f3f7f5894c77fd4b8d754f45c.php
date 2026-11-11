<?php $__env->startSection('title', 'Activity logs | EZiCart'); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/activity-logs.css')); ?>?v=<?php echo e(filemtime(public_path('css/activity-logs.css'))); ?>">
<script src="<?php echo e(asset('js/activity-logs.js')); ?>?v=<?php echo e(filemtime(public_path('js/activity-logs.js'))); ?>" defer></script>
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('components.feature-feedback', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="feature-page"><header><div><h1>Activity logs</h1><p>Administrative and operational changes within your administration domain, plus system events.</p></div></header>
<form class="feature-panel feature-form" method="GET">
<label>Actor ID<input type="number" name="actor_id" value="<?php echo e(request('actor_id')); ?>" min="1"></label><label>Activity<input name="action" value="<?php echo e(request('action')); ?>" maxlength="100"></label><label>Module<select name="module"><option value="">All permitted modules</option><?php $__currentLoopData = ['commerce', 'logistics', 'system']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($module); ?>" <?php if(request('module') === $module): echo 'selected'; endif; ?>><?php echo e(ucfirst($module)); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><label>Record ID<input type="number" min="1" name="record" value="<?php echo e(request('record')); ?>"></label><label>Record type<input name="entity_type" value="<?php echo e(request('entity_type')); ?>" maxlength="150" placeholder="App\Models\Order"></label><label>From<input type="date" name="from" value="<?php echo e(request('from')); ?>"></label><label>To<input type="date" name="to" value="<?php echo e(request('to')); ?>"></label><button class="feature-button">Filter logs</button></form>
<div class="feature-table-scroll"><table class="feature-table activity-log-table">
    <thead><tr><th scope="col">When / Actor</th><th scope="col">Activity</th><th scope="col">Record</th><th scope="col">Changes</th></tr></thead>
    <tbody>
    <?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr class="activity-entry">
            <td data-label="When / Actor"><time><?php echo e($log->created_at); ?></time><strong><?php echo e($log->actor_id ? (trim(($log->first_name ?? '').' '.($log->last_name ?? '')) ?: 'User').' (#'.$log->actor_id.')' : 'System'); ?></strong><small>Log #<?php echo e($log->id); ?></small></td>
            <td data-label="Activity"><strong><?php echo e(\Illuminate\Support\Str::headline($log->action)); ?></strong><small><?php echo e($log->role ? \Illuminate\Support\Str::headline($log->role) : 'System event'); ?></small></td>
            <td data-label="Record"><?php echo e(class_basename($log->entity_type)); ?> #<?php echo e($log->entity_id); ?></td>
            <td class="activity-toggle-cell"><button type="button" class="activity-toggle" data-activity-toggle aria-controls="activity-detail-<?php echo e($log->id); ?>" aria-expanded="true" hidden><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'arrowback']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'arrowback']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><span data-activity-toggle-label>View changes</span><span class="activity-sr-only"> for log #<?php echo e($log->id); ?></span></button></td>
        </tr>
        <tr class="activity-detail-row" id="activity-detail-<?php echo e($log->id); ?>"><td colspan="4"><?php if (isset($component)) { $__componentOriginaldc36c589f8669d5c5975058644beaa40 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldc36c589f8669d5c5975058644beaa40 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.activity-changes','data' => ['log' => $log]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('activity-changes'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['log' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($log)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldc36c589f8669d5c5975058644beaa40)): ?>
<?php $attributes = $__attributesOriginaldc36c589f8669d5c5975058644beaa40; ?>
<?php unset($__attributesOriginaldc36c589f8669d5c5975058644beaa40); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldc36c589f8669d5c5975058644beaa40)): ?>
<?php $component = $__componentOriginaldc36c589f8669d5c5975058644beaa40; ?>
<?php unset($__componentOriginaldc36c589f8669d5c5975058644beaa40); ?>
<?php endif; ?></td></tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="4">No matching events.</td></tr>
    <?php endif; ?>
    </tbody>
</table></div><?php echo e($logs->links()); ?></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/admin/activity.blade.php ENDPATH**/ ?>