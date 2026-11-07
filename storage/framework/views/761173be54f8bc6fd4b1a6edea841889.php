<?php
    $workspaceUser = auth()->user();
    $workspaceName = match ($workspaceUser->role) {
        'buyer' => 'My purchases',
        'seller' => 'Seller workspace',
        'courier' => 'Courier workspace',
        'sorting_center' => 'Sorting center',
        'logistics_admin' => 'Logistics administration',
        default => 'Marketplace administration',
    };
    [$workspaceAction, $workspaceActionRoute] = match ($workspaceUser->role) {
        'buyer' => ['Browse marketplace', 'home'],
        'seller' => ['Add product', 'seller.products.create'],
        'courier' => ['Active parcels', 'courier.tracking'],
        'sorting_center' => ['Receive parcel', 'logistics.intake'],
        'logistics_admin' => ['Hub transfers', 'parcel-transfers.index'],
        default => ['Review applications', 'admin.registrations.index'],
    };
    $workspaceUnread = $communicationCounts['notifications'];
?>
<a class="workspace-skip" href="#workspace-content">Skip to content</a>
<header class="workspace-topbar">
    <div class="workspace-brand">
        <a href="<?php echo e($roleDashboardRoute ? route($roleDashboardRoute) : route($workspaceUser->role === 'seller' ? 'seller.dashboard' : 'buyer.dashboard')); ?>" aria-label="EZiCart dashboard">
            <img src="<?php echo e(asset('images/ezicart-logo.png')); ?>" alt="EZiCart">
        </a>
        <button class="workspace-icon-button workspace-menu-button" type="button" data-workspace-toggle aria-controls="workspace-sidebar" aria-expanded="true" aria-label="Toggle sidebar" title="Toggle sidebar">
            <svg class="workspace-hamburger" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
                <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>
    <div class="workspace-toolbar">
        <div class="workspace-breadcrumb"><span>Workspace</span><span aria-hidden="true">/</span><strong><?php echo e($workspaceName); ?></strong></div>
        <div class="workspace-toolbar-actions">
            <time class="workspace-date" datetime="<?php echo e(now()->toDateString()); ?>"><?php echo e(now()->format('D, d M Y')); ?></time>
            <a class="workspace-primary-action" href="<?php echo e(route($workspaceActionRoute)); ?>"><?php echo e($workspaceAction); ?><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?></a>
            <span class="workspace-toolbar-divider" aria-hidden="true"></span>
            <a class="workspace-icon-button" href="<?php echo e(route('chat.index')); ?>" data-message-drawer-toggle data-communication-label="Messages" aria-label="Messages<?php echo e($communicationCounts['messages'] ? ', '.$communicationCounts['messages'].' unread' : ''); ?>" title="Messages"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'chat']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chat']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><?php if (isset($component)) { $__componentOriginal8b79293dbbc0b4c049b98954b045a955 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8b79293dbbc0b4c049b98954b045a955 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.communication-count','data' => ['kind' => 'messages','count' => $communicationCounts['messages']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('communication-count'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['kind' => 'messages','count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($communicationCounts['messages'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8b79293dbbc0b4c049b98954b045a955)): ?>
<?php $attributes = $__attributesOriginal8b79293dbbc0b4c049b98954b045a955; ?>
<?php unset($__attributesOriginal8b79293dbbc0b4c049b98954b045a955); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8b79293dbbc0b4c049b98954b045a955)): ?>
<?php $component = $__componentOriginal8b79293dbbc0b4c049b98954b045a955; ?>
<?php unset($__componentOriginal8b79293dbbc0b4c049b98954b045a955); ?>
<?php endif; ?></a>
            <a class="workspace-icon-button" href="<?php echo e(route('notifications.index')); ?>" data-communication-label="Notifications" aria-label="Notifications<?php echo e($workspaceUnread ? ', '.$workspaceUnread.' unread' : ''); ?>" title="Notifications">
                <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'bell']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bell']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal8b79293dbbc0b4c049b98954b045a955 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8b79293dbbc0b4c049b98954b045a955 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.communication-count','data' => ['kind' => 'notifications','count' => $workspaceUnread]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('communication-count'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['kind' => 'notifications','count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($workspaceUnread)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8b79293dbbc0b4c049b98954b045a955)): ?>
<?php $attributes = $__attributesOriginal8b79293dbbc0b4c049b98954b045a955; ?>
<?php unset($__attributesOriginal8b79293dbbc0b4c049b98954b045a955); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8b79293dbbc0b4c049b98954b045a955)): ?>
<?php $component = $__componentOriginal8b79293dbbc0b4c049b98954b045a955; ?>
<?php unset($__componentOriginal8b79293dbbc0b4c049b98954b045a955); ?>
<?php endif; ?>
            </a>
            <a class="workspace-avatar" href="<?php echo e(route('account.profile.edit')); ?>" aria-label="Your profile" title="<?php echo e($workspaceUser->first_name); ?> <?php echo e($workspaceUser->last_name); ?>">
                <?php if (isset($component)) { $__componentOriginal32077e48dfcf7eeccbd729b994858fc5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal32077e48dfcf7eeccbd729b994858fc5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.profile-avatar','data' => ['user' => $workspaceUser]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('profile-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($workspaceUser)]); ?>
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
            </a>
        </div>
    </div>
</header>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/workspace-topbar.blade.php ENDPATH**/ ?>