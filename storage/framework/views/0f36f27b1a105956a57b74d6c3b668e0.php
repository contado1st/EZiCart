<section class="profile-editor profile-theme-<?php echo e($user->profile_theme ?: 'rose'); ?>" id="profile-appearance" aria-labelledby="profile-appearance-title" data-profile-editor data-profile-review="<?php echo e(in_array($user->role, ['seller', 'courier', 'sorting_center'], true) ? '1' : '0'); ?>">
    <div class="profile-cover">
        <img data-profile-preview="banner" <?php if($user->profile_banner_path): ?> src="<?php echo e(route('profiles.media', [$user, 'banner', 'v' => $user->updated_at?->timestamp])); ?>" <?php else: ?> hidden <?php endif; ?> alt="Your profile banner" width="1600" height="500">
        <span class="profile-cover-wordmark">EZiCart</span>
    </div>
    <div class="profile-identity">
        <div class="profile-avatar profile-avatar-large"><img data-profile-preview="avatar" <?php if($user->profile_avatar_path): ?> src="<?php echo e(route('profiles.media', [$user, 'avatar', 'v' => $user->updated_at?->timestamp])); ?>" <?php else: ?> hidden <?php endif; ?> alt="Your profile photo" width="400" height="400"><span data-profile-avatar-fallback <?php if($user->profile_avatar_path): ?> hidden <?php endif; ?> aria-hidden="true"><?php echo e(mb_strtoupper(mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1))); ?></span></div>
        <div class="profile-identity-copy"><h2 id="profile-appearance-title" data-profile-name><?php echo e($user->profile_display_name ?: ($user->role === 'seller' ? $user->business_name : $user->first_name.' '.$user->last_name)); ?></h2><p><?php echo e($user->role === 'seller' ? 'Your public shop identity' : 'Your personal account profile'); ?></p></div>
        <?php if($user->role === 'seller' && $user->status === 'approved'): ?><a class="profile-button" href="<?php echo e(route('shops.show', $user)); ?>">View my shop <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'arrowback']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'arrowback']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></a><?php endif; ?>
    </div>
    <div class="profile-editor-content">
        <div class="profile-section-heading"><h3>Make it yours</h3><p><?php echo e($user->role === 'seller' ? 'Your photo, banner and introduction appear on your public shop page.' : 'Personalize your account. Your profile and images are private.'); ?></p></div>
        <form action="<?php echo e(route('account.appearance.update')); ?>" method="POST" enctype="multipart/form-data" class="profile-appearance-form">
            <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
            <div class="profile-edit-grid">
                <label for="profile-display-name">Display name<input id="profile-display-name" name="profile_display_name" minlength="2" maxlength="50" value="<?php echo e(old('profile_display_name', $user->profile_display_name)); ?>" placeholder="<?php echo e($user->role === 'seller' ? $user->business_name : $user->first_name); ?>" data-profile-name-input><small>Optional. 2–50 characters. Your account details remain in the form below.</small><?php $__errorArgs = ['profile_display_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="profile-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
                <label for="profile-theme">Profile color<select id="profile-theme" name="profile_theme" data-profile-theme><?php $__currentLoopData = \App\Services\ProfileAppearanceService::THEMES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if(old('profile_theme', $user->profile_theme ?: 'rose') === $value): echo 'selected'; endif; ?>><?php echo e($name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><?php $__errorArgs = ['profile_theme'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="profile-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
                <label class="profile-edit-wide" for="profile-bio"><?php echo e($user->role === 'seller' ? 'Shop introduction' : 'About me'); ?><textarea id="profile-bio" name="profile_bio" rows="3" maxlength="1000" placeholder="<?php echo e($user->role === 'seller' ? 'Tell shoppers about your store and the products you offer.' : 'A short introduction for your personal profile.'); ?>"><?php echo e(old('profile_bio', $user->profile_bio)); ?></textarea><small>Up to 1,000 characters. Plain text only.</small><?php $__errorArgs = ['profile_bio'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="profile-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
                <div class="profile-upload"><label for="profile-avatar-file">Profile photo<input id="profile-avatar-file" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" data-profile-upload="avatar" aria-describedby="avatar-help"></label><small id="avatar-help">JPEG, PNG or WebP, up to 2 MB. 96–2048 px each side. Cropped to a square.</small><?php if($user->profile_avatar_path): ?><label class="profile-check"><input type="checkbox" name="remove_avatar" value="1" data-profile-remove="avatar"> Remove current photo</label><?php endif; ?> <?php $__errorArgs = ['avatar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="profile-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php $__errorArgs = ['remove_avatar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="profile-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                <div class="profile-upload"><label for="profile-banner-file">Banner image<input id="profile-banner-file" name="banner" type="file" accept="image/jpeg,image/png,image/webp" data-profile-upload="banner" aria-describedby="banner-help"></label><small id="banner-help">JPEG, PNG or WebP, up to 2 MB. At least 600 × 200 px; at most 4096 × 2048 px. Recommended 1600 × 500 px. Center-cropped.</small><?php if($user->profile_banner_path): ?><label class="profile-check"><input type="checkbox" name="remove_banner" value="1" data-profile-remove="banner"> Remove current banner</label><?php endif; ?> <?php $__errorArgs = ['banner'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="profile-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php $__errorArgs = ['remove_banner'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="profile-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
            </div>
            <?php $__errorArgs = ['profile_theme'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="profile-field-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <div class="profile-save-row"><p data-profile-preview-status role="status"><?php echo e(in_array($user->role, ['seller', 'courier', 'sorting_center'], true) ? 'These are previews. Your current appearance stays active until an admin approves your request.' : 'Image and color previews are saved only when you select Save appearance.'); ?></p><button class="profile-button profile-button-primary" type="submit"><?php echo e(in_array($user->role, ['seller', 'courier', 'sorting_center'], true) ? 'Request appearance update' : 'Save appearance'); ?></button></div>
        </form>
    </div>
</section>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/account/appearance.blade.php ENDPATH**/ ?>