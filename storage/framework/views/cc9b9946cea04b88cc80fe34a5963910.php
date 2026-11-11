<?php $__env->startSection('workspace'); ?>
    <header class="ops-heading">
        <div>
            <h1>Completed and past work</h1>
            <p>Pickup and delivery records attached to your rider account.</p>
        </div>
        <div class="courier-actions">
            <a class="ops-btn ops-btn--primary" href="<?php echo e(route('courier.dashboard')); ?>">Back to dispatch board</a>
        </div>
    </header>

    <section class="ops-panel">
        <form class="ops-form" method="GET" action="<?php echo e(route('courier.history')); ?>" aria-label="Filter delivery history">
            <div class="ops-field">
                <label for="history-search">Order number</label>
                <input id="history-search" name="search" value="<?php echo e(request('search')); ?>" maxlength="100" placeholder="e.g. ORD-10293">
            </div>
            <div class="ops-field">
                <label for="history-status">Status</label>
                <select id="history-status" name="status">
                    <option value="">All statuses</option>
                    <?php $__currentLoopData = ['DELIVERED', 'COMPLETED', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER', 'PICKED_UP']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($status); ?>" <?php if(request('status') === $status): echo 'selected'; endif; ?>>
                            <?php echo e(str_replace('_', ' ', $status)); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <button class="ops-btn ops-btn--primary" type="submit">Apply filters</button>
            <?php if(request()->filled('search') || request()->filled('status')): ?>
                <a class="ops-btn" href="<?php echo e(route('courier.history')); ?>">Clear filters</a>
            <?php endif; ?>
        </form>

        <?php if(request()->filled('search') || request()->filled('status')): ?>
            <ul class="ops-filter-chips" aria-label="Active filters">
                <?php if(request()->filled('search')): ?>
                    <li>Order: <strong><?php echo e(request('search')); ?></strong></li>
                <?php endif; ?>
                <?php if(request()->filled('status')): ?>
                    <li>Status: <strong><?php echo e(str_replace('_', ' ', request('status'))); ?></strong></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>

        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Stage</th>
                        <th>Destination</th>
                        <th>Completed / Activity</th>
                        <th>Failure / notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="ops-mono" data-label="Order">
                                <strong><?php echo e($order->order_number); ?></strong><?php if (isset($component)) { $__componentOriginal2969209a6e703f0dcf1ea87165624c74 = $component; } ?>
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
<?php endif; ?>
                            </td>
                            <td data-label="Stage">
                                <span class="ops-status <?php echo e($order->status === 'DELIVERY_FAILED' ? 'ops-status--danger' : (in_array($order->status, ['DELIVERED', 'COMPLETED', 'PICKED_UP'], true) ? 'ops-status--green' : 'ops-status--amber')); ?>">
                                    <?php echo e(str_replace('_', ' ', $order->status)); ?>

                                </span>
                            </td>
                            <td data-label="Destination"><?php echo e($order->delivery_area ?? $order->municipality); ?></td>
                            <td data-label="Activity" class="ops-mono">
                                <?php echo e($order->delivered_at?->format('d M Y H:i') ?? ($order->failed_at?->format('d M Y H:i') ?? ($order->picked_up_at?->format('d M Y H:i') ?? $order->updated_at->format('d M Y H:i')))); ?>

                            </td>
                            <td data-label="Notes">
                                <?php if($order->delivery_failure_reason): ?>
                                    <span class="courier-failed-reason-tag">
                                        <?php echo e(str_replace('_', ' ', ucfirst($order->delivery_failure_reason))); ?>

                                    </span>
                                <?php else: ?>
                                    <?php echo e($order->delivery_notes ?? '—'); ?>

                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5">
                                <div class="ops-empty">No delivery history matches those filters.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="ops-pagination"><?php echo e($orders->links()); ?></div>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.courier', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/courier/history.blade.php ENDPATH**/ ?>