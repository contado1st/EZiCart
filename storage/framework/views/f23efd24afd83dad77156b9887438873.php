<?php $__env->startSection('workspace'); ?>
    <header class="ops-heading">
        <div>
            <h1>Operations overview</h1>
            <p>Sorting hub · <?php echo e(now()->format('D, d M Y')); ?> · Real-time operational queues and hub throughput.</p>
        </div>
        <div class="courier-actions">
            <a class="ops-btn ops-btn--primary" href="<?php echo e(route('logistics.intake')); ?>">Receive parcel</a>
        </div>
    </header>

    <?php if($exceptions->isNotEmpty()): ?>
        <div class="logistics-triage-banner" role="alert">
            <div>
                <strong>Action Required: <?php echo e($exceptions->count()); ?> Rider Suspension <?php echo e(Str::plural('Exception', $exceptions->count())); ?></strong>
                <p>Parcels previously assigned to suspended couriers require physical hub recovery or route reassignment.</p>
            </div>
            <a class="ops-btn ops-btn--danger" href="#logistics-exceptions-title">Review exceptions</a>
        </div>
    <?php endif; ?>

    <section class="ops-stats ops-stats--priority" aria-label="Priority parcel queues">
        <div class="ops-stat ops-stat--action ops-stat--warning">
            <span class="workspace-stat-icon" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'delivery1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'delivery1']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></span>
            <span>Pickup arrivals</span>
            <strong><?php echo e($stats['inbound']); ?></strong>
            <small class="workspace-stat-detail">Inbound to your sorting center</small>
        </div>
        <div class="ops-stat ops-stat--action">
            <span class="workspace-stat-icon" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'package1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'package1']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></span>
            <span>Awaiting sort</span>
            <strong><?php echo e($stats['at_center']); ?></strong>
            <small class="workspace-stat-detail">Received and waiting to sort</small>
        </div>
        <div class="ops-stat ops-stat--action">
            <span class="workspace-stat-icon" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'checklist']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'checklist']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></span>
            <span>Ready for dispatch</span>
            <strong><?php echo e($stats['sorted']); ?></strong>
            <small class="workspace-stat-detail">Parcels ready for a rider</small>
        </div>
        <div class="ops-stat ops-stat--action ops-stat--danger">
            <span class="workspace-stat-icon" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'warning']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></span>
            <span>Failed deliveries</span>
            <strong><?php echo e($stats['failed']); ?></strong>
            <small class="workspace-stat-detail">Attempts requiring review</small>
        </div>
    </section>

    <section class="ops-panel <?php echo e($exceptions->isNotEmpty() ? 'ops-panel--urgent' : ''); ?>"
        aria-labelledby="logistics-exceptions-title">
        <h2 id="logistics-exceptions-title">
            <span>Rider suspension exceptions</span>
            <span class="ops-status <?php echo e($exceptions->isNotEmpty() ? 'ops-status--danger' : 'ops-status--green'); ?>">
                <?php echo e($exceptions->count()); ?> open
            </span>
        </h2>
        <?php if($exceptions->isEmpty()): ?>
            <div class="ops-empty">
                <strong>Everything is clear</strong>
                <p>No active parcels are waiting on suspended rider recovery.</p>
            </div>
        <?php else: ?>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Parcel</th>
                            <th>Former rider</th>
                            <th>Current stage</th>
                            <th>Reason</th>
                            <th>Next action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $exceptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $exception): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="ops-mono"><?php echo e($exception->order_number); ?></td>
                                <td><?php echo e($exception->rider_first_name); ?> <?php echo e($exception->rider_last_name); ?></td>
                                <td>
                                    <span class="ops-status ops-status--danger">
                                        <?php echo e(str_replace('_', ' ', $exception->order_status)); ?>

                                    </span>
                                </td>
                                <td><?php echo e($exception->reason); ?></td>
                                <td>
                                    <?php if($exception->type === 'SUSPENDED_RIDER_PICKUP_ACCEPTED'): ?>
                                        <a class="ops-btn ops-btn--primary" href="<?php echo e(route('logistics.pickupRequests')); ?>">Reassign pickup</a>
                                    <?php elseif($exception->order_status === 'OUT_FOR_DELIVERY'): ?>
                                        <a class="ops-btn" href="<?php echo e(route('logistics.dispatch')); ?>">Scan recovered parcel</a>
                                    <?php elseif($exception->order_status === 'ASSIGNED_TO_RIDER' && $exception->hub_released_at): ?>
                                        <form method="POST"
                                            action="<?php echo e(route('logistics.orders.recoverReleasedParcel', $exception->order_id)); ?>" data-decision="logistics.orders.recoverReleasedParcel" data-decision-title="Confirm parcel recovery?" data-decision-message="Continue only after logistics physically received the parcel back from the assigned rider." data-decision-label="Confirm recovery" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                            <?php echo csrf_field(); ?>
                                            <button class="ops-btn ops-btn--primary" type="submit">Confirm physical hub recovery</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="ops-muted">Physical recovery or dispatch review required</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="ops-grid" aria-label="Operational queues">
        <?php $__currentLoopData = [
            'inbound' => ['Inbound pickup arrivals', 'logistics.intake', 'Verify arrival'],
            'sorting' => ['Waiting to be sorted', 'logistics.sorting', 'Sort parcels'],
            'dispatch' => ['Ready for rider assignment', 'logistics.dispatch', 'Assign rider'],
            'failed' => ['Failed deliveries to review', 'logistics.tracking', 'Review tracking'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$title, $route, $actionLabel]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <section class="ops-panel">
                <h2>
                    <span><?php echo e($title); ?></span>
                    <a class="ops-btn" href="<?php echo e(route($route)); ?>"><?php echo e($actionLabel); ?></a>
                </h2>
                <?php if($queues[$key]->isEmpty()): ?>
                    <div class="ops-empty">
                        <p>No parcels currently waiting in this queue.</p>
                    </div>
                <?php else: ?>
                    <div class="ops-table-wrap">
                        <table class="ops-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Destination</th>
                                    <th>Current stage</th>
                                    <th>Last updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $queues[$key]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td class="ops-mono"><?php echo e($order->order_number); ?></td>
                                        <td><?php echo e($order->municipality); ?>, <?php echo e($order->province); ?></td>
                                        <td>
                                            <span
                                                class="ops-status <?php echo e($order->status === 'DELIVERY_FAILED' ? 'ops-status--danger' : 'ops-status--pink'); ?>">
                                                <?php echo e(str_replace('_', ' ', $order->status)); ?>

                                            </span>
                                        </td>
                                        <td class="ops-muted"><?php echo e($order->updated_at->diffForHumans()); ?></td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </section>

    <section class="ops-panel" aria-labelledby="parcel-flow-title">
        <h2 id="parcel-flow-title">Parcel lifecycle pipeline</h2>
        <div class="ops-flow">
            <div class="ops-flow-step">
                <strong>1. Seller pickup</strong>
                <span><?php echo e($stats['inbound']); ?> inbound</span>
            </div>
            <div class="ops-flow-step">
                <strong>2. Hub arrival</strong>
                <span><?php echo e($stats['at_center']); ?> to sort</span>
            </div>
            <div class="ops-flow-step">
                <strong>3. Area sorting</strong>
                <span><?php echo e($stats['sorted']); ?> staged</span>
            </div>
            <div class="ops-flow-step">
                <strong>4. Dispatch</strong>
                <span><?php echo e($stats['assigned']); ?> assigned</span>
            </div>
            <div class="ops-flow-step">
                <strong>5. In transit</strong>
                <span><?php echo e($stats['out']); ?> active</span>
            </div>
            <div class="ops-flow-step">
                <strong>6. Delivered today</strong>
                <span><?php echo e($stats['delivered_today']); ?> completed</span>
            </div>
        </div>
        <ul class="ops-summary" aria-label="Additional hub totals">
            <li>Approved riders <strong><?php echo e($stats['active_riders']); ?></strong></li>
            <li>Available riders <strong><?php echo e($stats['available_riders']); ?></strong></li>
            <li>Returns in transit <strong><?php echo e($stats['returned']); ?></strong></li>
        </ul>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.logistics', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/logistics/dashboard.blade.php ENDPATH**/ ?>