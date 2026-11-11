<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="dash-wrapper">
        <!-- Sidebar Navigation -->
<!-- Main Workspace -->
        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Order Fulfillment & Dispatch</h1>
                    <p class="dash-subtitle">Accept incoming buyer orders, pack packages, and generate waybills.</p>
                </div>
            </div>

            <?php if(session('success')): ?>
                <div class="dash-alert-success"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> <?php echo e(session('success')); ?></div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <div class="order-tabs-bar">
                <a href="<?php echo e(route('seller.orders.index')); ?>"
                    class="order-tab-link <?php echo e(!request('status') ? 'active' : ''); ?>">
                    All Orders (<?php echo e($counts['all']); ?>)
                </a>
                <a href="<?php echo e(route('seller.orders.index', ['status' => 'PLACED'])); ?>"
                    class="order-tab-link <?php echo e(request('status') === 'PLACED' ? 'active' : ''); ?>">
                    New Placed (<?php echo e($counts['placed']); ?>)
                </a>
                <a href="<?php echo e(route('seller.orders.index', ['status' => 'PREPARING'])); ?>"
                    class="order-tab-link <?php echo e(request('status') === 'PREPARING' ? 'active' : ''); ?>">
                    In Packing (<?php echo e($counts['preparing']); ?>)
                </a>
                <a href="<?php echo e(route('seller.orders.index', ['status' => 'READY_FOR_PICKUP'])); ?>"
                    class="order-tab-link <?php echo e(request('status') === 'READY_FOR_PICKUP' ? 'active' : ''); ?>">
                    Ready for Pickup (<?php echo e($counts['ready']); ?>)
                </a>
                <a href="<?php echo e(route('seller.orders.index', ['status' => 'COMPLETED'])); ?>"
                    class="order-tab-link <?php echo e(request('status') === 'COMPLETED' ? 'active' : ''); ?>">
                    Completed (<?php echo e($counts['completed']); ?>)
                </a>
            </div>

            <!-- Orders Feed -->
            <?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="order-card">
                    <div class="order-card-header">
                        <div>
                            <a href="<?php echo e(route('seller.orders.show', $order->id)); ?>"
                                class="order-number"><?php echo e($order->order_number); ?></a><?php if (isset($component)) { $__componentOriginal2969209a6e703f0dcf1ea87165624c74 = $component; } ?>
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
                            <div class="order-date">
                                Ordered on <?php echo e($order->created_at->format('M d, Y h:i A')); ?>

                            </div>
                        </div>
                        <div>
                            <span class="status-pill status-<?php echo e(strtolower(str_replace('_', '-', $order->status))); ?>">
                                <?php echo e($order->pendingCancellation ? 'CANCELLATION PENDING' : str_replace('_', ' ', $order->status)); ?>

                            </span>
                        </div>
                    </div>

                    <!-- Recipient Delivery Address -->
                    <div class="order-recipient-box">
                        Deliver to: <span class="order-recipient-name"><?php echo e($order->recipient_name); ?></span>
                        (<?php echo e($order->recipient_contact); ?>)
                        &bull;
                        <?php echo e($order->street_address); ?>, <?php echo e($order->barangay); ?>, <?php echo e($order->municipality); ?>,
                        <?php echo e($order->province); ?>

                    </div>

                    <!-- Item Line Listings -->
                    <div class="order-item-listing">
                        <?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="order-item-row">
                                <div>
                                    <strong><?php echo e($item->product_name); ?></strong>
                                    <span class="u-extracted-1c2d63da9b">x<?php echo e($item->quantity); ?></span>
                                </div>
                                <div>₱<?php echo e(number_format($item->item_total, 2)); ?></div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>

                    <!-- Footer Summary & Actions -->
                    <div class="order-card-footer">
                        <div class="order-total-block">
                            Payment: <strong><?php echo e($order->payment_method); ?></strong> &bull;
                            Net Earnings: <strong
                                class="u-extracted-f8f1327255">₱<?php echo e(number_format($order->subtotal - $order->commission_fee, 2)); ?></strong>
                            <span class="u-extracted-cf0c302441">(10% platform fee
                                deducted)</span>
                        </div>

                        <div class="order-seller-action-row">
                            <a href="<?php echo e(route('seller.orders.waybill', $order->id)); ?>" target="_blank"
                                class="dash-btn-sm dash-btn-outline">
                                <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'print']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'print']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> Waybill
                            </a>

                            <?php if(
                                $order->payment_method !== 'COD' &&
                                    in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true)): ?>
                                <p role="status">Fulfillment on hold: payment verification is not configured for
                                    <?php echo e($order->payment_method); ?>.</p>
                            <?php elseif($order->status === 'PLACED'): ?>
                                <form action="<?php echo e(route('seller.orders.updateStatus', $order->id)); ?>" method="POST" data-decision="seller.orders.updateStatus" data-decision-title="Update order status?" data-decision-message="Check the selected status. This updates the order workflow and may notify the buyer and delivery staff." data-decision-label="Update status" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PATCH'); ?>
                                    <input type="hidden" name="status" value="CONFIRMED">
                                    <button type="submit" class="dash-btn-sm dash-btn-primary">
                                        Accept Order
                                    </button>
                                </form>
                            <?php elseif($order->status === 'CONFIRMED'): ?>
                                <form action="<?php echo e(route('seller.orders.updateStatus', $order->id)); ?>" method="POST" data-decision="seller.orders.updateStatus" data-decision-title="Update order status?" data-decision-message="Check the selected status. This updates the order workflow and may notify the buyer and delivery staff." data-decision-label="Update status" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PATCH'); ?>
                                    <input type="hidden" name="status" value="PREPARING">
                                    <button type="submit" class="dash-btn-sm dash-btn-primary">Start Preparing</button>
                                </form>
                            <?php elseif($order->status === 'PREPARING'): ?>
                                <form action="<?php echo e(route('seller.orders.updateStatus', $order->id)); ?>" method="POST" data-decision="seller.orders.updateStatus" data-decision-title="Update order status?" data-decision-message="Check the selected status. This updates the order workflow and may notify the buyer and delivery staff." data-decision-label="Update status" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PATCH'); ?>
                                    <input type="hidden" name="status" value="READY_FOR_PICKUP">
                                    <button type="submit" class="dash-btn-sm dash-btn-primary">
                                        Mark Ready for Pickup
                                    </button>
                                </form>
                            <?php elseif($order->status === 'READY_FOR_PICKUP' && $order->pickup_arrived_at && !$order->seller_handover_at): ?>
                                <form action="<?php echo e(route('seller.orders.confirmHandover', $order)); ?>" method="POST" data-decision="seller.orders.confirmHandover" data-decision-title="Confirm physical handover?" data-decision-message="Continue only after you physically handed the parcel to the assigned rider." data-decision-label="Confirm handover" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                    <?php echo csrf_field(); ?>
                                    <label for="handover-rider-badge-<?php echo e($order->id); ?>">Scan assigned rider badge
                                        QR/code</label>
                                    <input id="handover-rider-badge-<?php echo e($order->id); ?>" name="rider_badge" data-qr-input
                                        required maxlength="100" placeholder="EZR:…" autocomplete="off">
                                    <input type="hidden" name="method" value="manual">
                                    <button type="button" data-qr-start>Use camera</button><button type="button"
                                        data-qr-stop hidden>Stop camera</button>
                                    <video data-qr-video playsinline hidden></video>
                                    <p data-qr-status role="status">Camera, handheld scanner, or manual code entry is
                                        supported.</p>
                                    <button type="submit" class="dash-btn-sm dash-btn-primary">Confirm rider handover</button>
                                </form>
                            <?php elseif($order->status === 'RETURN_IN_TRANSIT'): ?>
                                <?php if($order->return_handed_to_seller_at): ?>
                                    <form action="<?php echo e(route('seller.orders.confirmReturn', $order)); ?>" method="POST" data-decision="seller.orders.confirmReturn" data-decision-title="Confirm returned parcel?" data-decision-message="Continue only after you physically received the returned parcel." data-decision-label="Confirm return" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        <?php echo csrf_field(); ?>
                                        <label for="return-rider-badge-<?php echo e($order->id); ?>">Scan return rider badge
                                            QR/code</label>
                                        <input id="return-rider-badge-<?php echo e($order->id); ?>" name="rider_badge" data-qr-input
                                            required maxlength="100" placeholder="EZR:…" autocomplete="off">
                                        <input type="hidden" name="method" value="manual">
                                        <button type="button" data-qr-start>Use camera</button><button type="button"
                                            data-qr-stop hidden>Stop camera</button>
                                        <video data-qr-video playsinline hidden></video>
                                        <p data-qr-status role="status">Camera, handheld scanner, or manual entry.</p>
                                        <button type="submit" class="dash-btn-sm dash-btn-primary">Confirm scanned return received</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted-small">Awaiting courier handoff</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="dash-empty-box">
                    <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> No customer orders found under this status.
                </div>
            <?php endif; ?>

            <div class="u-extracted-d4f7536fd4">
                <?php echo e($orders->links()); ?>

            </div>
        </main>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/seller/orders/index.blade.php ENDPATH**/ ?>