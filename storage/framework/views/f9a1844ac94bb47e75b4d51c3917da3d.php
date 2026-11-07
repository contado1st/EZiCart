<?php $__env->startSection('workspace'); ?>
    <header class="ops-heading">
        <div>
            <h1>Scan audit history</h1>
            <p>Parcel, storage location, and rider scans recorded for this hub. Real-time audit trail across intake, sorting, and dispatch.</p>
        </div>
    </header>

    <section class="ops-panel">
        <form class="ops-form" method="GET" action="<?php echo e(route('logistics.scan-history')); ?>" aria-label="Filter scan history">
            <div class="ops-field">
                <label for="station">Station</label>
                <select id="station" name="station">
                    <option value="">All stations</option>
                    <?php $__currentLoopData = ['intake', 'seller_pickup', 'seller_handover', 'seller_return_receipt', 'putaway', 'pick', 'cycle_count', 'dispatch_release', 'dispatch_batch_rider', 'dispatch_batch_parcel', 'dispatch_batch_release', 'delivery_start', 'return_to_seller', 'rider_exception_intake', 'failed_return_intake', 'parcel_label_reprint']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $station): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($station); ?>" <?php if(request('station') === $station): echo 'selected'; endif; ?>>
                            <?php echo e(str_replace('_', ' ', ucfirst($station))); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="ops-field">
                <label for="result">Result</label>
                <select id="result" name="result">
                    <option value="">All results</option>
                    <?php $__currentLoopData = ['accepted', 'rejected', 'correct', 'missing', 'unexpected']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $result): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($result); ?>" <?php if(request('result') === $result): echo 'selected'; endif; ?>><?php echo e(ucfirst($result)); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <button class="ops-btn ops-btn--primary" type="submit">Apply filters</button>
            <?php if(request()->filled('station') || request()->filled('result')): ?>
                <a class="ops-btn" href="<?php echo e(route('logistics.scan-history')); ?>">Clear filters</a>
            <?php endif; ?>
        </form>

        <?php if(request()->filled('station') || request()->filled('result')): ?>
            <ul class="ops-filter-chips" aria-label="Active filters">
                <?php if(request()->filled('station')): ?>
                    <li>Station: <strong><?php echo e(str_replace('_', ' ', ucfirst(request('station')))); ?></strong></li>
                <?php endif; ?>
                <?php if(request()->filled('result')): ?>
                    <li>Result: <strong><?php echo e(ucfirst(request('result'))); ?></strong></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="ops-panel">
        <h2>Recorded scans <span class="ops-muted"><?php echo e($scans->total()); ?> records</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Parcel</th>
                        <th>Station</th>
                        <th>Result</th>
                        <th>Scan method</th>
                        <th>Operator</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $scans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $scan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td data-label="Timestamp" class="ops-mono"><?php echo e($scan->created_at); ?></td>
                            <td data-label="Parcel" class="ops-mono">
                                <strong><?php echo e($scan->order_number ?? 'Unresolved reference'); ?></strong>
                            </td>
                            <td data-label="Station"><?php echo e(str_replace('_', ' ', ucfirst($scan->station))); ?></td>
                            <td data-label="Result">
                                <?php
                                    $resultClass = match (strtolower($scan->result)) {
                                        'accepted', 'correct' => 'ops-status--green',
                                        'rejected', 'missing' => 'ops-status--danger',
                                        'unexpected' => 'ops-status--amber',
                                        default => 'ops-status--pink',
                                    };
                                ?>
                                <span class="ops-status <?php echo e($resultClass); ?>"><?php echo e(ucfirst($scan->result)); ?></span>
                            </td>
                            <td data-label="Method"><?php echo e(ucfirst($scan->method)); ?></td>
                            <td data-label="Operator">
                                <?php echo e(trim(($scan->actor_first_name ?? '') . ' ' . ($scan->actor_last_name ?? '')) ?: 'Former operator'); ?>

                            </td>
                            <td data-label="Details"><?php echo e($scan->failure_reason ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="7">
                                <div class="ops-empty">No scan records match this hub and filter criteria.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="ops-pagination"><?php echo e($scans->links()); ?></div>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.logistics', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/logistics/scan-history.blade.php ENDPATH**/ ?>