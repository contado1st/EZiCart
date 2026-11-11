<?php $__env->startSection('workspace'); ?>
    <header class="ops-heading">
        <div>
            <h1>Sorting queue</h1>
            <p>Sort received parcels into their destination delivery zones for dispatch staging.</p>
        </div>
        <div class="courier-actions">
            <span class="ops-status ops-status--pink"><?php echo e($parcels->total()); ?> awaiting sort</span>
        </div>
    </header>

    <section class="ops-panel">
        <div class="ops-heading">
            <div>
                <h2>Received parcels staging</h2>
                <p class="ops-muted">Destination areas are normalized by municipality and province for rider route mapping.</p>
            </div>
        </div>

        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Parcel</th>
                        <th>Recipient & address</th>
                        <th>Hub received</th>
                        <th>Resolved routing zone</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $parcels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
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
                                <?php if($order->parcel_code): ?>
                                    <div class="ops-muted">EZP:<?php echo e($order->parcel_code); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo e($order->recipient_name); ?></strong>
                                <div class="ops-muted"><?php echo e($order->barangay); ?>, <?php echo e($order->municipality); ?>, <?php echo e($order->province); ?></div>
                                <div class="ops-muted"><?php echo e($order->recipient_contact); ?></div>
                            </td>
                            <td>
                                <div><?php echo e($order->received_at?->format('d M Y H:i') ?? '—'); ?></div>
                                <div class="ops-muted"><?php echo e($order->received_at?->diffForHumans()); ?></div>
                            </td>
                            <td>
                                <div class="logistics-sort-flow">
                                    <span class="logistics-area">
                                        <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'location']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'location']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> <?php echo e($order->municipality); ?>, <?php echo e($order->province); ?>

                                    </span>
                                </div>
                            </td>
                            <td>
                                <form class="ops-form" method="POST" action="<?php echo e(route('logistics.orders.sort', $order)); ?>" data-decision="logistics.orders.sort" data-decision-title="Confirm sorting?" data-decision-message="Check the parcel and selected sorting details before recording this update." data-decision-label="Confirm sorting" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                    <?php echo csrf_field(); ?>
                                    <button class="ops-btn ops-btn--primary" type="submit">Confirm area and sort</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5">
                                <div class="ops-empty">
                                    <strong>Sorting queue clear</strong>
                                    <p>All received parcels have been sorted and staged for dispatch.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="ops-pagination"><?php echo e($parcels->links()); ?></div>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.logistics', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/logistics/sorting.blade.php ENDPATH**/ ?>