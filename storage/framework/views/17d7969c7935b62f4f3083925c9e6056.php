<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/reports.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="dash-wrapper">
        <!-- Sidebar -->
<!-- Main Workspace -->
        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Platform Commission & Sales Summary</h1>
                    <a class="dash-btn-sm dash-btn-primary" href="<?php echo e(route('sales-reports.index')); ?>">Itemized reports, CSV and PDF</a>
                    <p class="dash-subtitle">Review completed order values, verified cash receipts, and recorded external settlements.</p>
                </div>
            </div>

            <!-- Date Range Filter Bar -->
            <div class="report-filter-bar">
                <form action="<?php echo e(route('admin.reports.index')); ?>" method="GET" class="report-filter-form">
                    <div class="report-filter-group">
                        <label class="report-filter-label">From:</label>
                        <input type="date" name="from_date" value="<?php echo e($fromDate->format('Y-m-d')); ?>"
                            class="report-date-input" required>
                    </div>
                    <div class="report-filter-group">
                        <label class="report-filter-label">To:</label>
                        <input type="date" name="to_date" value="<?php echo e($toDate->format('Y-m-d')); ?>"
                            class="report-date-input" required>
                    </div>
                    <button type="submit" class="dash-btn-sm dash-btn-primary">Generate Report</button>
                </form>

                <div class="report-actions-group">
                    <button onclick="window.print()" class="report-print-btn"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> Print Statement</button>
                </div>
            </div>

            <!-- Metric Summary Cards -->
            <div class="report-summary-banner">
                <div class="report-summary-card">
                    <div class="report-summary-title">Completed order value</div>
                    <div class="report-summary-value">₱<?php echo e(number_format($stats['total_gmv'], 2)); ?></div>
                </div>

                <div class="report-summary-card">
                    <div class="report-summary-title">Product value before discounts</div>
                    <div class="report-summary-value">₱<?php echo e(number_format($stats['product_sales'], 2)); ?></div>
                </div>

                <div class="report-summary-card">
                    <div class="report-summary-title">Verified gross commission</div>
                    <div class="report-summary-value report-summary-profit">
                        ₱<?php echo e(number_format($stats['total_commission'], 2)); ?></div>
                </div>

                <div class="report-summary-card">
                    <div class="report-summary-title">Settlement Rate</div>
                    <div class="report-summary-value"><?php echo e($stats['completed_count']); ?> / <?php echo e($stats['total_orders']); ?></div>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="dash-panel report-table-section">
                <p>Verified remittances: ₱<?php echo e(number_format($stats['verified_remittances'], 2)); ?> · Executed refunds: ₱<?php echo e(number_format($stats['executed_refunds'], 2)); ?> · Settled payouts: ₱<?php echo e(number_format($stats['settled_payouts'], 2)); ?> · Funded platform promotions: ₱<?php echo e(number_format($stats['funded_promotions'], 2)); ?>.</p>
                <p>Gross commission reflects currently completed, non-refunded orders with remittances verified during the selected dates. Promotion funding is an additional platform cost.</p>
                <h2 class="report-table-title">Booked order commissions (<?php echo e($completedOrders->total()); ?>)</h2>

                <div class="dash-table-wrapper">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Order No.</th>
                                <th>Merchant</th>
                                <th>Buyer</th>
                                <th>Gross Total</th>
                                <th>Discounts</th>
                                <th>10% Commission</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $completedOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td><strong><?php echo e($order->order_number); ?></strong></td>
                                    <td><?php echo e($order->seller->business_name ?? 'Merchant'); ?></td>
                                    <td><?php echo e($order->buyer->first_name); ?> <?php echo e($order->buyer->last_name); ?></td>
                                    <td>₱<?php echo e(number_format($order->total_amount, 2)); ?></td>
                                    <td>₱<?php echo e(number_format($order->discount_amount, 2)); ?></td>
                                    <td><strong
                                            class="report-summary-profit">₱<?php echo e(number_format($order->commission_fee, 2)); ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="6" class="dash-table-empty">No transactions found for the specified
                                        period.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php echo e($completedOrders->links()); ?>

                </div>
            </div>
        </main>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/admin/reports/index.blade.php ENDPATH**/ ?>