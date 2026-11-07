<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/platform-controls.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="dash-wrapper">
<main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Platform Announcements & Bulletins</h1>
                    <p class="dash-subtitle">Broadcast operational notices, maintenance schedules, and policy updates.</p>
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

            <div class="controls-grid">
                <!-- Create Announcement -->
                <div class="controls-card">
                    <h2 class="controls-title">Create Announcement</h2>
                    <form action="<?php echo e(route('admin.announcements.store')); ?>" method="POST">
                        <?php echo csrf_field(); ?>

                        <div class="controls-field">
                            <label class="controls-label">Title *</label>
                            <input type="text" name="title" value="<?php echo e(old('title')); ?>"
                                placeholder="e.g. Scheduled System Maintenance" class="controls-input" required>
                        </div>

                        <div class="controls-field">
                            <label class="controls-label">Notice Type *</label>
                            <select name="type" class="controls-select" required>
                                <option value="info">Information (Blue)</option>
                                <option value="warning">Warning / Advisory (Yellow)</option>
                                <option value="urgent">Urgent Alert (Red)</option>
                                <option value="maintenance">System Maintenance (Gray)</option>
                            </select>
                        </div>

                        <div class="controls-field">
                            <label class="controls-label">Target Audience *</label>
                            <select name="target_role" class="controls-select" required>
                                <option value="all">Entire Platform (All Users)</option>
                                <option value="buyer">Buyers Only</option>
                                <option value="seller">Merchants & Sellers Only</option>
                                <option value="courier">Delivery Riders Only</option>
                                <option value="sorting_center">Sorting Centers Only</option>
                            </select>
                        </div>

                        <div class="controls-field">
                            <label class="controls-label">Message Content *</label>
                            <textarea name="content" rows="4" class="controls-textarea" placeholder="Provide complete notice details..."
                                required><?php echo e(old('content')); ?></textarea>
                        </div>

                        <div class="controls-field">
                            <label class="controls-label">Expiration Date (Optional)</label>
                            <input type="date" name="expires_at" class="controls-input">
                        </div>

                        <button type="submit" class="dash-btn-primary u-extracted-ec7de1f956">
                            Publish Announcement
                        </button>
                    </form>
                </div>

                <!-- Announcements Table -->
                <div class="dash-panel">
                    <h2 class="courier-section-title">Published Notices (<?php echo e($announcements->total()); ?>)</h2>

                    <div class="dash-table-wrapper u-extracted-dab43fb936">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Audience</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__empty_1 = true; $__currentLoopData = $announcements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo e($item->title); ?></strong>
                                            <div class="u-extracted-cf0c302441">
                                                <?php echo e(\Illuminate\Support\Str::limit($item->content, 60)); ?></div>
                                        </td>
                                        <td><span
                                                class="badge-target-role"><?php echo e(strtoupper(str_replace('_', ' ', $item->target_role))); ?></span>
                                        </td>
                                        <td>
                                            <span class="status-pill banner-type-<?php echo e($item->type); ?>">
                                                <?php echo e(ucfirst($item->type)); ?>

                                            </span>
                                        </td>
                                        <td>
                                            <span
                                                class="status-pill <?php echo e($item->is_active ? 'status-completed' : 'status-failed'); ?>">
                                                <?php echo e($item->is_active ? 'Active' : 'Archived'); ?>

                                            </span>
                                        </td>
                                        <td><?php echo e($item->created_at->format('M d, Y')); ?></td>
                                        <td>
                                            <div class="u-extracted-6d9d6fda77">
                                                <form action="<?php echo e(route('admin.announcements.toggle', $item->id)); ?>"
                                                    method="POST" data-decision="admin.announcements.toggle" data-decision-title="Change announcement visibility?" data-decision-message="This changes whether users can see the selected announcement." data-decision-label="Change visibility" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                    <?php echo csrf_field(); ?>
                                                    <?php echo method_field('PATCH'); ?>
                                                    <button type="submit" class="dash-btn-sm u-extracted-e013d740bb">
                                                        <?php echo e($item->is_active ? 'Archive' : 'Activate'); ?>

                                                    </button>
                                                </form>
                                                <form action="<?php echo e(route('admin.announcements.destroy', $item->id)); ?>"
                                                    method="POST" data-decision="admin.announcements.destroy" data-decision-title="Delete this announcement?" data-decision-message="This permanently deletes the selected announcement." data-decision-label="Delete announcement" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                    <?php echo csrf_field(); ?>
                                                    <?php echo method_field('DELETE'); ?>
                                                    <button type="submit"
                                                        class="dash-btn-sm dash-btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="6" class="dash-table-empty">No announcements active.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="u-extracted-dab43fb936">
                        <?php echo e($announcements->links()); ?>

                    </div>
                </div>
            </div>
        </main>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/admin/announcements/index.blade.php ENDPATH**/ ?>