<div class="form-group"><label class="form-label" for="product-weight">Weight per product unit (kg) *</label><input class="form-control" id="product-weight" name="weight_kg" type="number" min="0.001" max="100000" step="0.001" value="<?php echo e(old('weight_kg', isset($product) && $product->weight_grams ? $product->weight_grams / 1000 : '')); ?>" required><small>Measure one unit. The seller records the final packed parcel weight, including packaging, before pickup.</small><?php $__errorArgs = ['weight_kg'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="profile-field-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/seller/products/weight-field.blade.php ENDPATH**/ ?>