<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/platform-controls.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="dash-wrapper">
<main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Platform Account Moderation & Governance</h1>
                    <p class="dash-subtitle">Investigate policy infractions, manage account sanctions, and issue account
                        suspensions.</p>
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

            <div class="dash-panel">
                <div class="filter-bar-flex">
                    <form action="<?php echo e(route('admin.moderation.index')); ?>" method="GET" class="u-extracted-d8393f3793">
                        <input type="text" name="search" value="<?php echo e($search); ?>"
                            placeholder="Search name, email, store..." class="controls-input">
                        <select name="role" class="controls-select u-extracted-de2395bfe6">
                            <option value="">All Roles</option>
                            <option value="seller" <?php echo e($role === 'seller' ? 'selected' : ''); ?>>Sellers</option>
                            <option value="buyer" <?php echo e($role === 'buyer' ? 'selected' : ''); ?>>Buyers</option>
                        </select>
                        <button type="submit" class="dash-btn-sm dash-btn-primary">Filter</button>
                    </form>
                </div>

                <div class="dash-table-wrapper">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Account Name</th>
                                <th>Role</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Sanction / Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td>
                                        <strong><?php echo e($user->first_name); ?> <?php echo e($user->last_name); ?></strong>
                                        <?php if($user->business_name): ?>
                                            <div class="u-extracted-696134c27b">
                                                <?php echo e($user->business_name); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span
                                            class="badge-target-role"><?php echo e(strtoupper(str_replace('_', ' ', $user->role))); ?></span>
                                    </td>
                                    <td>
                                        <?php echo e($user->email); ?>

                                        <div class="u-extracted-cf0c302441">
                                            <?php echo e($user->contact_no); ?></div>
                                    </td>
                                    <td>
                                        <?php if($user->role === 'courier' && $user->active_pickups_count + $user->active_parcels_count > 0): ?>
                                            <?php ($activeParcelCount = $user->active_pickups_count + $user->active_parcels_count); ?>
                                            <p class="text-muted-small">Suspending this rider will open <?php echo e($activeParcelCount); ?> logistics recovery <?php echo e($activeParcelCount === 1 ? 'case' : 'cases'); ?>.</p>
                                        <?php endif; ?>
                                        <?php if($user->status === 'suspended'): ?>
                                            <span class="status-pill status-badge-suspended">SUSPENDED</span>
                                            <div class="u-extracted-da01588a85">
                                                <?php echo e($user->suspension_reason); ?>

                                            </div>
                                        <?php else: ?>
                                            <span class="status-pill status-<?php echo e(strtolower($user->status)); ?>">
                                                <?php echo e(strtoupper($user->status)); ?>

                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($user->status === 'suspended'): ?>
                                            <?php if($user->suspension_previous_status === 'approved'): ?>
                                                <form action="<?php echo e(route('admin.moderation.reactivate', $user->id)); ?>"
                                                    method="POST" data-decision="admin.moderation.reactivate" data-decision-title="Restore this account?" data-decision-message="Check the account review and intended status before restoring access." data-decision-label="Restore account" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                    <?php echo csrf_field(); ?>
                                                    <button type="submit"
                                                        class="dash-btn-sm dash-btn-success">Reactivate</button>
                                                </form>
                                            <?php else: ?>
                                                <p class="text-muted-small">Prior status is unknown. Review the account before restoring it.</p>
                                                <form action="<?php echo e(route('admin.moderation.reactivate', $user->id)); ?>"
                                                    method="POST" data-decision="admin.moderation.reactivate" data-decision-title="Restore this account?" data-decision-message="Check the account review and intended status before restoring access." data-decision-label="Restore account" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                    <?php echo csrf_field(); ?>
                                                    <label class="form-label"
                                                        for="restore-status-<?php echo e($user->id); ?>">Restore status after review</label>
                                                    <select id="restore-status-<?php echo e($user->id); ?>" class="controls-select"
                                                        name="restored_status" required>
                                                        <option value="" disabled selected>Choose status</option>
                                                        <option value="pending">Pending review</option>
                                                        <option value="approved">Approved after review</option>
                                                        <option value="rejected">Rejected</option>
                                                    </select>
                                                    <button type="submit" class="dash-btn-sm dash-btn-success">Restore account</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php elseif($user->status === 'approved'): ?>
                                            <form action="<?php echo e(route('admin.moderation.suspend', $user->id)); ?>"
                                                method="POST" class="table-action-form" data-decision="admin.moderation.suspend" data-decision-title="Suspend this account?" data-decision-message="This restricts the account access and may affect its active work. Review the reason before continuing." data-decision-label="Suspend account" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                <?php echo csrf_field(); ?>
                                                <input type="text" name="suspension_reason"
                                                    placeholder="Violation reason..."
                                                    class="controls-input u-extracted-637ac4c504" required>
                                                <button type="submit" class="dash-btn-sm dash-btn-danger">Suspend</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted-small">Registration review handles pending and rejected
                                                accounts.</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="5" class="dash-table-empty">No accounts match the current filter
                                        criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="u-extracted-dab43fb936">
                    <?php echo e($users->links()); ?>

                </div>
            </div>
        </main>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/admin/moderation/index.blade.php ENDPATH**/ ?>