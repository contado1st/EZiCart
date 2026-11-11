<?php $__env->startSection('workspace'); ?>
    <header class="ops-heading">
        <div>
            <h1>Routing areas</h1>
            <p>Create operational zones, then map each province and municipality to its delivery zone. Multiple municipalities can use one area.</p>
        </div>
    </header>

    <section class="ops-grid">
        <div class="ops-panel">
            <h2>Create a routing area</h2>
            <form class="ops-form" method="POST" action="<?php echo e(route('logistics.areas.store')); ?>">
                <?php echo csrf_field(); ?>
                <div class="ops-field">
                    <label for="area-name">Area name</label>
                    <input id="area-name" name="name" value="<?php echo e(old('name')); ?>" maxlength="255" required placeholder="e.g. North Sector">
                </div>
                <div class="ops-field">
                    <label for="area-code">Area code</label>
                    <input id="area-code" name="code" value="<?php echo e(old('code')); ?>" maxlength="255" pattern="[A-Za-z0-9_-]+" required placeholder="e.g. north-sector">
                </div>
                <button class="ops-btn ops-btn--primary" type="submit">Create area</button>
            </form>
        </div>

        <div class="ops-panel">
            <h2>Map a municipality</h2>
            <form class="ops-form" method="POST" action="<?php echo e(route('logistics.areas.municipalities.store')); ?>">
                <?php echo csrf_field(); ?>
                <div class="ops-field">
                    <label for="mapping-area">Routing area</label>
                    <select id="mapping-area" name="area_id" required>
                        <option value="">Choose an area</option>
                        <?php $__currentLoopData = $areas->where('is_active', true); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $area): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($area->id); ?>" <?php if(old('area_id') == $area->id): echo 'selected'; endif; ?>>
                                <?php echo e($area->name); ?> (<?php echo e($area->code); ?>)
                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="ops-field">
                    <label for="province">Province</label>
                    <input id="province" name="province" value="<?php echo e(old('province')); ?>" maxlength="191" required placeholder="e.g. Laguna">
                </div>
                <div class="ops-field">
                    <label for="municipality">City / municipality</label>
                    <input id="municipality" name="municipality" value="<?php echo e(old('municipality')); ?>" maxlength="191" required placeholder="e.g. Santa Cruz">
                </div>
                <button class="ops-btn ops-btn--primary" type="submit">Save mapping</button>
            </form>
        </div>
    </section>

    <section class="ops-panel">
        <h2>Configured areas <span class="ops-muted"><?php echo e($areas->count()); ?> areas</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Area / Hub status</th>
                        <th>Mapped municipalities</th>
                        <th>Approved riders</th>
                        <th>Active parcels</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $areas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $area): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td data-label="Area">
                                <strong><?php echo e($area->name); ?></strong>
                                <div class="ops-mono"><?php echo e($area->code); ?></div>
                                <?php if($area->sorting_center_id === null): ?>
                                    <form method="POST" action="<?php echo e(route('logistics.areas.claim', $area)); ?>" data-decision="logistics.areas.claim" data-decision-title="Claim this service area?" data-decision-message="This associates the selected service area with your sorting center." data-decision-label="Claim service area" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        <?php echo csrf_field(); ?>
                                        <button class="ops-btn" type="submit">Assign to this hub</button>
                                    </form>
                                    <span class="ops-status ops-status--amber">Unassigned legacy area</span>
                                <?php else: ?>
                                    <span class="ops-status ops-status--green">Assigned to this hub</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Municipalities">
                                <?php $__empty_2 = true; $__currentLoopData = $area->municipalities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mapping): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                                    <div class="ops-area-mapping">
                                        <span><strong><?php echo e($mapping->municipality); ?>, <?php echo e($mapping->province); ?></strong></span>
                                        <form class="ops-form" method="POST" action="<?php echo e(route('logistics.areas.municipalities.update', $mapping)); ?>">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('PATCH'); ?>
                                            <label for="mapping-<?php echo e($mapping->id); ?>">Change area</label>
                                            <select id="mapping-<?php echo e($mapping->id); ?>" name="area_id" required>
                                                <?php $__currentLoopData = $areas->where('is_active', true); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $candidate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <option value="<?php echo e($candidate->id); ?>" <?php if($candidate->id === $area->id): echo 'selected'; endif; ?>>
                                                        <?php echo e($candidate->name); ?>

                                                    </option>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </select>
                                            <button class="ops-btn" type="submit">Update</button>
                                        </form>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                                    <span class="ops-muted">No municipalities mapped yet.</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Riders">
                                <span class="logistics-workload-tag <?php echo e($area->riders_count > 0 ? 'logistics-workload-tag--light' : 'logistics-workload-tag--full'); ?>">
                                    <?php echo e($area->riders_count); ?> rider(s)
                                </span>
                            </td>
                            <td data-label="Parcels">
                                <span class="ops-mono"><?php echo e($area->orders_count); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="4">
                                <div class="ops-empty">No routing areas configured yet.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.logistics', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/logistics/areas.blade.php ENDPATH**/ ?>