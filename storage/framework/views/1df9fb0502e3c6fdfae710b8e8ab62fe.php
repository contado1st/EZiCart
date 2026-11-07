<?php $__env->startSection('title', $report['title'].' | EZiCart'); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/analytics.css')); ?>?v=<?php echo e(filemtime(public_path('css/analytics.css'))); ?>">
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<?php
    $formatMetric = static fn ($value, $unit) => $value === null ? 'No data' : ($unit === 'money' ? '₱'.number_format($value, 2) : number_format($value, $unit === 'hours' ? 2 : 0).($unit === 'hours' ? ' hrs' : ''));
    $chartData = ['trend' => $report['trend'], 'statuses' => $report['statuses'], 'commercial' => $report['commercial'], 'groups' => collect($report['sections'])->whereIn('key', ['products', 'categories', 'sellers', 'locations'])->map(fn ($section) => ['key' => $section['key'], 'title' => $section['title'], 'rows' => array_slice($section['rows'], 0, 10)])->values()->all()];
?>
<div class="analytics-page">
    <div class="analytics-print-brand">EZiCart · Analytics report</div>
    <header class="analytics-heading"><div><h1><?php echo e($report['title']); ?></h1><p><?php echo e($report['filters']['from']); ?> — <?php echo e($report['filters']['to']); ?> · <?php echo e($report['timezone']); ?> · PHP</p></div>
        <div class="analytics-actions"><button type="button" data-analytics-print><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> Print charts</button>
        <?php $__currentLoopData = ['xlsx' => 'Excel workbook', 'csv' => 'CSV', 'pdf' => 'Full PDF report']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $extension => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('analytics.export', [...$report['filters'], 'format' => $extension])); ?>"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'note']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'note']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> <?php echo e($label); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
    </header>
    <p class="analytics-print-scope">Chart summary · <?php echo e(\Illuminate\Support\Str::headline($report['role'])); ?> workspace. <?php $__currentLoopData = collect($report['filters'])->except(['from', 'to', 'comparison', 'interval'])->filter(fn ($value) => $value !== null && $value !== ''); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo e(\Illuminate\Support\Str::headline($key)); ?>: <?php echo e($value); ?>; <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> Full source records are included in the Excel, CSV and PDF downloads.</p>
    <?php echo $__env->make('components.feature-feedback', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <form class="analytics-filters" method="GET" action="<?php echo e(route('analytics.index')); ?>" data-analytics-filter>
        <div class="analytics-filter-main">
            <label>From<input type="date" name="from" value="<?php echo e($report['filters']['from']); ?>" required></label>
            <label>To<input type="date" name="to" value="<?php echo e($report['filters']['to']); ?>" required></label>
            <label>Group trend by<select name="interval"><?php $__currentLoopData = ['day', 'week', 'month', 'quarter', 'year']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $interval): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($interval); ?>" <?php if($report['filters']['interval'] === $interval): echo 'selected'; endif; ?>><?php echo e(ucfirst($interval)); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
            <label>Compare with<select name="comparison"><?php $__currentLoopData = ['previous' => 'Previous equal period', 'year' => 'Same dates last year', 'none' => 'No comparison']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if($report['filters']['comparison'] === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
            <button class="analytics-primary" type="submit">Apply filters <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'search']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></button><a href="<?php echo e(route('analytics.index')); ?>">Reset</a>
        </div>
        <details class="analytics-advanced" <?php if(collect($report['filters'])->except(['from','to','interval','comparison'])->filter()->isNotEmpty()): ?> open <?php endif; ?>><summary>More filters</summary><div class="analytics-filter-main">
            <?php if(auth()->user()->isCommerceAdmin()): ?><label>Seller ID<input type="number" name="seller_id" min="1" value="<?php echo e($report['filters']['seller_id'] ?? ''); ?>"></label><?php endif; ?>
            <?php if(auth()->user()->isCommerceAdmin() || auth()->user()->isLogisticsAdmin() || auth()->user()->role === 'sorting_center'): ?><label>Rider ID<input type="number" name="rider_id" min="1" value="<?php echo e($report['filters']['rider_id'] ?? ''); ?>"></label><?php endif; ?>
            <label>Product ID<input type="number" name="product_id" min="1" value="<?php echo e($report['filters']['product_id'] ?? ''); ?>"></label>
            <label>Category<input name="category" maxlength="120" value="<?php echo e($report['filters']['category'] ?? ''); ?>"></label>
            <label>Order status<select name="status"><option value="">All statuses</option><?php $__currentLoopData = \App\Enums\OrderStatus::cases(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($status->value); ?>" <?php if(($report['filters']['status'] ?? '') === $status->value): echo 'selected'; endif; ?>><?php echo e(\Illuminate\Support\Str::headline(strtolower($status->value))); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
            <label>Municipality<input name="municipality" maxlength="120" value="<?php echo e($report['filters']['municipality'] ?? ''); ?>"></label>
            <label>Barangay<input name="barangay" maxlength="120" value="<?php echo e($report['filters']['barangay'] ?? ''); ?>"></label>
            <label>Payment record<select name="payment_status"><option value="">All payment records</option><?php $__currentLoopData = ['verified' => 'Remittance verified', 'collected' => 'COD collected', 'uncollected' => 'No recorded collection', 'refunded' => 'Executed refund']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if(($report['filters']['payment_status'] ?? '') === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
            <label>Payment method<input name="payment_method" maxlength="30" placeholder="e.g. COD" value="<?php echo e($report['filters']['payment_method'] ?? ''); ?>"></label>
            <label>Voucher code<input name="voucher" maxlength="100" value="<?php echo e($report['filters']['voucher'] ?? ''); ?>"></label>
        </div></details>
    </form>
    <p class="analytics-freshness">Updated <?php echo e($report['generated_at']); ?>. Figures use orders placed in the selected period and their current recorded state. <?php if($report['comparison']): ?>Comparison: <?php echo e($report['comparison']['from']); ?> — <?php echo e($report['comparison']['to']); ?>.<?php endif; ?></p>
    <section class="analytics-metrics" aria-label="Reporting period metrics">
        <?php $__currentLoopData = $report['metrics']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $metric): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><article><h2><?php echo e($metric['label']); ?></h2><strong><?php echo e($formatMetric($metric['value'], $metric['unit'])); ?></strong>
            <?php if($report['comparison']): ?><p><?php if($metric['change'] !== null): ?><span><?php echo e($metric['change'] > 0 ? '+' : ''); ?><?php echo e($metric['change']); ?>%</span> vs comparison <?php else: ?> No percentage baseline <?php endif; ?></p><small>Previous: <?php echo e($formatMetric($metric['previous'], $metric['unit'])); ?></small><?php endif; ?>
        </article><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </section>
    <div data-analytics-charts><div data-skeleton-chart-loading hidden><p class="analytics-chart-loading" role="status">Loading interactive charts. All figures are available in the tables below.</p><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'charts']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'charts']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?></div><noscript><p class="analytics-chart-loading">Interactive charts require JavaScript. All figures are available in the tables below.</p></noscript></div>
    <script type="application/json" id="analytics-chart-data"><?php echo json_encode($chartData, 15, 512) ?></script>
    <?php if(count($report['insights'])): ?><section class="analytics-observations"><h2>Observations to review</h2><?php $__currentLoopData = $report['insights']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $insight): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><strong><?php echo e($insight['Metric']); ?>:</strong> <?php echo e($insight['Observation']); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></section><?php endif; ?>
    <section class="analytics-section analytics-detail-section"><header><h2>Trend data</h2><p>Weekly and quarterly labels identify the group containing each selected date.</p></header><div class="analytics-table-scroll"><table><thead><tr><th scope="col">Period</th><th scope="col">Orders</th><th scope="col">Completed</th><th scope="col">Recorded delivery</th><?php if($report['commercial']): ?><th scope="col">Net seller sales PHP</th><?php endif; ?></tr></thead><tbody><?php $__currentLoopData = $report['trend']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $point): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($point['period']); ?></td><td><?php echo e($point['orders']); ?></td><td><?php echo e($point['completed']); ?></td><td><?php echo e($point['delivered']); ?></td><?php if($report['commercial']): ?><td><?php echo e(number_format($point['net'], 2)); ?></td><?php endif; ?></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div></section>
    <?php $__currentLoopData = $report['sections']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <section class="analytics-section analytics-detail-section" id="analytics-<?php echo e($section['key']); ?>"><header><h2><?php echo e($section['title']); ?></h2><p><?php echo e(number_format(count($section['rows']))); ?> <?php echo e($section['key'] === 'orders' ? 'orders in this report' : 'rows'); ?>. <?php if($section['key'] !== 'orders' && count($section['rows']) > 20): ?>Showing the first 20; exports include every row.<?php endif; ?></p></header>
            <div class="analytics-table-scroll"><?php if(count($section['rows'])): ?>
            <table><thead><tr><?php $__currentLoopData = array_keys($section['rows'][0]); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $heading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><th scope="col"><?php echo e($heading); ?></th><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tr></thead><tbody>
            <?php $__currentLoopData = $section['key'] === 'orders' ? $orders : array_slice($section['rows'], 0, 20); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><?php $__currentLoopData = $row; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $heading => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><td><?php echo e(is_float($value) ? number_format($value, 2) : $value); ?></td><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody></table><?php else: ?><p class="analytics-empty">No matching records for this section.</p><?php endif; ?></div>
            <?php if($section['key'] === 'orders'): ?><?php echo e($orders->links()); ?><?php endif; ?>
        </section>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <section class="analytics-definitions analytics-detail-section"><h2>Definitions and data coverage</h2><ul><?php $__currentLoopData = $report['notes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $note): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($note); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul><p>Exports contain all permitted source rows, comparisons, filters and definitions. “Print charts” prints the chart summary; use the full PDF or Excel report for all rows.</p></section>
    <p class="analytics-print-scope">Reporting basis: orders placed during the selected period, using their latest recorded state. Net seller sales is not profit. Unavailable metrics are not estimated.</p>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/analytics/index.blade.php ENDPATH**/ ?>