<?php $__env->startSection('workspace'); ?>
    <header class="ops-heading">
        <div>
            <h1>Seller pickup requests</h1>
            <p>Review new seller pickup requests, dispatch approved couriers, or reassign unclaimed routes.</p>
        </div>
        <div class="courier-actions">
            <span class="ops-status ops-status--pink"><?php echo e($orders->total()); ?> pending pickup</span>
        </div>
    </header>

    <section class="ops-panel">
        <div class="ops-heading">
            <div>
                <h2>Pickup requests queue</h2>
                <p class="ops-muted">Approved riders serving the seller's area are eligible for pickup dispatch.</p>
            </div>
        </div>

        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order & schedule</th>
                        <th>Seller pickup point</th>
                        <th>Parcel items</th>
                        <th>Rider assignment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="ops-mono">
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
                                <div class="ops-muted">
                                    <?php echo e($order->pickup_scheduled_for?->format('d M Y, h:i A') ?? 'Immediate dispatch'); ?>

                                    <?php if($order->pickup_window): ?>
                                        · <?php echo e($order->pickup_window); ?>

                                    <?php endif; ?>
                                </div>
                                <?php if($order->pickup_notes): ?>
                                    <div class="ops-muted">Note: <?php echo e($order->pickup_notes); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo e($order->seller?->business_name ?? $order->seller?->first_name); ?></strong>
                                <div class="ops-muted">
                                    <?php echo e(trim(($order->seller?->house_number ? $order->seller->house_number.' ' : '').($order->seller?->street_address ?? ''))); ?>, <?php echo e($order->seller?->municipality); ?>

                                </div>
                                <div class="ops-muted"><?php echo e($order->seller?->contact_no); ?></div>
                            </td>
                            <td>
                                <strong><?php echo e($order->items->count()); ?> item(s)</strong>
                                <div class="ops-muted">₱<?php echo e(number_format($order->total_amount, 2)); ?> (COD)</div>
                            </td>
                            <td>
                                <?php if($order->pickupCourier): ?>
                                    <div class="ops-muted">
                                        Current: <strong><?php echo e($order->pickupCourier->first_name); ?> <?php echo e($order->pickupCourier->last_name); ?></strong>
                                        (<?php echo e(ucfirst($order->pickupCourier->status)); ?>)
                                    </div>
                                <?php endif; ?>
                                <form class="ops-form" method="POST" action="<?php echo e(route('logistics.orders.assignPickup', $order)); ?>" data-decision="logistics.orders.assignPickup" data-decision-title="Assign pickup rider?" data-decision-message="Check the selected rider and pickup details before assigning this parcel." data-decision-label="Assign pickup" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                    <?php echo csrf_field(); ?>
                                    <div class="ops-field">
                                        <label for="pickup-rider-<?php echo e($order->id); ?>">
                                            <?php echo e($order->pickupCourier ? 'Replacement rider' : 'Select approved rider'); ?>

                                        </label>
                                        <select id="pickup-rider-<?php echo e($order->id); ?>" name="pickup_courier_id" required>
                                            <option value="">Select rider</option>
                                            <?php $__currentLoopData = $riders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($rider->id); ?>">
                                                    <?php echo e($rider->first_name); ?> <?php echo e($rider->last_name); ?> (<?php echo e($rider->vehicle_type); ?>; <?php echo e($rider->vehicle_capacity_verified_at ? \App\Services\ParcelLoadService::kg($rider->vehicle_capacity_grams) : 'capacity unverified'); ?>)
                                                </option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                    <button class="ops-btn ops-btn--primary" type="submit" <?php if($riders->isEmpty()): echo 'disabled'; endif; ?>>
                                        <?php echo e($order->pickupCourier ? 'Reassign pickup' : 'Assign pickup'); ?>

                                    </button>
                                    <?php if($riders->isEmpty()): ?>
                                        <p class="ops-muted">No approved riders match this area currently.</p>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="4">
                                <div class="ops-empty">
                                    <strong>No pickup requests</strong>
                                    <p>New seller pickup requests will appear here for rider assignment.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="ops-pagination"><?php echo e($orders->links()); ?></div>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.logistics', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/logistics/pickup-requests.blade.php ENDPATH**/ ?>