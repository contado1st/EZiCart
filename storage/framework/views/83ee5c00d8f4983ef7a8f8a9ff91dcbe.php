<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['rider']));

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

foreach (array_filter((['rider']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<span class="<?php echo \Illuminate\Support\Arr::toCssClasses(['parcel-weight-label', 'is-missing' => !$rider->vehicle_capacity_verified_at || !$rider->vehicle_capacity_grams]); ?>"><?php echo e($rider->vehicle_type ?: 'Vehicle'); ?> · Cargo limit: <?php echo e($rider->vehicle_capacity_verified_at ? \App\Services\ParcelLoadService::kg($rider->vehicle_capacity_grams) : 'Not verified — dispatch blocked'); ?></span>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/vehicle-capacity.blade.php ENDPATH**/ ?>