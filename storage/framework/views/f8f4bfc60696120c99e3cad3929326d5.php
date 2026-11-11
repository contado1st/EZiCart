<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/auth-fallback.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('title', 'Activate rider account | EZiCart'); ?>

<?php $__env->startSection('content'); ?>
    <main class="auth-page">
        <section class="auth-shell auth-shell--verify" aria-labelledby="auth-title">
            <aside class="auth-aside"><a class="auth-brand" href="<?php echo e(route('home')); ?>">EZiCart<span>.</span></a><div class="auth-aside-copy"><h1>Set up your rider access.</h1><p>Choose a password to activate the account added by your logistics company.</p></div></aside>
            <div class="auth-content"><div class="auth-mobile-brand"><a class="auth-brand" href="<?php echo e(route('home')); ?>">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Activate your rider account</h2><p class="auth-intro">This link can be used once and expires 24 hours after it was sent.</p>
                <?php if($activationError): ?>
                    <div class="auth-message auth-message--error" role="alert"><?php echo e($activationError); ?></div>
                <?php else: ?>
                <?php if($errors->any()): ?><div class="auth-message auth-message--error" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?>
                <form action="<?php echo e(route('rider.activation.store')); ?>" method="POST" class="auth-form" data-auth-submit>
                    <?php echo csrf_field(); ?><input type="hidden" name="token" value="<?php echo e($token); ?>">
                    <div class="auth-field"><label for="password">Password</label><div class="auth-password-wrap"><input id="password" type="password" name="password" autocomplete="new-password" aria-describedby="password-hint" minlength="8" maxlength="128" required><button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button></div><small id="password-hint" class="auth-help">8–128 characters, with uppercase and lowercase letters, a number, and a symbol.</small><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                    <div class="auth-field"><label for="password_confirmation">Confirm password</label><div class="auth-password-wrap"><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" maxlength="128" required><button class="auth-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">Show</button></div></div>
                    <button class="auth-submit" type="submit">Activate account</button>
                </form>
                <?php endif; ?>
                <p class="auth-switch"><a href="<?php echo e(route('login')); ?>">Back to sign in</a></p>
            </div>
        </section>
    </main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/auth/activate-rider.blade.php ENDPATH**/ ?>