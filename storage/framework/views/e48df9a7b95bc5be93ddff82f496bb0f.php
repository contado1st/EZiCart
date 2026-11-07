<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/buyer/reviews.css'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/disputes.css'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/buyer-account.css')); ?>?v=<?php echo e(filemtime(public_path('css/buyer-account.css'))); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="buyer-account-page buyer-orders-layout">

        <main class="dash-main buyer-account-content buyer-orders-content">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">My purchases</h1>
                    <p class="dash-subtitle">Track parcel progress, confirm receipt, and review delivered purchases.</p>
                </div>
            </div>

            <section class="workspace-metrics" aria-label="Purchase summary">
                <?php if (isset($component)) { $__componentOriginal427225e64668c174be9348a122c04dee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal427225e64668c174be9348a122c04dee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workspace-metric','data' => ['label' => 'All orders','value' => $counts['all'],'icon' => 'cart1','detail' => 'Your purchase history']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workspace-metric'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'All orders','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($counts['all']),'icon' => 'cart1','detail' => 'Your purchase history']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $attributes = $__attributesOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__attributesOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $component = $__componentOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__componentOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal427225e64668c174be9348a122c04dee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal427225e64668c174be9348a122c04dee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workspace-metric','data' => ['label' => 'Preparing to ship','value' => $counts['to_ship'],'icon' => 'package1','detail' => 'Placed, confirmed or being prepared','tone' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workspace-metric'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Preparing to ship','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($counts['to_ship']),'icon' => 'package1','detail' => 'Placed, confirmed or being prepared','tone' => 'warning']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $attributes = $__attributesOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__attributesOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $component = $__componentOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__componentOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal427225e64668c174be9348a122c04dee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal427225e64668c174be9348a122c04dee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workspace-metric','data' => ['label' => 'To receive','value' => $counts['to_receive'],'icon' => 'delivery1','detail' => 'Track your parcel and delivery']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workspace-metric'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'To receive','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($counts['to_receive']),'icon' => 'delivery1','detail' => 'Track your parcel and delivery']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $attributes = $__attributesOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__attributesOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $component = $__componentOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__componentOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal427225e64668c174be9348a122c04dee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal427225e64668c174be9348a122c04dee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workspace-metric','data' => ['label' => 'Closed orders','value' => $counts['completed'],'icon' => 'checklist','detail' => 'Completed, cancelled or returned','tone' => 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workspace-metric'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Closed orders','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($counts['completed']),'icon' => 'checklist','detail' => 'Completed, cancelled or returned','tone' => 'success']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $attributes = $__attributesOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__attributesOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $component = $__componentOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__componentOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
            </section>

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

            <?php if(session('error')): ?>
                <div class="dash-alert-success u-extracted-ee8231be38">
                    <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> <?php echo e(session('error')); ?>

                </div>
            <?php endif; ?>

            <div class="dash-panel">
                <div class="workspace-orders-heading">
                    <h2 class="dash-panel-title">Order history</h2>
                    <form method="GET" action="<?php echo e(route('buyer.dashboard')); ?>" class="workspace-order-filter">
                        <label for="dashboard-order-status">Status</label>
                        <select name="status" id="dashboard-order-status">
                            <option value="">All statuses</option>
                            <?php $__currentLoopData = \App\Enums\OrderStatus::cases(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $orderStatus): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($orderStatus->value); ?>" <?php if(request('status') === $orderStatus->value): echo 'selected'; endif; ?>><?php echo e(ucwords(strtolower(str_replace('_', ' ', $orderStatus->value)))); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <button type="submit" class="dash-btn-sm dash-btn-outline">Filter</button>
                    </form>
                </div>

                <?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="order-card u-extracted-dab43fb936">
                        <div class="order-card-header">
                            <div>
                                <a href="<?php echo e(route('buyer.orders.show', $order->id)); ?>"
                                    class="order-number"><?php echo e($order->order_number); ?></a>
                                <div class="order-date">Placed on <?php echo e($order->created_at->format('M d, Y h:i A')); ?> &bull;
                                    Store: <strong><?php echo e($order->seller->business_name ?? 'Merchant'); ?></strong></div>
                            </div>
                            <span class="status-pill status-<?php echo e(strtolower(str_replace('_', '-', $order->status))); ?>">
                                <?php echo e(str_replace('_', ' ', $order->status)); ?>

                            </span>
                        </div>

                        <div class="u-extracted-0e37a331af">
                            <?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="u-extracted-8ad17e2bf5">
                                    <div>
                                        <strong><?php echo e($item->product_name); ?></strong>
                                        <?php if($item->variation_info): ?>
                                            <span class="u-extracted-36eaa30988">(<?php echo e($item->variation_info); ?>)</span>
                                        <?php endif; ?>
                                        <span class="u-extracted-71b8babba9">&times;
                                            <?php echo e($item->quantity); ?></span>
                                    </div>
                                    <div>₱<?php echo e(number_format($item->item_total, 2)); ?></div>
                                </div>

                                <!-- Review Form for Completed Orders -->
                                <?php if($order->status === 'COMPLETED'): ?>
                                    <?php
                                        $alreadyReviewed = \App\Models\Review::where('order_id', $order->id)
                                            ->where('product_id', $item->product_id)
                                            ->first();
                                    ?>

                                    <?php if($alreadyReviewed): ?>
                                        <div class="u-extracted-930b6add1c">
                                            ★ Rated <?php echo e($alreadyReviewed->rating); ?>/5:
                                            "<?php echo e($alreadyReviewed->comment ?? 'No comment'); ?>"
                                        </div>
                                    <?php else: ?>
                                        <div class="review-modal-box">
                                            <form action="<?php echo e(route('buyer.orders.review', $order->id)); ?>" method="POST">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="product_id" value="<?php echo e($item->product_id); ?>">
                                                <div class="u-extracted-487b2c5804">
                                                    <select name="rating" class="review-form-select u-extracted-6f50c17793"
                                                        required>
                                                        <option value="5">★★★★★ (5)</option>
                                                        <option value="4">★★★★☆ (4)</option>
                                                        <option value="3">★★★☆☆ (3)</option>
                                                        <option value="2">★★☆☆☆ (2)</option>
                                                        <option value="1">★☆☆☆☆ (1)</option>
                                                    </select>
                                                    <input type="text" name="comment"
                                                        placeholder="Write feedback (optional)..."
                                                        class="review-form-select u-extracted-1c09f0c006">
                                                    <button type="submit"
                                                        class="dash-btn-sm dash-btn-primary u-extracted-1c09f0c006">
                                                        Rate
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>

                        <div class="order-card-footer">
                            <div>
                                Total Amount: <strong>₱<?php echo e(number_format($order->total_amount, 2)); ?></strong>
                                <span class="u-extracted-cf0c302441">(<?php echo e($order->payment_method); ?>)</span>
                            </div>

                            <div class="u-extracted-487b2c5804">
                                <?php if($order->dispute): ?>
                                    <span
                                        class="status-pill status-badge-<?php echo e(strtolower(str_replace('_', '-', $order->dispute->status))); ?>">
                                        Dispute: <?php echo e(str_replace('_', ' ', $order->dispute->status)); ?>

                                    </span>
                                <?php elseif(in_array($order->status, ['DELIVERED', 'COMPLETED'])): ?>
                                    <a href="<?php echo e(route('buyer.orders.dispute.create', $order->id)); ?>"
                                        class="dash-btn-sm u-extracted-8dddb4a915">
                                        <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> File Dispute
                                    </a>
                                <?php endif; ?>

                                <?php if($order->status === 'DELIVERED'): ?>
                                    <form action="<?php echo e(route('buyer.orders.confirm', $order->id)); ?>" method="POST" data-decision="buyer.orders.confirm" data-decision-title="Confirm receipt?" data-decision-message="Confirm only after you have received the parcel and checked your items. This records your receipt of the order." data-decision-label="Confirm receipt" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="dash-btn-sm dash-btn-success">
                                            Confirm Order Received
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if($order->pendingCancellation): ?>
                                    <a class="dash-btn-sm" href="<?php echo e(route('buyer.orders.show', $order)); ?>">Cancellation pending</a>
                                <?php elseif(in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING'], true)): ?>
                                    <form action="<?php echo e(route('buyer.orders.cancel', $order)); ?>" method="POST" data-decision="buyer.orders.cancel" data-decision-title="Request cancellation?" data-decision-message="The seller has 24 hours to respond before automatic approval. Cancellation is unavailable after physical pickup." data-decision-label="Request cancellation" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="dash-btn-sm">Request
                                            cancellation</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="dash-empty-box">You have not placed any orders yet.</div>
                <?php endif; ?>
                <div class="workspace-pagination"><?php echo e($orders->links()); ?></div>
            </div>
        </main>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/buyer/dashboard.blade.php ENDPATH**/ ?>