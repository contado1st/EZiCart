<?php $__env->startSection('title', 'Weight & load planning | EZiCart'); ?>
<?php $__env->startSection('content'); ?>
<main class="web-operations">
    <header><h1>Weight & load planning</h1><p>Plan pickup, delivery, returns and hub transport using actual packed weights and verified vehicle cargo limits.</p></header>
    <?php echo $__env->make('components.feature-feedback', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <section class="dash-panel"><h2>Vehicle reservations</h2><p class="web-muted">Loads include assigned pickups, active delivery/return parcels and in-transit hub transfers. Reservations clear when a parcel is unloaded or its assignment is released. Unknown weights never count as zero available cargo.</p>
        <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Rider / vehicle</th><th>Verified cargo limit</th><th>Reserved load</th><th>Remaining capacity</th><th>Planning status</th></tr></thead><tbody>
        <?php $__empty_1 = true; $__currentLoopData = $riders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php ($load = $loadSummaries[$rider->id]); ?><tr><td><strong><?php echo e($rider->first_name); ?> <?php echo e($rider->last_name); ?></strong><div><?php echo e($rider->vehicle_type ?: 'Not recorded'); ?> · <?php echo e($rider->plate_number ?: 'No plate recorded'); ?></div><?php if($actor->isLogisticsAdmin()): ?><a href="<?php echo e(route('staff.show', $rider)); ?>">Verify vehicle capacity</a><?php endif; ?></td><td><?php echo e(\App\Services\ParcelLoadService::kg($load['capacity'])); ?></td><td><?php echo e(\App\Services\ParcelLoadService::kg($load['grams'])); ?> / <?php echo e($load['count']); ?> parcels <?php if($load['unknown']): ?><strong class="profile-field-error">+ <?php echo e($load['unknown']); ?> unweighed</strong><?php endif; ?></td><td><?php echo e(\App\Services\ParcelLoadService::kg($load['remaining'])); ?></td><td><?php if($load['capacity'] === null): ?><strong class="profile-field-error">Blocked: admin capacity verification needed</strong><?php elseif($load['unknown']): ?><strong class="profile-field-error">Blocked: weigh all assigned parcels</strong><?php elseif($load['overweight']): ?><strong class="profile-field-error">Over capacity: reassign before movement</strong><?php else: ?> Within verified capacity <?php endif; ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5">No assigned or managed riders are available in this view.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <section class="dash-panel"><h2>Parcels awaiting movement</h2><div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Parcel</th><th>Status</th><th>Actual packed weight</th><th>Assigned pickup / delivery / transport</th><th>Record / inspect weight</th></tr></thead><tbody>
    <?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($order->order_number); ?></td><td><?php echo e(Str::headline($order->status)); ?></td><td><?php if (isset($component)) { $__componentOriginal2969209a6e703f0dcf1ea87165624c74 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2969209a6e703f0dcf1ea87165624c74 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.parcel-weight','data' => ['order' => $order,'compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('parcel-weight'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['order' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order),'compact' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2969209a6e703f0dcf1ea87165624c74)): ?>
<?php $attributes = $__attributesOriginal2969209a6e703f0dcf1ea87165624c74; ?>
<?php unset($__attributesOriginal2969209a6e703f0dcf1ea87165624c74); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2969209a6e703f0dcf1ea87165624c74)): ?>
<?php $component = $__componentOriginal2969209a6e703f0dcf1ea87165624c74; ?>
<?php unset($__componentOriginal2969209a6e703f0dcf1ea87165624c74); ?>
<?php endif; ?></td><td><?php echo e($order->pickupCourier?->first_name ?: 'No pickup rider'); ?> / <?php echo e($order->deliveryCourier?->first_name ?: 'No delivery rider'); ?><?php if($order->activeTransfer?->carrier): ?> / <?php echo e($order->activeTransfer->carrier->first_name); ?> (hub transport)<?php endif; ?></td><td><?php if($actor->role !== 'courier' || in_array($actor->id, [$order->pickup_courier_id, $order->delivery_courier_id], true)): ?><a class="dash-btn-sm dash-btn-outline" href="<?php echo e(route('parcel-evidence.show', $order)); ?>">Parcel weight & journey</a><?php else: ?> Hub transport assigned <?php endif; ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5">No parcels currently require movement planning.</td></tr><?php endif; ?>
    </tbody></table></div><?php echo e($orders->links()); ?></section>
</main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/logistics/load-planning.blade.php ENDPATH**/ ?>