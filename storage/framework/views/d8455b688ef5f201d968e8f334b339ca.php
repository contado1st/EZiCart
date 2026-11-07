<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['order', 'kind', 'event']));

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

foreach (array_filter((['order', 'kind', 'event']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php if((int) $event->actor_id === auth()->id() && ($kind !== 'scan' || $event->result === 'accepted')): ?>
<form class="web-form" method="POST" action="<?php echo e(route('parcel-evidence.store', $order)); ?>" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="event_type" value="<?php echo e($kind); ?>"><input type="hidden" name="event_id" value="<?php echo e($event->id); ?>"><label for="photo-<?php echo e($kind); ?>-<?php echo e($event->id); ?>">Attach receiving / handoff photos (up to 3; 2 MB each)<input id="photo-<?php echo e($kind); ?>-<?php echo e($event->id); ?>" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required></label><button class="dash-btn-sm" type="submit">Save event photos</button></form>
<?php endif; ?>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/parcel-evidence-upload.blade.php ENDPATH**/ ?>