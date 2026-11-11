<?php $__env->startPush('styles'); ?>
    <script src="<?php echo e(asset('js/profiles.js')); ?>?v=<?php echo e(filemtime(public_path('js/profiles.js'))); ?>" defer></script>
<?php $__env->stopPush(); ?>

<?php if($user->role === 'buyer'): ?>
    <?php $__env->startPush('styles'); ?>
        <link rel="stylesheet" href="<?php echo e(asset('css/buyer-account.css')); ?>?v=<?php echo e(filemtime(public_path('css/buyer-account.css'))); ?>">
    <?php $__env->stopPush(); ?>
<?php endif; ?>

<?php $__env->startSection('content'); ?>
    <?php if($user->role === 'buyer'): ?>
        <main class="buyer-account-page">
            <?php echo $__env->make('components.buyer-account-sidebar', ['activeSection' => 'profile'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <div class="buyer-account-content">
                <header class="buyer-account-header">
                    <div><p class="buyer-account-eyebrow">My Account</p><h1>My profile</h1><p>Manage your profile and keep your delivery details up to date.</p></div>
                    <a href="<?php echo e(route('buyer.dashboard')); ?>">View my purchases <span aria-hidden="true">↗</span></a>
                </header>

                <?php if(session('success')): ?><div class="buyer-account-notice" role="status"><?php echo e(session('success')); ?></div><?php endif; ?>
                <?php if($errors->any()): ?><div class="buyer-account-notice buyer-account-notice--error" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?>

                <?php echo $__env->make('account.appearance', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php if($followedShops->total()): ?><section class="profile-followed" id="following-shops"><h2>Shops you follow</h2><p>Your followed active shops, most recent first.</p><div><?php $__currentLoopData = $followedShops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $followedShop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('shops.show', $followedShop)); ?>"><?php if (isset($component)) { $__componentOriginal32077e48dfcf7eeccbd729b994858fc5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal32077e48dfcf7eeccbd729b994858fc5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.profile-avatar','data' => ['user' => $followedShop]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('profile-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($followedShop)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal32077e48dfcf7eeccbd729b994858fc5)): ?>
<?php $attributes = $__attributesOriginal32077e48dfcf7eeccbd729b994858fc5; ?>
<?php unset($__attributesOriginal32077e48dfcf7eeccbd729b994858fc5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal32077e48dfcf7eeccbd729b994858fc5)): ?>
<?php $component = $__componentOriginal32077e48dfcf7eeccbd729b994858fc5; ?>
<?php unset($__componentOriginal32077e48dfcf7eeccbd729b994858fc5); ?>
<?php endif; ?><span><?php echo e($followedShop->profile_display_name ?: $followedShop->business_name); ?></span><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'arrowback']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'arrowback']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php echo e($followedShops->fragment('following-shops')->links()); ?></section><?php endif; ?>

                <section class="buyer-account-panel" id="buyer-profile" aria-labelledby="buyer-profile-title">
                    <div class="buyer-account-panel-heading"><div><h2 id="buyer-profile-title">Profile details</h2><p>Update the name and contact details connected to your account.</p></div></div>
                    <form action="<?php echo e(route('account.profile.update')); ?>" method="POST" class="buyer-account-form">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <div class="buyer-account-form-grid">
                            <div class="form-group"><label class="form-label" for="first-name">First name</label><input id="first-name" class="form-control" name="first_name" value="<?php echo e(old('first_name', $user->first_name)); ?>" autocomplete="given-name" required><?php $__errorArgs = ['first_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="buyer-account-field-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                            <div class="form-group"><label class="form-label" for="middle-initial">Middle initial</label><input id="middle-initial" class="form-control" name="middle_initial" maxlength="2" value="<?php echo e(old('middle_initial', $user->middle_initial)); ?>" autocomplete="additional-name"><?php $__errorArgs = ['middle_initial'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="buyer-account-field-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                            <div class="form-group"><label class="form-label" for="last-name">Last name</label><input id="last-name" class="form-control" name="last_name" value="<?php echo e(old('last_name', $user->last_name)); ?>" autocomplete="family-name" required><?php $__errorArgs = ['last_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="buyer-account-field-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                            <div class="form-group"><label class="form-label" for="email">Email address</label><input id="email" class="form-control" value="<?php echo e($user->email); ?>" autocomplete="email" disabled><small>Your login email is currently read-only.</small></div>
                            <div class="form-group"><label class="form-label" for="contact-number">Contact number</label><input id="contact-number" class="form-control" name="contact_no" value="<?php echo e(old('contact_no', $user->contact_no)); ?>" autocomplete="tel" required><?php $__errorArgs = ['contact_no'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="buyer-account-field-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                        </div>

                        <div class="buyer-account-address" id="buyer-addresses">
                            <div class="buyer-account-panel-heading"><div><h2>Default delivery address</h2><p>Editing this location also updates your default saved address.</p></div><a href="<?php echo e(route('buyer.addresses.index')); ?>">Manage saved addresses (<?php echo e($user->addresses_count); ?>)</a></div>
                            <?php echo $__env->make('components.psgc-address-fields', ['prefix' => 'account-address', 'address' => $user, 'postalRequired' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        </div>
                        <button class="buyer-account-save" type="submit">Save profile</button>
                    </form>
                </section>

                <section class="buyer-account-panel buyer-password-panel" id="buyer-password" aria-labelledby="buyer-password-title">
                    <div class="buyer-account-panel-heading"><div><h2 id="buyer-password-title">Change password</h2><p>Use a unique password to protect your EZiCart account.</p></div></div>
                    <form action="<?php echo e(route('account.password.update')); ?>" method="POST" class="buyer-account-password-form" data-decision="account.password.update" data-decision-title="Change your password?" data-decision-message="Use the new password the next time you sign in. Check that you have entered it correctly." data-decision-label="Change password" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <div class="buyer-password-fields">
                            <div class="form-group"><label class="form-label" for="current-password">Current password</label><div class="credential-password-wrap"><input id="current-password" type="password" class="form-control" name="current_password" required autocomplete="current-password"><button class="credential-password-toggle" type="button" data-password-toggle="current-password" aria-label="Show password" aria-pressed="false">Show</button></div><?php $__errorArgs = ['current_password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="buyer-account-field-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                            <div class="form-group"><label class="form-label" for="new-password">New password</label><div class="credential-password-wrap"><input id="new-password" type="password" class="form-control" name="password" required autocomplete="new-password"><button class="credential-password-toggle" type="button" data-password-toggle="new-password" aria-label="Show password" aria-pressed="false">Show</button></div><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="buyer-account-field-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                            <div class="form-group"><label class="form-label" for="new-password-confirmation">Confirm new password</label><div class="credential-password-wrap"><input id="new-password-confirmation" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password"><button class="credential-password-toggle" type="button" data-password-toggle="new-password-confirmation" aria-label="Show password" aria-pressed="false">Show</button></div></div>
                        </div>
                        <button class="buyer-account-save" type="submit">Update password</button>
                    </form>
                </section>
            </div>
        </main>
    <?php else: ?>
        <main class="container u-extracted-d7ea292b2e">
            <h1>Account settings</h1>
            <p>Update your contact and profile details. Your role, approval status, and uploaded documents can’t be changed here.</p>

            <?php if(session('success')): ?><div class="form-notice"><?php echo e(session('success')); ?></div><?php endif; ?>

            <?php if($errors->any()): ?><p class="profile-field-error" role="alert"><?php echo e($errors->first()); ?></p><?php endif; ?>
            <?php echo $__env->make('account.profile-requests', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('account.appearance', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <section class="dash-panel u-extracted-ebdbcd4a73">
                <h2>Profile and contact details</h2>
                <form action="<?php echo e(route('account.profile.update')); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PATCH'); ?>
                    <div class="form-group"><label class="form-label" for="first-name">First name</label><input id="first-name" class="form-control" name="first_name" value="<?php echo e(old('first_name', $user->first_name)); ?>" required><?php $__errorArgs = ['first_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                    <div class="form-group"><label class="form-label" for="middle-initial">Middle initial</label><input id="middle-initial" class="form-control" name="middle_initial" maxlength="2" value="<?php echo e(old('middle_initial', $user->middle_initial)); ?>"></div>
                    <div class="form-group"><label class="form-label" for="last-name">Last name</label><input id="last-name" class="form-control" name="last_name" value="<?php echo e(old('last_name', $user->last_name)); ?>" required><?php $__errorArgs = ['last_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                    <div class="form-group"><label class="form-label" for="email">Email</label><input id="email" class="form-control" value="<?php echo e($user->email); ?>" disabled></div>
                    <div class="form-group"><label class="form-label" for="contact-number">Contact number</label><input id="contact-number" class="form-control" name="contact_no" value="<?php echo e(old('contact_no', $user->contact_no)); ?>" required><?php $__errorArgs = ['contact_no'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                    <?php if($user->role === 'seller'): ?>
                        <div class="form-group"><label class="form-label" for="business-name">Business name</label><input id="business-name" class="form-control" name="business_name" value="<?php echo e(old('business_name', $user->business_name)); ?>" required></div>
                        <div class="form-group"><label class="form-label" for="line-of-business">Line of business</label><select id="line-of-business" class="form-control" name="line_of_business"><option value="">Not specified</option><?php $__currentLoopData = array_filter(array_unique([...config('web-commerce.categories'), app(\App\Services\ProductCategoryService::class)->label((string) $user->line_of_business)])); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($category); ?>" <?php if(app(\App\Services\ProductCategoryService::class)->label((string) old('line_of_business', $user->line_of_business)) === $category): echo 'selected'; endif; ?>><?php echo e($category); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
                    <?php endif; ?>
                    <?php if($user->role === 'courier'): ?>
                        <?php if (isset($component)) { $__componentOriginal2a13aa3c200396391be62a69d0753524 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a13aa3c200396391be62a69d0753524 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.vehicle-capacity','data' => ['rider' => $user]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('vehicle-capacity'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['rider' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a13aa3c200396391be62a69d0753524)): ?>
<?php $attributes = $__attributesOriginal2a13aa3c200396391be62a69d0753524; ?>
<?php unset($__attributesOriginal2a13aa3c200396391be62a69d0753524); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a13aa3c200396391be62a69d0753524)): ?>
<?php $component = $__componentOriginal2a13aa3c200396391be62a69d0753524; ?>
<?php unset($__componentOriginal2a13aa3c200396391be62a69d0753524); ?>
<?php endif; ?><p class="web-muted">A logistics admin verifies your current vehicle's cargo capacity. Vehicle type or plate changes require capacity verification again before pickup or dispatch.</p>
                        <div class="form-group"><label class="form-label" for="vehicle-type">Vehicle type</label><select id="vehicle-type" class="form-control" name="vehicle_type"><option value="">Not specified</option><?php $__currentLoopData = array_filter(array_unique([...array_keys(config('rider-vehicles.types')), $user->vehicle_type])); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($type); ?>" <?php if(old('vehicle_type', $user->vehicle_type) === $type): echo 'selected'; endif; ?>><?php echo e($type); ?><?php echo e(isset(config('rider-vehicles.types')[$type]) ? ' ('.config('rider-vehicles.categories')[config('rider-vehicles.types')[$type]].')' : ''); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
                        <div class="form-group"><label class="form-label" for="plate-number">Plate number</label><input id="plate-number" class="form-control" name="plate_number" value="<?php echo e(old('plate_number', $user->plate_number)); ?>"></div>
                        <div class="form-group"><label class="form-label" for="vehicle-category">Vehicle category</label><select id="vehicle-category" class="form-control" name="vehicle_category"><option value="">Not specified</option><?php $__currentLoopData = config('rider-vehicles.categories'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if(old('vehicle_category', $user->vehicle_category) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
                        <div class="form-group"><label class="form-label" for="vehicle-description">Vehicle details</label><input id="vehicle-description" class="form-control" name="vehicle_description" maxlength="120" value="<?php echo e(old('vehicle_description', $user->vehicle_description)); ?>"></div>
                        <div class="form-group"><label class="form-label" for="license-number">License number</label><input id="license-number" class="form-control" name="license_number" pattern="[A-Za-z][0-9]{2}-[0-9]{2}-[0-9]{6}" placeholder="A00-00-000000" value="<?php echo e(old('license_number', $user->license_number)); ?>"></div>
                        <div class="form-group"><label class="form-label" for="license-expiry">License expiry</label><input id="license-expiry" class="form-control" type="date" name="license_expires_at" value="<?php echo e(old('license_expires_at', $user->license_expires_at?->format('Y-m-d'))); ?>"></div>
                        <h3>Active service areas</h3><ul><?php $__empty_1 = true; $__currentLoopData = $user->serviceAreas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $area): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><li><?php echo e($area->name); ?><?php echo e($area->pivot->is_primary ? ' (primary)' : ''); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><li>No service areas assigned. Contact Logistics.</li><?php endif; ?></ul>
                    <?php endif; ?>
                    <?php echo $__env->make('components.psgc-address-fields', ['prefix' => 'account-address', 'address' => $user, 'postalRequired' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    <button class="btn-submit" type="submit"><?php echo e(in_array($user->role, ['seller', 'courier', 'sorting_center'], true) ? 'Request profile update' : 'Save profile'); ?></button>
                </form>
            </section>

            <section class="dash-panel u-extracted-ebdbcd4a73">
                <h2>Change password</h2>
                <form action="<?php echo e(route('account.password.update')); ?>" method="POST" data-decision="account.password.update" data-decision-title="Change your password?" data-decision-message="Use the new password the next time you sign in. Check that you have entered it correctly." data-decision-label="Change password" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PATCH'); ?>
                    <?php $__currentLoopData = [['current-password', 'Current password', 'current_password', 'current-password'], ['new-password', 'New password', 'password', 'new-password'], ['new-password-confirmation', 'Confirm new password', 'password_confirmation', 'new-password']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$id, $label, $name, $autocomplete]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="form-group"><label class="form-label" for="<?php echo e($id); ?>"><?php echo e($label); ?></label><div class="credential-password-wrap"><input id="<?php echo e($id); ?>" type="password" class="form-control" name="<?php echo e($name); ?>" required autocomplete="<?php echo e($autocomplete); ?>"><button class="credential-password-toggle" type="button" data-password-toggle="<?php echo e($id); ?>" aria-label="Show password" aria-pressed="false">Show</button></div><?php $__errorArgs = [$name];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <button class="btn-submit" type="submit">Change password</button>
                </form>
            </section>

            <section class="dash-panel u-extracted-ebdbcd4a73">
                <h2>Private documents</h2>
                <ul>
                    <?php if($user->id_path || $user->id_upload_path): ?><li><a href="<?php echo e(route('account.documents.show', 'identity')); ?>">Download identity document</a></li><?php endif; ?>
                    <?php if($user->permit_path || $user->business_permit_path): ?><li><a href="<?php echo e(route('account.documents.show', 'permit')); ?>">Download business permit</a></li><?php endif; ?>
                    <?php if($user->license_path): ?><li><a href="<?php echo e(route('account.documents.show', 'license')); ?>">Download driver's licence</a></li><?php endif; ?>
                    <?php if($user->or_cr_path || $user->or_cr_upload_path): ?><li><a href="<?php echo e(route('account.documents.show', 'vehicle')); ?>">Download OR/CR</a></li><?php endif; ?>
                </ul>
            </section>
        </main>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/account/edit.blade.php ENDPATH**/ ?>