<?php if($profileRevisions !== null): ?>
<section class="dash-panel web-section" id="profile-requests" aria-labelledby="profile-requests-title">
    <h2 id="profile-requests-title">Profile change requests</h2>
    <p>Changes to your profile, appearance and contact details require <?php echo e($user->role === 'seller' ? 'marketplace' : 'logistics'); ?> admin approval. Your verified details stay active until approval. Only one request may be pending at a time.</p>
    <?php $__empty_1 = true; $__currentLoopData = $profileRevisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $revision): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <article class="profile-request-history">
            <div class="web-actions"><strong><?php echo e(ucfirst($revision->status)); ?></strong><time class="web-muted" datetime="<?php echo e($revision->created_at->toIso8601String()); ?>">Submitted <?php echo e($revision->created_at->format('d M Y, h:i A')); ?></time></div>
            <details><summary>View requested changes</summary><?php echo $__env->make('account.profile-change-values', ['revision' => $revision], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></details>
            <?php if($revision->reviewed_at): ?><p class="web-muted">Reviewed <?php echo e($revision->reviewed_at->format('d M Y, h:i A')); ?> by <?php echo e($revision->reviewer?->first_name ?: 'Administrator'); ?></p><?php endif; ?>
            <?php if($revision->reason): ?><p><?php echo e($revision->reason); ?></p><?php endif; ?>
        </article>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="web-muted">No profile changes submitted yet.</p><?php endif; ?>
    <?php echo e($profileRevisions->fragment('profile-requests')->links()); ?>

</section>
<?php endif; ?>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/account/profile-requests.blade.php ENDPATH**/ ?>