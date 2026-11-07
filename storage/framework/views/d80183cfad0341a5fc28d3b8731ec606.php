<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/auth-fallback.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('title', 'Choose a new password | EZiCart'); ?>

<?php $__env->startSection('content'); ?>
    <main class="auth-page">
        <section class="auth-shell auth-shell--verify" aria-labelledby="auth-title">
            <aside class="auth-aside"><a class="auth-brand" href="<?php echo e(route('home')); ?>">EZiCart<span>.</span></a><div class="auth-aside-copy"><h1>Choose a fresh password.</h1><p>Use a password you have not used on EZiCart before.</p></div></aside>
            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="<?php echo e(route('home')); ?>">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Choose a new password</h2>

                <form action="<?php echo e(route('password.update')); ?>" method="POST" class="auth-form">
                    <?php echo csrf_field(); ?>
                    <div class="auth-field">
                        <p class="auth-intro">Set a new password for <?php echo e($email); ?>.</p>
                        <?php if($errors->any()): ?>
                            <div class="auth-message auth-message--error" role="alert"><?php echo e($errors->first()); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="auth-field">
                        <label for="password">New password</label>
                        <div class="auth-password-wrap"><input id="password" type="password" name="password" required autocomplete="new-password"><button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button></div>
                        <small class="auth-help">At least 8 characters, with uppercase and lowercase letters, a number, and a symbol.</small>
                        <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <span class="auth-field-error"><?php echo e($message); ?></span>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="auth-field">
                        <label for="password-confirmation">Confirm new password</label>
                        <div class="auth-password-wrap"><input id="password-confirmation" type="password" name="password_confirmation" required autocomplete="new-password"><button class="auth-password-toggle" type="button" data-password-toggle="password-confirmation" aria-label="Show password" aria-pressed="false">Show</button></div>
                    </div>
                    <button type="submit" class="auth-submit">Reset password</button>
                </form>
            </div>
        </section>
    </main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/auth/reset-password.blade.php ENDPATH**/ ?>