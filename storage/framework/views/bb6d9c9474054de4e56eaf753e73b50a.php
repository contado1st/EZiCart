<?php $__env->startSection('workspace'); ?>
    <header class="ops-heading">
        <div>
            <h1>My active parcels</h1>
            <p>Pickups, hub transfers, and active doorstep deliveries assigned to your rider account.</p>
        </div>
        <div class="courier-actions">
            <a class="ops-btn ops-btn--primary" href="<?php echo e(route('courier.dashboard')); ?>">Back to dispatch board</a>
        </div>
    </header>

    <?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <section class="ops-panel courier-delivery">
            <div class="ops-heading">
                <div>
                    <p class="ops-mono">Order <?php echo e($order->order_number); ?></p>
                    <h2><?php echo e($order->municipality); ?> · <?php echo e(str_replace('_', ' ', $order->status)); ?></h2>
                    <p class="ops-muted">Recipient: <?php echo e($order->recipient_name); ?> · <?php echo e($order->delivery_area); ?></p>
                </div>
                <div class="courier-tracking-state">
                    <span class="ops-status <?php echo e($order->status === 'DELIVERY_FAILED' ? 'ops-status--danger' : ($order->status === 'RETURN_IN_TRANSIT' ? 'ops-status--amber' : 'ops-status--pink')); ?>">
                        <?php echo e(str_replace('_', ' ', $order->status)); ?>

                    </span>
                    <span class="ops-muted">Updated <?php echo e($order->updated_at->diffForHumans()); ?></span>
                </div>
            </div>

            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Time</th>
                            <th>Location</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_2 = true; $__currentLoopData = $order->trackingEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                            <tr>
                                <td data-label="Event"><?php echo e(str_replace('_', ' ', $event->event_type)); ?></td>
                                <td data-label="Time" class="ops-mono"><?php echo e($event->created_at->format('d M H:i')); ?></td>
                                <td data-label="Location"><?php echo e($event->location ?? '—'); ?></td>
                                <td data-label="Details"><?php echo e($event->notes ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                            <tr>
                                <td colspan="4">
                                    <div class="ops-empty">No tracking updates recorded yet.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if($order->status !== 'RETURN_IN_TRANSIT'): ?>
                <div class="courier-actions ops-alert--compact">
                    <a class="ops-btn ops-btn--primary" href="<?php echo e(route('courier.orders.show', $order)); ?>">Open parcel details</a>
                    <a class="ops-btn" href="<?php echo e(route('courier.orders.messages.show', $order)); ?>">Order messages</a>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="ops-empty">
            <strong>No active parcels</strong>
            <p>You have no pickups or deliveries in progress right now.</p>
        </div>
    <?php endif; ?>

    <div class="ops-pagination"><?php echo e($orders->links()); ?></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.courier', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/courier/tracking.blade.php ENDPATH**/ ?>