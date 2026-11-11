<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name']));

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

foreach (array_filter((['name']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $availableShopicons = [
        'account', 'arrowback', 'bell', 'cart1', 'cart2', 'chat', 'checklist', 'close', 'delivery1',
        'diagram', 'exclamationtriangle', 'heart', 'info', 'invoice', 'lightbulb', 'location',
        'lock', 'megaphone', 'money', 'note', 'package1', 'phone', 'print', 'search', 'settings',
        'shield', 'smileypositive', 'star', 'voucher', 'warning',
    ];

    abort_unless(in_array($name, $availableShopicons, true), 404);
?>

<shopicon-regular-<?php echo e($name); ?> <?php echo e($attributes->class(['shop-icon'])); ?> aria-hidden="true"></shopicon-regular-<?php echo e($name); ?>>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/shop-icon.blade.php ENDPATH**/ ?>