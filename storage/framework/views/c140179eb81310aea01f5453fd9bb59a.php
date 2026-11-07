<label for="<?php echo e($prefix); ?>-reference">External transaction or cash receipt reference</label>
<input id="<?php echo e($prefix); ?>-reference" name="reference" required maxlength="120">
<label for="<?php echo e($prefix); ?>-reason">Verification notes</label>
<textarea id="<?php echo e($prefix); ?>-reason" name="reason" required minlength="5" maxlength="500"></textarea>
<label for="<?php echo e($prefix); ?>-evidence">Receipt or transfer proof (JPG, PNG or PDF, up to 5 MB)</label>
<input id="<?php echo e($prefix); ?>-evidence" type="file" name="evidence" accept=".jpg,.jpeg,.png,.pdf" required>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/finance-evidence-fields.blade.php ENDPATH**/ ?>