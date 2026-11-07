<?php $__env->startSection('title', 'Notifications | EZiCart'); ?>
<?php $__env->startSection('communications-title', 'Notifications'); ?>
<?php $__env->startSection('communications-description', 'Order updates, messages and account activity, organized for you.'); ?>
<?php $__env->startSection('communications-content'); ?>
<div class="notification-frame">
    <aside class="notification-sidebar" aria-label="Notification categories"><h2>Your updates</h2><a href="<?php echo e(route('notifications.index')); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-active' => !request('category') || request('category') === 'all']); ?>"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> All updates <span><?php echo e($totalCount); ?></span></a>
        <?php $__currentLoopData = ['orders' => ['package1', 'Order updates'], 'messages' => ['chat', 'Messages'], 'stock' => ['bell', 'Stock alerts'], 'account' => ['account', 'Account activity']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category => [$icon, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('notifications.index', ['category' => $category, 'filter' => request('filter')])); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-active' => request('category') === $category]); ?>" <?php if(request('category') === $category): ?> aria-current="page" <?php endif; ?>><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => ''.e($icon).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => ''.e($icon).'']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> <?php echo e($label); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if(auth()->user()->role === 'buyer'): ?><a href="<?php echo e(route('notifications.index', ['category' => 'promotions'])); ?>"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'megaphone']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'megaphone']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> Shop promotions</a><form method="POST" action="<?php echo e(route('notifications.preferences')); ?>" class="web-form"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><input type="hidden" name="seller_promotions_enabled" value="0"><label><span><input type="checkbox" name="seller_promotions_enabled" value="1" <?php if(auth()->user()->seller_promotions_enabled): echo 'checked'; endif; ?>> Allow shop promotions</span></label><button class="notification-open" type="submit">Save preferences</button></form><?php endif; ?>
        <p>Private updates for your account. Open an update to view its details and mark it read.</p>
    </aside>
    <section class="notification-feed" aria-labelledby="notification-feed-title">
        <header class="notification-feed-heading"><div><h2 id="notification-feed-title"><?php echo e(request('category') && request('category') !== 'all' ? ucfirst(request('category')).' updates' : 'All updates'); ?></h2><p><?php echo e($unreadCount); ?> unread <?php echo e(\Illuminate\Support\Str::plural('notification', $unreadCount)); ?></p></div><form method="POST" action="<?php echo e(route('notifications.read-all')); ?>"><?php echo csrf_field(); ?><button class="communications-text-link" type="submit" <?php if(!$unreadCount): echo 'disabled'; endif; ?>>Mark all as read</button></form></header>
        <nav class="notification-filters" aria-label="Notification read status"><a href="<?php echo e(route('notifications.index', ['category' => request('category'), 'filter' => 'all'])); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-active' => request('filter') !== 'unread']); ?>" <?php if(request('filter') !== 'unread'): ?> aria-current="page" <?php endif; ?>>All</a><a href="<?php echo e(route('notifications.index', ['category' => request('category'), 'filter' => 'unread'])); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-active' => request('filter') === 'unread']); ?>" <?php if(request('filter') === 'unread'): ?> aria-current="page" <?php endif; ?>>Unread</a></nav>
        <div class="notification-items">
        <?php $__empty_1 = true; $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php if(($notification->data['category'] ?? null) === 'promotions'): ?><div class="web-promotion-label">ADVERTISEMENT · <?php echo e($notification->data['seller_name'] ?? 'Seller promotion'); ?> · <?php echo e($notification->data['title'] ?? 'Shop offer'); ?></div><?php endif; ?>
            <?php
                $notificationIcon = match($notification->type) {
                    \App\Notifications\OrderMessageNotification::class, \App\Notifications\DirectMessageNotification::class => 'chat',
                    \App\Notifications\StockAvailabilityNotification::class => 'bell',
                    \App\Notifications\OrderStatusNotification::class, \App\Notifications\OrderWorkflowNotification::class => 'package1',
                    default => 'account',
                };
            ?>
            <article class="<?php echo \Illuminate\Support\Arr::toCssClasses(['notification-item', 'is-unread' => !$notification->read_at]); ?>"><span class="notification-item-icon"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => ''.e($notificationIcon).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => ''.e($notificationIcon).'']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></span><div class="notification-item-copy"><div class="notification-item-title"><h3><?php echo e($notification->data['order_number'] ?? $notification->data['product_name'] ?? 'EZiCart update'); ?></h3><?php if(!$notification->read_at): ?><span class="notification-unread-dot" aria-label="Unread"></span><?php endif; ?></div><p><?php echo e($notification->data['message'] ?? 'You have a new update.'); ?></p><?php if(isset($notification->data['next_step'])): ?><p class="notification-next-step"><strong>Next step at this update:</strong> <?php echo e($notification->data['next_step']); ?></p><?php endif; ?><time datetime="<?php echo e($notification->created_at->toIso8601String()); ?>"><?php echo e($notification->created_at->format('M d, Y · h:i A')); ?></time></div><div class="notification-item-actions"><?php if(!empty($notification->data['url'])): ?><form method="POST" action="<?php echo e(route('notifications.open', $notification->id)); ?>"><?php echo csrf_field(); ?><button class="notification-open" type="submit">View update</button></form><?php endif; ?> <?php if(!$notification->read_at): ?><form method="POST" action="<?php echo e(route('notifications.read', $notification->id)); ?>"><?php echo csrf_field(); ?><button class="communications-text-link" type="submit">Mark read</button></form><?php else: ?><span class="notification-read-label">Read</span><?php endif; ?></div></article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="conversation-empty notification-empty"><span class="communications-empty-icon"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?></span><h2><?php echo e(request('filter') === 'unread' ? 'You’re all caught up' : 'No updates here yet'); ?></h2><p>New order, message and account updates will appear here.</p></div><?php endif; ?>
        </div>
        <?php if($notifications->hasPages()): ?><nav class="notification-pagination" aria-label="Notification pages"><?php echo e($notifications->links()); ?></nav><?php endif; ?>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.communications', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/notifications/index.blade.php ENDPATH**/ ?>