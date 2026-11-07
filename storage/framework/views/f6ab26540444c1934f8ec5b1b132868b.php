<?php $__env->startSection('title', ($address ? 'Edit address' : 'Add an address').' — EZiCart'); ?>
<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/buyer-addresses.css')); ?>?v=<?php echo e(filemtime(public_path('css/buyer-addresses.css'))); ?>">
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<main class="buyer-address-page">
    <header class="dash-header"><div><h1 class="dash-title"><?php echo e($address ? 'Edit address' : 'Add an address'); ?></h1><p class="dash-subtitle">Save a delivery location and the contact who will receive your parcel.</p></div><a href="<?php echo e(route('buyer.addresses.index')); ?>" class="dash-btn-sm dash-btn-outline">Back to addresses</a></header>
    <section class="dash-panel buyer-address-form-panel">
        <?php if($errors->any()): ?><div class="buyer-address-error" role="alert"><strong>Check your address details.</strong><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($message); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div><?php endif; ?>
        <form method="POST" action="<?php echo e($address ? route('buyer.addresses.update', $address) : route('buyer.addresses.store')); ?>" class="buyer-address-form">
            <?php echo csrf_field(); ?>
            <?php if($address): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>
            <div class="buyer-address-form-grid">
                <div class="buyer-address-field"><label for="address-label">Address label</label><input id="address-label" name="label" maxlength="50" value="<?php echo e(old('label', $address?->label)); ?>" placeholder="Home, work, or another location" required></div>
                <div class="buyer-address-field"><label for="address-recipient">Recipient name</label><input id="address-recipient" name="recipient_name" minlength="2" maxlength="255" value="<?php echo e(old('recipient_name', $address?->recipient_name ?? trim(auth()->user()->first_name.' '.auth()->user()->last_name))); ?>" autocomplete="name" required></div>
                <div class="buyer-address-field"><label for="address-contact">Recipient mobile number</label><input id="address-contact" name="recipient_contact" type="tel" maxlength="13" pattern="(09[0-9]{9}|\+639[0-9]{9})" value="<?php echo e(old('recipient_contact', $address?->recipient_contact ?? auth()->user()->contact_no)); ?>" autocomplete="tel" required aria-describedby="address-contact-help"><small id="address-contact-help">Use 09XXXXXXXXX or +639XXXXXXXXX.</small></div>
            </div>
            <?php echo $__env->make('components.psgc-address-fields', ['prefix' => 'saved-address', 'address' => $address, 'postalRequired' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php if($address?->is_default): ?>
                <p class="buyer-address-help"><span class="buyer-address-default">Default address</span> To change your default, choose another address from your saved list.</p>
            <?php else: ?>
                <label class="buyer-address-checkbox"><input type="checkbox" name="is_default" value="1" <?php if(old('is_default', false)): echo 'checked'; endif; ?>> Use as my default delivery address</label>
            <?php endif; ?>
            <div class="buyer-address-actions"><button class="dash-btn-primary" type="submit"><?php echo e($address ? 'Save changes' : 'Save address'); ?></button><a class="dash-btn-sm dash-btn-outline" href="<?php echo e(route('buyer.addresses.index')); ?>">Cancel</a></div>
        </form>
    </section>
</main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/buyer/addresses/form.blade.php ENDPATH**/ ?>