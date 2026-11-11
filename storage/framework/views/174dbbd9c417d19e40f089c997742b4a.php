<?php $__env->startSection('workspace'); ?>
    <header class="ops-heading">
        <div>
            <h1>Rider roster</h1>
            <p>Review rider applications, manage hub area assignments, and maintain active delivery eligibility.</p>
        </div>
        <div class="courier-actions">
            <span class="ops-status ops-status--pink"><?php echo e($riders->total()); ?> riders</span>
        </div>
    </header>

    <?php if(session('success')): ?>
        <div class="ops-alert ops-alert--success" role="status"><?php echo e(session('success')); ?></div>
    <?php endif; ?>
    <?php if($errors->any()): ?>
        <div class="ops-alert ops-alert--error" role="alert"><?php echo e($errors->first()); ?></div>
    <?php endif; ?>

    <details class="ops-panel">
        <summary><strong>Add a rider</strong><span class="ops-muted">Create a roster entry and email a one-use activation link.</span></summary>
        <form method="POST" action="<?php echo e(route('logistics.riders.store')); ?>" enctype="multipart/form-data" class="ops-rider-form">
            <?php echo csrf_field(); ?>
            <div><label for="rider-first-name">First name</label><input id="rider-first-name" name="first_name" value="<?php echo e(old('first_name')); ?>" required minlength="2" pattern="[\p{L}\p{M} .\x27-]{2,50}" title="Use 2 to 50 letters, spaces, apostrophes, periods, or hyphens."></div>
            <div><label for="rider-last-name">Last name</label><input id="rider-last-name" name="last_name" value="<?php echo e(old('last_name')); ?>" required minlength="2" pattern="[\p{L}\p{M} .\x27-]{2,50}" title="Use 2 to 50 letters, spaces, apostrophes, periods, or hyphens."></div>
            <div><label for="rider-birthday">Date of birth</label><input id="rider-birthday" type="date" name="birthday" value="<?php echo e(old('birthday')); ?>" max="<?php echo e(now()->subYearsNoOverflow(18)->toDateString()); ?>" required></div>
            <div><label for="rider-email">Email</label><input id="rider-email" type="email" name="email" value="<?php echo e(old('email')); ?>" required autocomplete="email" maxlength="255"></div>
            <div><label for="rider-mobile">Mobile number</label><input id="rider-mobile" type="tel" name="contact_no" value="<?php echo e(old('contact_no')); ?>" placeholder="09XXXXXXXXX or +639XXXXXXXXX" autocomplete="tel" inputmode="tel" maxlength="16" pattern="(?:09\d{9}|09\d{2} ?\d{3} ?\d{4}|\+639\d{9}|\+63 ?9\d{2} ?\d{3} ?\d{4})" title="Enter a valid PH mobile number: 11 digits in 09XXXXXXXXX format or the equivalent +63 format." data-philippine-phone required><small class="ops-muted">Enter exactly 11 digits: 09XXXXXXXXX or its +63 equivalent.</small></div>
            <div><label for="rider-license-number">Driver’s license number</label><input id="rider-license-number" name="license_number" value="<?php echo e(old('license_number')); ?>" placeholder="N01-23-456789" pattern="[A-Za-z][0-9]{2}-[0-9]{2}-[0-9]{6}" minlength="13" maxlength="13" title="Enter one letter followed by 2-2-6 digits, for example N01-23-456789." required></div>
            <div><label for="rider-license-expiry">License expiry date</label><input id="rider-license-expiry" type="date" name="license_expires_at" value="<?php echo e(old('license_expires_at')); ?>" min="<?php echo e(now()->toDateString()); ?>" required></div>
            <div><label for="rider-vehicle">Vehicle type</label><select id="rider-vehicle" name="vehicle_type" required><option value="">Choose vehicle</option><?php $__currentLoopData = array_keys(config('rider-vehicles.types')); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vehicle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($vehicle); ?>" <?php if(old('vehicle_type') === $vehicle): echo 'selected'; endif; ?>><?php echo e($vehicle); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
            <div><label for="rider-plate">Plate number</label><input id="rider-plate" name="plate_number" value="<?php echo e(old('plate_number')); ?>" required minlength="2" maxlength="20"></div>
            <div><label for="rider-license-file">Driver’s license document</label><input id="rider-license-file" type="file" name="license_document" accept=".jpg,.jpeg,.png,.pdf" required></div>
            <div><label for="rider-orcr-file">Vehicle OR/CR</label><input id="rider-orcr-file" type="file" name="or_cr_document" accept=".jpg,.jpeg,.png,.pdf" required></div>
            <p class="ops-muted">Documents must be JPG, PNG, or PDF and no larger than 5 MB. The rider will set their own password from the activation email.</p>
            <button class="ops-btn ops-btn--primary" type="submit">Add rider and send activation</button>
        </form>
    </details>

    <section class="ops-panel">
        <div class="ops-heading">
            <div>
                <h2>Roster records</h2>
                <p class="ops-muted">Riders must be assigned to at least one hub service area to view full profiles and verify documents.</p>
            </div>
        </div>

        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Rider & badge</th>
                        <th>Contact & vehicle</th>
                        <th>Service areas</th>
                        <th>Workload</th>
                        <th>Status</th>
                        <th>Verification docs</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $riders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td>
                                <strong><?php echo e($rider->first_name); ?> <?php echo e($rider->last_name); ?></strong>
                                <?php if($rider->assigned_to_hub): ?>
                                    <div class="ops-muted"><?php echo e($rider->email); ?></div>
                                    <div class="ops-mono">EZR:<?php echo e($rider->badge_code); ?></div>
                                    <div class="logistics-badge-qr" role="img" aria-label="QR badge for <?php echo e($rider->first_name); ?>">
                                        <?php echo $rider->badge_qr; ?>

                                    </div>
                                <?php else: ?>
                                    <div class="ops-muted">Unassigned rider candidate</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($rider->assigned_to_hub): ?>
                                    <div><strong><?php echo e($rider->contact_no); ?></strong></div>
                                    <div class="ops-muted">
                                        <?php echo e($rider->vehicle_type ?? 'Vehicle not set'); ?> · <?php echo e($rider->plate_number ?? 'No plate'); ?>

                                    </div>
                                <?php else: ?>
                                    <span class="ops-muted">Assign to a hub area to view contact and vehicle details.</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="<?php echo e(route('logistics.riders.areas.update', $rider)); ?>" data-decision="logistics.riders.areas.update" data-decision-title="Change rider service areas?" data-decision-message="Check the selected areas before updating the rider coverage." data-decision-label="Save service areas" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PATCH'); ?>
                                    <label class="sr-only" for="rider-areas-<?php echo e($rider->id); ?>">Service areas</label>
                                    <select id="rider-areas-<?php echo e($rider->id); ?>" name="area_ids[]" multiple>
                                        <?php $__currentLoopData = $areas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $area): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($area->id); ?>" <?php if($rider->serviceAreas->contains('id', $area->id)): echo 'selected'; endif; ?>>
                                                <?php echo e($area->name); ?> (<?php echo e($area->code); ?>)
                                            </option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                    <button class="ops-btn" type="submit">Save areas</button>
                                </form>
                            </td>
                            <td>
                                <?php if($rider->assigned_to_hub): ?>
                                    <strong><?php echo e($rider->active_deliveries_count); ?> active</strong>
                                    <div class="ops-muted">
                                        <?php echo e($rider->completed_deliveries_count); ?> delivered · <?php echo e($rider->failed_deliveries_count); ?> failed
                                    </div>
                                <?php else: ?>
                                    <span class="ops-muted">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="ops-status <?php echo e($rider->status === 'approved' ? 'ops-status--green' : ($rider->status === 'pending' ? 'ops-status--amber' : 'ops-status--danger')); ?>">
                                    <?php echo e($rider->status === 'pending_activation' ? 'Pending Activation' : ucfirst($rider->status)); ?>

                                </span>
                            </td>
                            <td>
                                <?php if($rider->assigned_to_hub): ?>
                                    <div class="courier-actions">
                                        <?php $__currentLoopData = ['identity' => 'ID', 'license' => 'License', 'vehicle' => 'OR/CR']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php if(
                                                ($type === 'identity' && $rider->id_path) ||
                                                ($type === 'license' && $rider->license_path) ||
                                                ($type === 'vehicle' && $rider->or_cr_path)): ?>
                                                <a class="logistics-doc-chip" href="<?php echo e(route('logistics.riders.documents.show', [$rider, $type])); ?>">
                                                    <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> <?php echo e($label); ?>

                                                </a>
                                            <?php else: ?>
                                                <span class="logistics-doc-pending"><?php echo e($label); ?> pending</span>
                                            <?php endif; ?>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="ops-muted">Assign to this hub before reviewing documents.</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(!$rider->assigned_to_hub): ?>
                                    <span class="ops-muted">Assign one or more service areas to claim this rider for your hub.</span>
                                <?php elseif($rider->status === 'pending_activation' && (int) $rider->logistics_owner_id === (int) auth()->id()): ?>
                                    <form method="POST" action="<?php echo e(route('logistics.riders.activation.resend', $rider)); ?>">
                                        <?php echo csrf_field(); ?>
                                        <button class="ops-btn" type="submit">Resend activation</button>
                                    </form>
                                <?php elseif($rider->status === 'approved'): ?>
                                    <form class="ops-form" method="POST" action="<?php echo e(route('logistics.riders.suspend', $rider)); ?>" data-decision="logistics.riders.suspend" data-decision-title="Suspend this account?" data-decision-message="This restricts the account access and may affect its active work. Review the reason before continuing." data-decision-label="Suspend account" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        <?php echo csrf_field(); ?>
                                        <div class="ops-field">
                                            <input name="reason" required maxlength="500" aria-label="Suspension reason" placeholder="Reason for suspension…">
                                        </div>
                                        <button class="ops-btn ops-btn--danger" type="submit">Suspend</button>
                                    </form>
                                <?php elseif($rider->status === 'suspended'): ?>
                                    <?php if($rider->suspension_source === 'logistics' && $rider->suspension_previous_status === 'approved'): ?>
                                        <form method="POST" action="<?php echo e(route('logistics.riders.reactivate', $rider)); ?>" data-decision="logistics.riders.reactivate" data-decision-title="Restore this account?" data-decision-message="Check the account review and intended status before restoring access." data-decision-label="Restore account" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                            <?php echo csrf_field(); ?>
                                            <button class="ops-btn" type="submit">Reactivate</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="ops-muted">Admin review required</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="ops-muted">No action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="7">
                                <div class="ops-empty">
                                    <strong>No rider profiles found</strong>
                                    <p>No rider applications or profiles match this hub.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="ops-pagination"><?php echo e($riders->links()); ?></div>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.logistics', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/logistics/riders.blade.php ENDPATH**/ ?>