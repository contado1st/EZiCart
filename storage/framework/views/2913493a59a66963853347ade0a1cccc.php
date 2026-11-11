<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['address']));

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

foreach (array_filter((['address']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div class="buyer-address-summary">
    <strong><?php echo e($address->recipient_name); ?></strong>
    <span><?php echo e($address->recipient_contact); ?></span>
    <address>
        <?php echo e(trim(($address->house_number ? $address->house_number.' ' : '').($address->street_address ?? ''))); ?><br>
        <?php echo e(implode(', ', array_filter([$address->barangay, $address->municipality, $address->province, $address->region]))); ?>

        <?php if($address->postal_code): ?><span><?php echo e($address->postal_code); ?></span><?php endif; ?>
    </address>
</div>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/buyer-address-summary.blade.php ENDPATH**/ ?>