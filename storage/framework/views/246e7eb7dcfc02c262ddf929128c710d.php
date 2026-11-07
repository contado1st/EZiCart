<?php
    $prefix = $prefix ?? 'address';
    $address = $address ?? null;
    $postalRequired = $postalRequired ?? false;
    $addressOptional = $addressOptional ?? false;
    $allowManual = $allowManual ?? true;
    $addressMode = $allowManual
        ? old('address_mode', $addressOptional ? '' : 'psgc')
        : 'psgc';
    $selected = [
        'region' => old('region', $address?->region ?? ''),
        'province' => old('province', $address?->province ?? ''),
        'municipality' => old('municipality', $address?->municipality ?? ''),
        'barangay' => old('barangay', $address?->barangay ?? ''),
    ];
?>

<div class="psgc-address-fields" data-psgc-address data-options-url="<?php echo e(route('psgc.addresses')); ?>" data-address-mode="<?php echo e($addressMode); ?>" data-selected-region="<?php echo e($selected['region']); ?>" data-selected-province="<?php echo e($selected['province']); ?>" data-selected-municipality="<?php echo e($selected['municipality']); ?>" data-selected-barangay="<?php echo e($selected['barangay']); ?>">
    <input type="hidden" name="region" value="<?php echo e($selected['region']); ?>" data-address-value="region">
    <input type="hidden" name="province" value="<?php echo e($selected['province']); ?>" data-address-value="province">
    <input type="hidden" name="municipality" value="<?php echo e($selected['municipality']); ?>" data-address-value="municipality">
    <input type="hidden" name="barangay" value="<?php echo e($selected['barangay']); ?>" data-address-value="barangay">

    <fieldset class="address-entry-choice" aria-describedby="<?php echo e($prefix); ?>-address-choice-help">
        <legend>How would you like to enter the location?</legend>
        <label for="<?php echo e($prefix); ?>-mode-psgc"><input id="<?php echo e($prefix); ?>-mode-psgc" type="radio" name="address_mode" value="psgc" data-address-mode-choice <?php if($addressMode === 'psgc'): echo 'checked'; endif; ?> <?php if (! ($addressOptional)): ?> required <?php endif; ?>> Choose from location lists</label>
        <?php if($allowManual): ?><label for="<?php echo e($prefix); ?>-mode-manual"><input id="<?php echo e($prefix); ?>-mode-manual" type="radio" name="address_mode" value="manual" data-address-mode-choice <?php if($addressMode === 'manual'): echo 'checked'; endif; ?> <?php if (! ($addressOptional)): ?> required <?php endif; ?>> Enter address manually</label><?php endif; ?>
    </fieldset>
    <p class="auth-help" id="<?php echo e($prefix); ?>-address-choice-help"><?php if($allowManual): ?>Manual entry is available immediately while lists load. Use the official area names for accurate delivery routing.<?php else: ?> Choose each location from the official list so delivery areas can be validated.<?php endif; ?></p>

    <div data-psgc-address-panel>
    <div class="auth-grid">
        <div class="auth-field">
            <label for="<?php echo e($prefix); ?>-region">Region</label>
            <select id="<?php echo e($prefix); ?>-region" name="region_code" data-address-select="region" <?php if (! ($addressOptional)): ?> required <?php endif; ?>>
                <option value="">Loading regions…</option>
            </select>
            <?php $__errorArgs = ['region_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <?php $__errorArgs = ['region'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="auth-field">
            <label for="<?php echo e($prefix); ?>-province">Province</label>
            <select id="<?php echo e($prefix); ?>-province" name="province_code" data-address-select="province" disabled <?php if (! ($addressOptional)): ?> required <?php endif; ?>>
                <option value="">Choose a region first</option>
            </select>
            <?php $__errorArgs = ['province_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <?php $__errorArgs = ['province'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="auth-field">
            <label for="<?php echo e($prefix); ?>-municipality">City / Municipality</label>
            <select id="<?php echo e($prefix); ?>-municipality" name="municipality_code" data-address-select="municipality" disabled <?php if (! ($addressOptional)): ?> required <?php endif; ?>>
                <option value="">Choose a province first</option>
            </select>
            <?php $__errorArgs = ['municipality_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <?php $__errorArgs = ['municipality'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="auth-field">
            <label for="<?php echo e($prefix); ?>-barangay">Barangay</label>
            <select id="<?php echo e($prefix); ?>-barangay" name="barangay_code" data-address-select="barangay" disabled <?php if (! ($addressOptional)): ?> required <?php endif; ?>>
                <option value="">Choose a city / municipality first</option>
            </select>
            <?php $__errorArgs = ['barangay_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <?php $__errorArgs = ['barangay'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
    </div>
    </div>

    <?php if($allowManual): ?><div class="auth-grid" data-manual-address-panel hidden>
        <?php $__currentLoopData = ['region' => 'Region', 'province' => 'Province', 'municipality' => 'City / Municipality', 'barangay' => 'Barangay']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="auth-field">
                <label for="<?php echo e($prefix); ?>-manual-<?php echo e($name); ?>"><?php echo e($label); ?></label>
                <input id="<?php echo e($prefix); ?>-manual-<?php echo e($name); ?>" name="manual_<?php echo e($name); ?>" value="<?php echo e(old('manual_'.$name, $selected[$name])); ?>" maxlength="120" autocomplete="address-level<?php echo e($loop->iteration + 1); ?>" data-manual-address-field disabled>
                <?php $__errorArgs = ['manual_'.$name];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div><?php endif; ?>

    <div class="auth-grid" data-address-lines>
        <div class="auth-field">
            <label for="<?php echo e($prefix); ?>-house-number">House / building number</label>
            <input id="<?php echo e($prefix); ?>-house-number" name="house_number" value="<?php echo e(old('house_number', $address?->house_number ?? '')); ?>" maxlength="100" autocomplete="address-line1">
            <small class="auth-help">Enter a house number or building name, such as 12B or Sunrise Tower.</small>
            <?php $__errorArgs = ['house_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="auth-field">
            <label for="<?php echo e($prefix); ?>-street">Street / subdivision</label>
            <input id="<?php echo e($prefix); ?>-street" name="street_address" value="<?php echo e(old('street_address', $address?->street_address ?? '')); ?>" maxlength="255" autocomplete="address-line2" <?php if($postalRequired): ?> required <?php endif; ?>>
            <small class="auth-help">Enter the street, subdivision, or purok. Leave blank if there isn’t one.</small>
            <?php $__errorArgs = ['street_address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="auth-field">
            <label for="<?php echo e($prefix); ?>-postal">ZIP code<?php echo e($postalRequired ? '' : ' (optional)'); ?></label>
            <input id="<?php echo e($prefix); ?>-postal" name="postal_code" value="<?php echo e(old('postal_code', $address?->postal_code ?? '')); ?>" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" data-address-digits-only <?php if($postalRequired): ?> required <?php endif; ?>>
            <small class="auth-help">Enter exactly 4 numbers, such as 1000.</small>
            <?php $__errorArgs = ['postal_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
    </div>
    <p class="auth-help" data-address-status role="status">Select your region to load locations.</p>
</div>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/psgc-address-fields.blade.php ENDPATH**/ ?>