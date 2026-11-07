<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/auth-fallback.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('title', 'Verify Your Email | EZiCart'); ?>

<?php $__env->startSection('content'); ?>
    <?php ($isPasswordReset = ($verificationMode ?? 'registration') === 'password-reset'); ?>
    <main class="auth-page">
        <section class="auth-shell auth-shell--verify" aria-labelledby="auth-title">
            <aside class="auth-aside">
                <a class="auth-brand" href="<?php echo e(route('home')); ?>" aria-label="EZiCart home">EZiCart<span>.</span></a>
                <div class="auth-aside-copy">
                    <p class="auth-kicker">Account Security</p>
                    <h1><?php echo e($isPasswordReset ? 'Recover your account.' : 'One last step.'); ?></h1>
                    <p><?php echo e($isPasswordReset ? 'Confirm it is you before choosing a new password.' : 'Verify your email address to protect your account and start using EZiCart.'); ?></p>
                </div>
                <p class="auth-aside-foot">Philippine commerce, verified and secured.</p>
            </aside>

            <div class="auth-content">
                <div class="auth-mobile-brand"><a class="auth-brand" href="<?php echo e(route('home')); ?>">EZiCart<span>.</span></a></div>
                <h2 id="auth-title"><?php echo e($isPasswordReset ? 'Verify Password Recovery' : 'Verify Your Email'); ?></h2>
                <p class="auth-intro">
                    <?php echo e($isPasswordReset ? 'We sent a 6-digit password recovery code to' : "We've sent a 6-digit verification code to"); ?>

                    <span class="auth-target-email"><?php echo e($email ?: 'your registered email address'); ?></span>.
                    <?php echo e($isPasswordReset ? 'Enter it to verify your identity and continue resetting your password.' : 'Enter the code below to complete verification.'); ?>

                </p>

                
                <?php if(session('success')): ?>
                    <div class="auth-message auth-message--success" role="status"><?php echo e(session('success')); ?></div>
                <?php endif; ?>
                <?php if(session('status')): ?>
                    <div class="auth-message auth-message--success" role="status"><?php echo e(session('status')); ?></div>
                <?php endif; ?>
                <?php if(session('error')): ?>
                    <div class="auth-message auth-message--error" role="alert"><?php echo e(session('error')); ?></div>
                <?php endif; ?>
                <?php if($errors->any()): ?>
                    <div class="auth-message auth-message--error" role="alert">
                        <strong>Verification notice:</strong>
                        <span><?php echo e($errors->first()); ?></span>
                    </div>
                <?php endif; ?>

                
                <form action="<?php echo e($isPasswordReset ? route('password.otp.verify') : route('verification.verify')); ?>" method="POST" class="auth-form" id="verify-form" novalidate>
                    <?php echo csrf_field(); ?>
                    
                    <?php if($email && ! $isPasswordReset): ?>
                        <input type="hidden" name="email" id="verify-email" value="<?php echo e($email); ?>">
                    <?php elseif(! $email): ?>
                        <div class="auth-field">
                            <label for="input-email">Registered email address</label>
                            <input id="input-email" type="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus autocomplete="email" placeholder="name@example.com">
                            <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                    <?php endif; ?>

                    <div class="auth-field">
                        <label for="otp-code">6-Digit Verification Code</label>
                        <input
                            id="otp-code"
                            type="text"
                            name="code"
                            class="auth-otp-input"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            autocomplete="one-time-code"
                            placeholder="••••••"
                            value="<?php echo e(old('code')); ?>"
                            required
                            <?php if($email): ?> autofocus <?php endif; ?>
                        >
                        <small class="auth-help">Enter the 6 numbers sent to your email. Expires in 5 minutes.</small>
                        <?php $__errorArgs = ['code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="auth-field-error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>

                    <button class="auth-submit" type="submit" id="verify-btn">
                        <span id="verify-btn-text">Verify OTP</span>
                    </button>
                </form>

                
                <div class="auth-resend">
                    <form action="<?php echo e($isPasswordReset ? route('password.otp.resend') : route('verification.resend')); ?>" method="POST" id="resend-form">
                        <?php echo csrf_field(); ?>
                        <?php if(! $isPasswordReset): ?>
                            <input type="hidden" name="email" id="resend-email" value="<?php echo e($email); ?>">
                        <?php endif; ?>
                        <p class="auth-help" style="margin-bottom: 0.5rem;">Didn’t receive the code? Check your spam folder or resend.</p>
                        <button type="submit" id="resend-btn" <?php if($cooldownSeconds > 0): echo 'disabled'; endif; ?>>
                            <span id="resend-btn-text">
                                <?php if($cooldownSeconds > 0): ?>
                                    Resend OTP in <span id="cooldown-timer"><?php echo e($cooldownSeconds); ?></span>s
                                <?php else: ?>
                                    Resend OTP
                                <?php endif; ?>
                            </span>
                        </button>
                    </form>
                </div>

                <p class="auth-switch"><?php echo e($isPasswordReset ? 'Need to start over?' : 'Already verified or need another account?'); ?> <a href="<?php echo e($isPasswordReset ? route('password.request') : route('login')); ?>"><?php echo e($isPasswordReset ? 'Back to password recovery' : 'Back to sign in'); ?></a></p>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var cooldownSeconds = <?php echo e((int) $cooldownSeconds); ?>;
            var timerSpan = document.getElementById('cooldown-timer');
            var resendBtn = document.getElementById('resend-btn');
            var resendText = document.getElementById('resend-btn-text');
            var verifyForm = document.getElementById('verify-form');
            var verifyBtn = document.getElementById('verify-btn');
            var verifyBtnText = document.getElementById('verify-btn-text');
            var resendForm = document.getElementById('resend-form');
            var otpInput = document.getElementById('otp-code');
            var inputEmail = document.getElementById('input-email');
            var resendEmail = document.getElementById('resend-email');

            // Synchronize email if entered manually
            if (inputEmail && resendEmail) {
                inputEmail.addEventListener('input', function () {
                    resendEmail.value = inputEmail.value.trim();
                });
            }

            // Sanitize OTP input to digits only
            if (otpInput) {
                otpInput.addEventListener('input', function () {
                    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
                });
            }

            // Cooldown countdown timer
            if (cooldownSeconds > 0 && resendBtn && timerSpan) {
                var interval = setInterval(function () {
                    cooldownSeconds--;
                    if (cooldownSeconds <= 0) {
                        clearInterval(interval);
                        resendBtn.disabled = false;
                        resendText.textContent = 'Resend OTP';
                    } else {
                        timerSpan.textContent = cooldownSeconds;
                    }
                }, 1000);
            }

            // Loading state on verification submit
            if (verifyForm && verifyBtn) {
                verifyForm.addEventListener('submit', function () {
                    if (otpInput && otpInput.value.trim().length === 6) {
                        verifyBtn.disabled = true;
                        verifyBtnText.textContent = 'Verifying OTP…';
                    }
                });
            }

            // Loading state on resend submit
            if (resendForm && resendBtn) {
                resendForm.addEventListener('submit', function () {
                    resendBtn.disabled = true;
                    resendText.textContent = 'Sending new OTP…';
                });
            }
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/auth/verify-email.blade.php ENDPATH**/ ?>