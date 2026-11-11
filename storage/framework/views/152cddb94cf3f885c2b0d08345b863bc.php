<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="dash-wrapper">
        <!-- Sidebar -->
<!-- Main Content Area -->
        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Marketplace overview</h1>
                    <p class="dash-subtitle">System-wide monitoring of marketplace transactions, commission revenue, and
                        verifications.</p>
                </div>
                <div>
                    <a href="<?php echo e(route('admin.registrations.index')); ?>" class="dash-btn-primary">
                        Verify Users (<?php echo e($stats['pending_users']); ?>)
                    </a>
                    <a href="<?php echo e(route('admin.compliance.products.index')); ?>"
                        class="dash-btn-primary u-extracted-71c7258ecd">
                        Review Products (<?php echo e($stats['pending_product_reviews']); ?>)
                    </a>
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

            <!-- Financial & Logistics KPI Cards -->
            <section class="feature-panel"><h2>Platform activity</h2><div class="feature-grid">
                <?php $__currentLoopData = ['buyers' => 'Buyer accounts', 'sellers' => 'Approved sellers', 'products' => 'Approved listings', 'orders' => 'Orders placed', 'delivered' => 'Parcels delivered', 'failed_attempts' => 'Failed delivery attempts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <p><strong><?php echo e(number_format($stats[$key])); ?></strong><br><?php echo e($label); ?></p>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div></section>
            <div class="dash-stats-grid">
                <div class="dash-stat-card">
                    <span class="workspace-stat-icon" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'money']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'money']); ?>
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
                    <div class="dash-stat-label">Verified gross commission</div>
                    <div class="dash-stat-value success">₱<?php echo e(number_format($stats['total_commission'], 2)); ?></div>
                    <div class="dash-stat-subtext success">Completed orders with verified remittances, before promotion costs</div>
                </div>

                <div class="dash-stat-card">
                    <span class="workspace-stat-icon" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'cart1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'cart1']); ?>
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
                    <div class="dash-stat-label">Gross Merchandise Value</div>
                    <div class="dash-stat-value primary">₱<?php echo e(number_format($stats['gmv'], 2)); ?></div>
                    <div class="dash-stat-subtext">Total transaction volume</div>
                </div>

                <div class="dash-stat-card">
                    <span class="workspace-stat-icon" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'shield']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'shield']); ?>
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
                    <div class="dash-stat-label">Pending Verifications</div>
                    <div class="dash-stat-value warning"><?php echo e($stats['pending_users']); ?></div>
                    <div class="dash-stat-subtext">Sellers and couriers awaiting review</div>
                </div>

                <a href="<?php echo e(route('admin.compliance.products.index')); ?>" class="dash-stat-card">
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
                    <div class="dash-stat-label">Product Reviews</div>
                    <div class="dash-stat-value warning"><?php echo e($stats['pending_product_reviews']); ?></div>
                    <div class="dash-stat-subtext">New listings awaiting category and compliance review</div>
                </a>

                <div class="dash-stat-card">
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
                    <div class="dash-stat-label">Parcels in Transit</div>
                    <div class="dash-stat-value"><?php echo e($stats['active_parcels']); ?></div>
                    <div class="dash-stat-subtext">Active courier deliveries</div>
                </div>
            </div>

            <!-- Workflow Grid -->
            <div class="dash-workflow-grid">
                <!-- Left: System Transactions -->
                <div class="dash-panel">
                    <h3 class="dash-panel-title">Recent Platform Orders</h3>

                    <div class="dash-table-wrapper">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Store</th>
                                    <th>Buyer</th>
                                    <th>Total</th>
                                    <th>Commission</th>
                                    <th>Status</th>
                                    <th>Order action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__empty_1 = true; $__currentLoopData = $recentOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr>
                                        <td class="dash-text-bold"><?php echo e($order->order_number); ?><div><a
                                                    href="<?php echo e(route('admin.orders.messages.show', $order)); ?>">Open order
                                                    conversation</a></div>
                                        </td>
                                        <td><?php echo e($order->seller->business_name ?? 'Store'); ?></td>
                                        <td><?php echo e($order->recipient_name); ?></td>
                                        <td class="dash-text-bold">₱<?php echo e(number_format($order->total_amount, 2)); ?></td>
                                        <td class="dash-text-success-bold">₱<?php echo e(number_format($order->commission_fee, 2)); ?>

                                        </td>
                                        <td>
                                            <span
                                                class="status-pill status-<?php echo e(strtolower(str_replace('_', '-', $order->status))); ?>">
                                                <?php echo e(str_replace('_', ' ', $order->status)); ?>

                                            </span>
                                        </td>
                                        <td>
                                            <?php if(in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true) &&
                                                    $order->picked_up_at === null &&
                                                    $order->seller_handover_at === null): ?>
                                                <form action="<?php echo e(route('admin.orders.cancel', $order)); ?>" method="POST" data-decision="admin.orders.cancel" data-decision-title="Cancel this order?" data-decision-message="Check the order and reason carefully before recording an administrative cancellation." data-decision-label="Cancel order" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                    <?php echo csrf_field(); ?>
                                                    <label class="sr-only"
                                                        for="admin-cancel-reason-<?php echo e($order->id); ?>">Cancellation reason
                                                        for <?php echo e($order->order_number); ?></label>
                                                    <input id="admin-cancel-reason-<?php echo e($order->id); ?>" name="reason"
                                                        maxlength="500" required placeholder="Reason for cancellation">
                                                    <button type="submit"
                                                        class="dash-btn-sm dash-btn-outline">Cancel</button>
                                                </form>
                                            <?php else: ?>
                                                <span>—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="7" class="dash-table-empty">
                                            No transactions recorded yet.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right: Quick Verification Queue -->
                <div class="dash-panel">
                    <?php if (isset($component)) { $__componentOriginal1acbf46dd719838062ec78de64fc1ccc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1acbf46dd719838062ec78de64fc1ccc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workspace-distribution','data' => ['values' => $recentOrders->countBy('status')->all(),'label' => 'Orders by status','description' => 'Status mix of the latest platform orders.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workspace-distribution'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['values' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($recentOrders->countBy('status')->all()),'label' => 'Orders by status','description' => 'Status mix of the latest platform orders.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1acbf46dd719838062ec78de64fc1ccc)): ?>
<?php $attributes = $__attributesOriginal1acbf46dd719838062ec78de64fc1ccc; ?>
<?php unset($__attributesOriginal1acbf46dd719838062ec78de64fc1ccc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1acbf46dd719838062ec78de64fc1ccc)): ?>
<?php $component = $__componentOriginal1acbf46dd719838062ec78de64fc1ccc; ?>
<?php unset($__componentOriginal1acbf46dd719838062ec78de64fc1ccc); ?>
<?php endif; ?>
                    <div class="dash-panel-header-row">
                        <h3 class="dash-panel-title dash-panel-title-compact">Pending Approvals</h3>
                        <a href="<?php echo e(route('admin.registrations.index')); ?>" class="dash-action-link">View All →</a>
                    </div>

                    <?php $__empty_1 = true; $__currentLoopData = $pendingUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pUser): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="dash-approval-card">
                            <div class="dash-approval-row">
                                <div>
                                    <div class="dash-approval-name"><?php echo e($pUser->first_name); ?> <?php echo e($pUser->last_name); ?></div>
                                    <div class="dash-approval-email"><?php echo e($pUser->email); ?></div>
                                    <span
                                        class="dash-badge dash-badge-category dash-approval-badge"><?php echo e(ucfirst($pUser->role)); ?></span>
                                </div>
                                <div class="dash-approval-actions">
                                    <form action="<?php echo e(route('admin.registrations.approve', $pUser->id)); ?>" method="POST" data-decision="admin.registrations.approve" data-decision-title="Approve this account?" data-decision-message="Confirm that you reviewed the submitted credentials and that this account meets the approval requirements." data-decision-label="Approve account" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="dash-btn-sm dash-btn-success">Approve</button>
                                    </form>
                                    <form action="<?php echo e(route('admin.registrations.reject', $pUser->id)); ?>" method="POST" data-decision="admin.registrations.reject" data-decision-title="Reject this application?" data-decision-message="Check the rejection reason before recording your decision." data-decision-label="Reject application" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="dash-btn-sm dash-btn-danger">Reject</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="dash-empty-box">
                            <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> All user registration requests have been addressed.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>