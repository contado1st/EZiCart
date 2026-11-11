<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/auth-fallback.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('title', 'Sign in | EZiCart'); ?>

<?php $__env->startSection('content'); ?>
    <main class="auth-page">
        <section class="auth-shell" aria-labelledby="auth-title">
            <aside class="auth-aside">
                <a class="auth-brand" href="<?php echo e(route('home')); ?>" aria-label="EZiCart home">EZiCart<span>.</span></a>
                <div class="auth-aside-copy">
                    <p class="auth-kicker">Your marketplace, in one place</p>
                    <h1>Good finds start here.</h1>
                    <p>Sign in to continue shopping, manage your store, or pick up where you left off.</p>
                </div>
                <p class="auth-aside-foot">Made for local shopping in the Philippines.</p>
            </aside>

            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="<?php echo e(route('home')); ?>">EZiCart<span>.</span></a></div>
                <h2 id="auth-title">Sign in to EZiCart</h2>
                <p class="auth-intro">Use the email address and password for your account.</p>

                <?php if(session('success')): ?>
                    <div class="auth-message auth-message--success" role="status"><?php echo e(session('success')); ?></div>
                <?php endif; ?>
                <?php if(session('status')): ?>
                    <div class="auth-message" role="status"><?php echo e(session('status')); ?></div>
                <?php endif; ?>
                <?php if(session('error')): ?>
                    <div class="auth-message auth-message--error" role="alert"><?php echo e(session('error')); ?></div>
                <?php endif; ?>
                <?php if($errors->any()): ?>
                    <div class="auth-message auth-message--error" role="alert"><?php echo e($errors->first()); ?></div>
                <?php endif; ?>

                <form action="<?php echo e(route('login.post')); ?>" method="POST" class="auth-form">
                    <?php echo csrf_field(); ?>
                    <div class="auth-field">
                        <label for="email">Email address</label>
                        <input id="email" type="email" name="email" value="<?php echo e(old('email')); ?>" autocomplete="email" autocapitalize="none" spellcheck="false" maxlength="255" data-email-character-limit required autofocus>
                        <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="auth-field">
                        <div class="auth-label-row"><label for="password">Password</label><a href="<?php echo e(route('password.request')); ?>">Forgot password?</a></div>
                        <div class="auth-password-wrap">
                            <input id="password" type="password" name="password" autocomplete="current-password" required>
                            <button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button>
                        </div>
                        <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <label class="auth-check"><input type="checkbox" name="remember" value="1" <?php if(old('remember')): echo 'checked'; endif; ?>><span><?php echo e(request()->query('__tab') ? 'Keep this tab signed in' : 'Keep me signed in on this device'); ?></span></label>
                    <button class="auth-submit" type="submit">Sign in</button>
                </form>
                <p class="auth-switch">New to EZiCart? <a href="<?php echo e(route('register')); ?>">Create a buyer account</a></p>
            </div>
        </section>
    </main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/auth/login.blade.php ENDPATH**/ ?>