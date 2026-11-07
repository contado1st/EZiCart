<?php $__env->startSection('workspace'); ?>
    <header class="ops-heading">
        <div>
            <h1>Parcel throughput</h1>
            <p>Counts and throughput are derived from parcel order lifecycle timestamps within the selected reporting window.</p>
        </div>
    </header>

    <section class="ops-panel">
        <form class="ops-form" method="GET" action="<?php echo e(route('logistics.reports')); ?>" aria-label="Select report period">
            <div class="ops-field">
                <label for="report-from">From date</label>
                <input id="report-from" type="date" name="from" value="<?php echo e($from->toDateString()); ?>">
            </div>
            <div class="ops-field">
                <label for="report-to">To date</label>
                <input id="report-to" type="date" name="to" value="<?php echo e($to->toDateString()); ?>">
            </div>
            <button class="ops-btn ops-btn--primary" type="submit">Update report</button>
        </form>
    </section>

    
    <section class="ops-stats ops-stats--priority" aria-label="Key operational indicators">
        <div class="ops-stat">
            <span>Received</span>
            <strong><?php echo e($stats['received']); ?></strong>
        </div>
        <div class="ops-stat ops-stat--action">
            <span>Sorted</span>
            <strong><?php echo e($stats['sorted']); ?></strong>
        </div>
        <div class="ops-stat ops-stat--action">
            <span>Dispatched</span>
            <strong><?php echo e($stats['dispatched']); ?></strong>
        </div>
        <div class="ops-stat ops-stat--action">
            <span>Delivered</span>
            <strong><?php echo e($stats['delivered']); ?></strong>
        </div>
        <div class="ops-stat ops-stat--danger">
            <span>Failed attempts</span>
            <strong><?php echo e($stats['failed']); ?></strong>
        </div>
    </section>

    <section class="ops-grid">
        <div class="ops-panel">
            <h2>Orders placed by day</h2>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $volume; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td data-label="Date" class="ops-mono"><?php echo e($day->day); ?></td>
                                <td data-label="Orders"><strong><?php echo e($day->total); ?></strong></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="2">
                                    <div class="ops-empty">No order volume recorded for this period.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ops-panel">
            <h2>Parcel count by destination area</h2>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Routing area</th>
                            <th>Parcels</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $areaCounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $area): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td data-label="Area">
                                    <strong><?php echo e($area->destinationArea?->name ?? 'Unassigned area'); ?></strong>
                                </td>
                                <td data-label="Parcels"><strong><?php echo e($area->total); ?></strong></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="2">
                                    <div class="ops-empty">No sorted parcels for this period.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="ops-panel">
        <div class="ops-banner">
            <div>
                <h2>Current operational backlog</h2>
                <p>
                    <strong><?php echo e($stats['backlog']); ?></strong> parcels are currently in pickup, hub, sorting, or delivery queues.
                    Backlog is a live snapshot of items requiring hub action; date selection does not alter this active count.
                </p>
            </div>
            <a class="ops-btn ops-btn--primary" href="<?php echo e(route('logistics.dashboard')); ?>">Open triage board</a>
        </div>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.logistics', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/logistics/reports.blade.php ENDPATH**/ ?>