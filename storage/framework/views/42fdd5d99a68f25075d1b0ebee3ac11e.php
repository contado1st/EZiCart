<dl class="profile-review-changes">
    <?php $__currentLoopData = $revision->proposed_values; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div>
            <dt><?php echo e(\Illuminate\Support\Str::headline(str_replace('_path', '', $field))); ?></dt>
            <?php $__currentLoopData = ['before' => $revision->previous_values[$field] ?? null, 'requested' => $value]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $version => $display): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <dd><span class="web-muted"><?php echo e($version === 'before' ? 'Current at submission' : 'Requested'); ?></span>
                    <?php if(in_array($field, ['profile_avatar_path', 'profile_banner_path'], true)): ?>
                        <?php if($display): ?><img class="profile-review-image" src="<?php echo e(route('account.profile-requests.media', [$revision, $field === 'profile_avatar_path' ? 'avatar' : 'banner', 'version' => $version])); ?>" alt="<?php echo e($version === 'before' ? 'Current' : 'Requested'); ?> <?php echo e($field === 'profile_avatar_path' ? 'profile photo' : 'banner'); ?>">
                        <?php else: ?><span><?php echo e($version === 'before' ? 'No image' : 'Remove image'); ?></span><?php endif; ?>
                    <?php else: ?><span><?php echo e(filled($display) ? $display : 'Not specified'); ?></span><?php endif; ?>
                </dd>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</dl>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/account/profile-change-values.blade.php ENDPATH**/ ?>