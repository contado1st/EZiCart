<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['user']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['user']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<span <?php echo e($attributes->class(['profile-avatar'])); ?>>
    <?php if($user->profile_avatar_path): ?>
        <img src="<?php echo e(route('profiles.media', [$user, 'avatar', 'v' => $user->updated_at?->timestamp])); ?>" alt="" width="96" height="96" decoding="async">
    <?php else: ?>
        <span aria-hidden="true"><?php echo e(mb_strtoupper(mb_substr($user->profile_display_name ?: $user->first_name, 0, 1).mb_substr($user->last_name, 0, 1))); ?></span>
    <?php endif; ?>
</span>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/profile-avatar.blade.php ENDPATH**/ ?>