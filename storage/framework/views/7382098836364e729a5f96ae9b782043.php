<?php $__env->startSection('title', 'Profile change approvals | EZiCart'); ?>
<?php $__env->startSection('content'); ?>
<main class="web-operations">
    <header><h1>Profile change approvals</h1><p><?php echo e(auth()->user()->isCommerceAdmin() ? 'Review seller profile and contact updates.' : 'Review rider and sorting center profile and contact updates.'); ?> Changes take effect only after approval.</p></header>
    <?php echo $__env->make('components.feature-feedback', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <form method="GET" class="web-form"><label for="profile-review-status">Request status<select id="profile-review-status" name="status"><?php $__currentLoopData = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All requests']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if($status === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><button type="submit" class="dash-btn-sm dash-btn-outline">Filter requests</button></form>
    <section class="dash-panel"><h2><?php echo e($revisions->total()); ?> <?php echo e(Str::plural('request', $revisions->total())); ?></h2>
        <div class="dash-table-wrapper"><table class="dash-table profile-review-table">
            <thead><tr><th scope="col">Account / Submitted</th><th scope="col">Current and requested changes</th><th scope="col">Status</th><th scope="col">Review decision</th></tr></thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $revisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $revision): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><a href="<?php echo e(route('staff.show', $revision->user)); ?>"><strong><?php echo e($revision->user->first_name); ?> <?php echo e($revision->user->last_name); ?></strong></a><p><?php echo e(str_replace('_', ' ', $revision->user->role)); ?></p><p><?php echo e($revision->user->email); ?></p><time class="web-muted" datetime="<?php echo e($revision->created_at->toIso8601String()); ?>"><?php echo e($revision->created_at->format('d M Y, h:i A')); ?></time></td>
                    <td><?php echo $__env->make('account.profile-change-values', ['revision' => $revision], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></td>
                    <td><strong><?php echo e(ucfirst($revision->status)); ?></strong><?php if($revision->reviewed_at): ?><p class="web-muted"><?php echo e($revision->reviewed_at->format('d M Y, h:i A')); ?><br><?php echo e($revision->reviewer?->first_name ?: 'Administrator'); ?></p><?php endif; ?></td>
                    <td>
                        <?php if($revision->status === 'pending'): ?>
                            <form method="POST" action="<?php echo e(route($reviewRoute, $revision)); ?>" class="profile-review-decision" data-decision="profile-review" data-decision-title="Record this profile decision?" data-decision-message="Approval replaces the verified details with the requested values. Rejection keeps the current details." data-decision-label="Record decision" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                                <label for="profile-review-reason-<?php echo e($revision->id); ?>">Reason (required to reject)</label><textarea id="profile-review-reason-<?php echo e($revision->id); ?>" name="reason" rows="2" minlength="5" maxlength="1000"><?php echo e(old('reason')); ?></textarea>
                                <div class="web-actions"><button class="dash-btn-sm dash-btn-primary" type="submit" name="decision" value="approve">Approve</button><button class="dash-btn-sm dash-btn-outline" type="submit" name="decision" value="reject">Reject</button></div>
                            </form>
                        <?php else: ?><p><?php echo e($revision->reason ?: 'Review completed.'); ?></p><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="4">No <?php echo e($status === 'all' ? '' : $status); ?> profile change requests.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
        <?php echo e($revisions->links()); ?>

    </section>
</main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/staff/reviews.blade.php ENDPATH**/ ?>