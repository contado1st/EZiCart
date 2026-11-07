<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/app.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="admin-container">
        <div class="admin-header-row">
            <div>
                <h1 class="admin-page-title">Account Approval Center</h1>
                <p class="admin-page-desc">Review pending applicant submissions and inspect uploaded credentials.</p>
            </div>
            <span class="badge-pending-count">
                Pending Approvals: <?php echo e($pendingUsers->count()); ?>

            </span>
        </div>

        <?php if(session('success')): ?>
            <div class="alert-success">
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
<?php endif; ?> <?php echo e(session('success')); ?>

            </div>
        <?php endif; ?>

        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Role</th>
                        <th>Contact & Address</th>
                        <th>Role Details</th>
                        <th>Verification Files</th>
                        <th class="u-extracted-13cbe03b9a">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $pendingUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <!-- Applicant -->
                            <td>
                                <div class="text-dark-bold">
                                    <?php echo e(collect([$user->first_name, $user->middle_name ?: $user->middle_initial, $user->last_name, $user->suffix])->filter()->join(' ')); ?>

                                </div>
                                <div class="text-muted-small">
                                    Age: <?php echo e($user->age); ?> | Sex: <?php echo e($user->sex); ?>

                                </div>
                            </td>

                            <!-- Role -->
                            <td>
                                <span
                                    class="role-badge <?php echo e($user->role === 'seller' ? 'role-seller' : ($user->role === 'courier' ? 'role-courier' : 'role-buyer')); ?>">
                                    <?php echo e($user->role); ?>

                                </span>
                            </td>

                            <!-- Contact & Address -->
                            <td>
                                <div class="contact-info"><?php echo e($user->email); ?></div>
                                <div class="text-muted-small"><?php echo e($user->contact_no); ?></div>
                                <div class="text-muted-small">
                                    <?php echo e(trim(($user->house_number ? $user->house_number.' ' : '').$user->street_address)); ?>, <?php echo e($user->barangay); ?>, <?php echo e($user->municipality); ?>,
                                    <?php echo e($user->province); ?>

                                </div>
                            </td>

                            <!-- Role Specifics -->
                            <td>
                                <?php if($user->role === 'seller'): ?>
                                    <div><strong>Store:</strong> <?php echo e($user->business_name); ?></div>
                                    <div class="text-muted-small">Category: <?php echo e($user->line_of_business); ?> | Type: <?php echo e($user->seller_type); ?></div>
                                    <div class="text-muted-small">TIN: <?php echo e($user->tin); ?></div>
                                <?php elseif($user->role === 'sorting_center'): ?>
                                    <div><strong>Company:</strong> <?php echo e($user->business_name); ?></div>
                                    <div class="text-muted-small">SEC: <?php echo e($user->sec_registration_no); ?> | TIN: <?php echo e($user->tin); ?></div>
                                    <div class="text-muted-small">Position: <?php echo e($user->contact_position); ?></div>
                                    <div class="text-muted-small">Service areas: <?php echo e(implode(', ', $user->service_areas ?? [])); ?></div>
                                <?php elseif($user->role === 'courier'): ?>
                                    <div><strong>Vehicle:</strong> <?php echo e($user->vehicle_type); ?></div>
                                    <div class="text-muted-small">Plate: <?php echo e($user->plate_number); ?></div>
                                <?php else: ?>
                                    <span class="u-extracted-4cd15eb1e0">Standard Buyer Account</span>
                                <?php endif; ?>
                            </td>

                            <!-- Uploaded Document Links -->
                            <td>
                                <?php if($user->id_path || $user->id_upload_path): ?>
                                    <a href="<?php echo e(route('admin.users.documents.show', [$user, 'identity', 'preview' => 1])); ?>" target="_blank" rel="noopener"
                                        class="doc-link"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> View Valid ID</a>
                                <?php endif; ?>
                                <?php if($user->license_path): ?>
                                    <a href="<?php echo e(route('admin.users.documents.show', [$user, 'license', 'preview' => 1])); ?>" target="_blank" rel="noopener" class="doc-link">View Driver’s License</a>
                                <?php endif; ?>

                                <?php if($user->permit_path || $user->business_permit_path): ?>
                                    <a href="<?php echo e(route('admin.users.documents.show', [$user, 'permit', 'preview' => 1])); ?>" target="_blank" rel="noopener"
                                        class="doc-link"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> View Business Permit</a>
                                <?php endif; ?>

                                <?php if($user->sec_certificate_path): ?>
                                    <a href="<?php echo e(route('admin.users.documents.show', [$user, 'sec'])); ?>" class="doc-link">View SEC Certificate</a>
                                <?php endif; ?>
                                <?php if($user->bir_certificate_path): ?>
                                    <a href="<?php echo e(route('admin.users.documents.show', [$user, 'bir'])); ?>" class="doc-link">View BIR Form 2303</a>
                                <?php endif; ?>
                                <?php if($user->representative_id_path): ?>
                                    <a href="<?php echo e(route('admin.users.documents.show', [$user, 'representative'])); ?>" class="doc-link">View Representative ID</a>
                                <?php endif; ?>

                                <?php if($user->or_cr_path || $user->or_cr_upload_path): ?>
                                    <a href="<?php echo e(route('admin.users.documents.show', [$user, 'vehicle'])); ?>"
                                        class="doc-link"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> View Vehicle OR/CR</a>
                                <?php endif; ?>
                            </td>

                            <!-- Action Forms -->
                            <td>
                                <div class="action-buttons">
                                    <a class="dash-btn-sm dash-btn-outline" href="<?php echo e(route('admin.registrations.show', $user)); ?>">Review details & documents</a>
                                    <form action="<?php echo e(route('admin.registrations.approve', $user->id)); ?>" method="POST" data-decision="admin.registrations.approve" data-decision-title="Approve this account?" data-decision-message="Confirm that you reviewed the submitted credentials and that this account meets the approval requirements." data-decision-label="Approve account" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit"
                                            class="btn-approve">Approve</button>
                                    </form>

                                    <form action="<?php echo e(route('admin.registrations.reject', $user->id)); ?>" method="POST" data-decision="admin.registrations.reject" data-decision-title="Reject this application?" data-decision-message="Check the rejection reason before recording your decision." data-decision-label="Reject application" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        <?php echo csrf_field(); ?>
                                        <label class="sr-only" for="rejection-reason-<?php echo e($user->id); ?>">Reason for rejecting <?php echo e($user->first_name); ?> <?php echo e($user->last_name); ?></label>
                                        <input id="rejection-reason-<?php echo e($user->id); ?>" type="text" name="reason" maxlength="1000" minlength="5" placeholder="Reason required" required>
                                        <button type="submit"
                                            class="btn-reject">Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="6" class="empty-row">
                                No pending user applications found. All registrations are processed!
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/admin/registrations.blade.php ENDPATH**/ ?>