<?php
    $activeSection = $activeSection ?? 'profile';
    $buyer = auth()->user();
    $unreadNotificationCount = $buyer->unreadNotifications()->count();
?>

<aside class="buyer-account-sidebar" aria-label="My Account navigation">
    <div class="buyer-account-person">
        <?php if (isset($component)) { $__componentOriginal32077e48dfcf7eeccbd729b994858fc5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal32077e48dfcf7eeccbd729b994858fc5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.profile-avatar','data' => ['user' => $buyer,'class' => 'buyer-account-avatar','ariaHidden' => 'true']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('profile-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($buyer),'class' => 'buyer-account-avatar','aria-hidden' => 'true']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal32077e48dfcf7eeccbd729b994858fc5)): ?>
<?php $attributes = $__attributesOriginal32077e48dfcf7eeccbd729b994858fc5; ?>
<?php unset($__attributesOriginal32077e48dfcf7eeccbd729b994858fc5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal32077e48dfcf7eeccbd729b994858fc5)): ?>
<?php $component = $__componentOriginal32077e48dfcf7eeccbd729b994858fc5; ?>
<?php unset($__componentOriginal32077e48dfcf7eeccbd729b994858fc5); ?>
<?php endif; ?>
        <div>
            <strong><?php echo e($buyer->first_name); ?> <?php echo e($buyer->last_name); ?></strong>
            <a href="<?php echo e(route('account.profile.edit')); ?>#buyer-profile">Edit profile</a>
        </div>
    </div>

    <nav>
        <p class="buyer-account-nav-heading">My Account</p>
        <a class="<?php echo e($activeSection === 'profile' ? 'is-active' : ''); ?>" href="<?php echo e(route('account.profile.edit')); ?>" <?php if($activeSection === 'profile'): ?> aria-current="page" <?php endif; ?>>Profile</a>
        <a href="<?php echo e(route('buyer.addresses.index')); ?>">Addresses</a>
        <a href="<?php echo e(route('account.profile.edit')); ?>#buyer-password">Change password</a>
        <a class="buyer-account-notification-link <?php echo e($activeSection === 'notifications' ? 'is-active' : ''); ?>" href="<?php echo e(route('notifications.index')); ?>" <?php if($activeSection === 'notifications'): ?> aria-current="page" <?php endif; ?>>
            <span>Notifications</span>
            <?php if($unreadNotificationCount > 0): ?>
                <span class="buyer-account-notification-count" aria-label="<?php echo e($unreadNotificationCount); ?> unread"><?php echo e($unreadNotificationCount); ?></span>
            <?php endif; ?>
        </a>

        <p class="buyer-account-nav-heading">My EZiCart</p>
        <a class="<?php echo e($activeSection === 'purchases' ? 'is-active' : ''); ?>" href="<?php echo e(route('buyer.dashboard')); ?>" <?php if($activeSection === 'purchases'): ?> aria-current="page" <?php endif; ?>>My purchases</a>
        <a href="<?php echo e(route('cart.index')); ?>">My cart</a>
    </nav>

    <form action="<?php echo e(route('logout')); ?>" method="POST" class="buyer-account-sidebar-logout" data-decision="logout" data-decision-title="Sign out?" data-decision-message="You will leave your current session. You can sign in again when you are ready." data-decision-label="Sign out" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
        <?php echo csrf_field(); ?>
        <button type="submit">Log out</button>
    </form>
</aside>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/buyer-account-sidebar.blade.php ENDPATH**/ ?>