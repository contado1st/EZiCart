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
<?php ($recent = $user->last_web_activity_at && $user->last_web_activity_at->gte(now()->subMinutes(config('web-commerce.online_minutes')))); ?>
<div class="web-person-status"><span class="web-status-dot <?php echo e($recent ? 'is-online' : ''); ?>" aria-hidden="true"></span><span><?php echo e($recent ? 'Active recently' : ($user->last_web_activity_at ? 'Last active '.$user->last_web_activity_at->diffForHumans() : 'Activity not recorded yet')); ?></span></div>
<?php if($user->role === 'seller'): ?>
    <?php if($user->effectiveShopAvailability() === 'open'): ?><p class="web-person-status">Shop open <?php if($user->shop_availability !== 'open' && $user->shop_unavailable_from?->isFuture()): ?> · Scheduled pause begins <?php echo e($user->shop_unavailable_from->format('d M Y H:i')); ?> <?php endif; ?></p><?php endif; ?>
    <?php if($user->effectiveShopAvailability() !== 'open'): ?><p class="web-availability-note"><?php echo e(match($user->effectiveShopAvailability()) { 'vacation' => 'Seller on vacation.', 'closed' => 'Shop temporarily closed.', default => 'Seller temporarily unavailable.' }); ?> New orders are paused.<?php if($user->shop_returns_at): ?> Expected to return <?php echo e($user->shop_returns_at->format('d M Y H:i')); ?>. <?php endif; ?></p><?php endif; ?>
    <?php if($user->last_web_activity_at && $user->last_web_activity_at->lte(now()->subDays(config('web-commerce.inactive_warning_days')))): ?><p class="web-availability-note">This seller has been inactive for <?php echo e((int) $user->last_web_activity_at->diffInDays(now())); ?> days. Consider contacting the shop before purchasing.</p><?php endif; ?>
<?php endif; ?>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/user-activity.blade.php ENDPATH**/ ?>