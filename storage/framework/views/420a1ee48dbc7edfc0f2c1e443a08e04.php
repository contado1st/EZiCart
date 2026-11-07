<?php
    $sidebarUser = auth()->user();
    $navigation = match ($sidebarUser->role) {
        'buyer' => [
            'Shopping' => [
                ['buyer.dashboard', 'diagram', 'My purchases', 'buyer.dashboard'],
                ['home', 'package1', 'Marketplace', 'home'],
                ['buyer.for-you', 'star', 'For you', 'buyer.for-you'],
                ['buyer.stock.index', 'bell', 'Stock alerts', 'buyer.stock.*'],
                ['chat.index', 'chat', 'Chat', 'chat.index'],
                ['cart.index', 'cart2', 'My cart', 'cart.*'],
                ['finance.index', 'invoice', 'Financial records', 'finance.*'],
            ],
        ],
        'seller' => [
            'Store management' => [
                ['seller.dashboard', 'diagram', 'Overview', 'seller.dashboard'],
                ['seller.orders.index', 'cart1', 'Orders', 'seller.orders.*'],
                ['seller.products.index', 'package1', 'Products & inventory', 'seller.products.*'],
                ['seller.vouchers.index', 'voucher', 'Vouchers', 'seller.vouchers.*'],
                ['seller.reports.index', 'note', 'Reports', 'seller.reports.*'],
                ['sales-reports.index', 'note', 'Sales exports', 'sales-reports.*'],
                ['chat.index', 'chat', 'Chat', 'chat.index'],
                ['finance.index', 'money', 'Financial records', 'finance.*'],
            ],
        ],
        'courier' => [
            'Communication' => [
                ['chat.index', 'chat', 'Messages', 'chat.index'],
            ],
            'Delivery operations' => [
                ['courier.dashboard', 'diagram', 'Work board', 'courier.dashboard'],
                ['courier.tracking', 'delivery1', 'Active parcels', 'courier.tracking'],
                ['courier.history', 'checklist', 'Delivery history', 'courier.history'],
                ['courier.earnings', 'money', 'Earnings', 'courier.earnings'],
                ['finance.index', 'invoice', 'Cash remittances', 'finance.*'],
            ],
        ],
        'sorting_center' => [
            'Hub operations' => [
                ['logistics.dashboard', 'diagram', 'Overview', 'logistics.dashboard'],
                ['logistics.pickupRequests', 'delivery1', 'Pickup requests', 'logistics.pickupRequests'],
                ['logistics.intake', 'package1', 'Parcel intake', 'logistics.intake'],
                ['logistics.sorting', 'checklist', 'Sorting queue', 'logistics.sorting'],
                ['logistics.dispatch', 'delivery1', 'Rider dispatch', 'logistics.dispatch'],
                ['logistics.tracking', 'location', 'Delivery tracking', 'logistics.tracking'],
            ],
            'Hub management' => [
                ['logistics.storage', 'package1', 'Storage locations', 'logistics.storage'],
                ['parcel-transfers.index', 'delivery1', 'Hub transfers', 'parcel-transfers.*'],
                ['logistics.scan-history', 'note', 'Scan history', 'logistics.scan-history'],
                ['logistics.riders', 'account', 'Riders', 'logistics.riders'],
                ['logistics.areas', 'location', 'Routing areas', 'logistics.areas*'],
                ['coverage.index', 'location', 'Barangays & vehicles', 'coverage.*'],
                ['logistics.reports', 'diagram', 'Reports', 'logistics.reports'],
            ],
        ],
        'logistics_admin' => [
            'Logistics management' => [
                ['logistics-admin.dashboard', 'diagram', 'Control tower', 'logistics-admin.dashboard'],
                ['chat.index', 'chat', 'Chat', 'chat.*'],
                ['logistics-admin.dashboard#applications', 'shield', 'Applications', ''],
                ['logistics-admin.dashboard#service-areas', 'location', 'Service areas', ''],
                ['coverage.index', 'location', 'Coverage & shipping', 'coverage.*'],
                ['activity-logs.index', 'note', 'Activity logs', 'activity-logs.*'],
                ['logistics-admin.dashboard#managed-accounts', 'account', 'Centers & riders', ''],
                ['logistics-admin.dashboard#parcels', 'package1', 'Parcel oversight', ''],
                ['parcel-transfers.index', 'delivery1', 'Hub transfers', 'parcel-transfers.*'],
                ['finance.index', 'money', 'COD & remittances', 'finance.*'],
            ],
        ],
        default => [
            'Marketplace' => [
                ['admin.dashboard', 'diagram', 'Overview', 'admin.dashboard'],
                ['chat.index', 'chat', 'Chat', 'chat.*'],
                ['admin.registrations.index', 'shield', 'User approvals', 'admin.registrations.*'],
                ['admin.compliance.products.index', 'package1', 'Product compliance', 'admin.compliance.*'],
                ['admin.disputes.index', 'warning', 'Disputes', 'admin.disputes.*'],
                ['admin.vouchers.index', 'voucher', 'Platform vouchers', 'admin.vouchers.*'],
                ['sales-reports.index', 'note', 'Sales exports', 'sales-reports.*'],
                ['activity-logs.index', 'note', 'Activity logs', 'activity-logs.*'],
                ['finance.index', 'money', 'Refunds & payouts', 'finance.*'],
            ],
            'Administration' => [
                ['admin.reports.index', 'diagram', 'Reports', 'admin.reports.*'],
                ['admin.moderation.index', 'shield', 'Account moderation', 'admin.moderation.*'],
                ['admin.announcements.index', 'megaphone', 'Announcements', 'admin.announcements.*'],
            ],
        ],
    };
    $navigation['Insights'] = [['analytics.index', 'diagram', 'Analytics', 'analytics.*']];
    $navigation['Parcel records'] = [['parcel-evidence.index', 'package1', 'Journey & evidence', 'parcel-evidence.*']];
    if (in_array($sidebarUser->role, ['seller', 'courier', 'sorting_center', 'logistics_admin'], true)) $navigation['Planning'] = [['load-planning.index', 'delivery1', 'Weight & load planning', 'load-planning.*']];
    if ($sidebarUser->role === 'courier' && $sidebarUser->hub_transfer_only) {
        $navigation['Delivery operations'] = [['parcel-transfers.index', 'delivery1', 'Assigned hub transfers', 'parcel-transfers.*']];
    }
    if ($sidebarUser->isCommerceAdmin() || $sidebarUser->isLogisticsAdmin()) {
        $navigation['Staff'] = [['staff.index', 'account', 'Staff profiles', 'staff.index']];
        $navigation['Staff'][] = [$sidebarUser->isCommerceAdmin() ? 'admin.profile-reviews.index' : 'logistics-admin.profile-reviews.index', 'shield', 'Profile change approvals', $sidebarUser->isCommerceAdmin() ? 'admin.profile-reviews.*' : 'logistics-admin.profile-reviews.*'];
    }
    if ($sidebarUser->role === 'buyer') $navigation['Offers'] = [['buyer.vouchers.index', 'voucher', 'My vouchers', 'buyer.vouchers.*']];
    if ($sidebarUser->role === 'seller') $navigation['Shop settings'] = [['seller.availability.edit', 'settings', 'Availability', 'seller.availability.*'], ['seller.promotions.index', 'megaphone', 'Promotions', 'seller.promotions.*']];
    if ($sidebarUser->isCommerceAdmin()) $navigation['Administration'][] = ['admin.promotions.index', 'megaphone', 'Seller promotions', 'admin.promotions.*'];
    $sidebarRole = match ($sidebarUser->role) {
        'admin', 'ecommerce_admin' => 'E-commerce admin',
        'logistics_admin' => 'Logistics admin',
        'sorting_center' => 'Sorting center',
        default => ucfirst($sidebarUser->role),
    };
?>
<aside class="workspace-sidebar" id="workspace-sidebar" aria-label="<?php echo e($sidebarRole); ?> navigation">
    <div class="workspace-sidebar-heading"><span class="workspace-role-dot" aria-hidden="true"></span><span class="workspace-nav-label"><?php echo e($sidebarRole); ?></span><button type="button" class="workspace-icon-button workspace-mobile-close" data-workspace-close aria-label="Close navigation"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'close']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'close']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></button></div>
    <nav class="workspace-navigation">
        <?php $__currentLoopData = $navigation; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="workspace-nav-group"><p class="workspace-nav-heading"><?php echo e($group); ?></p>
                <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$destination, $icon, $label, $pattern]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        [$destinationRoute, $destinationAnchor] = array_pad(explode('#', $destination, 2), 2, null);
                        $isActive = $pattern !== '' && request()->routeIs($pattern);
                    ?>
                    <a href="<?php echo e(route($destinationRoute)); ?><?php echo e($destinationAnchor ? '#'.$destinationAnchor : ''); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['workspace-nav-link', 'is-active' => $isActive]); ?>" <?php if($isActive): ?> aria-current="page" <?php endif; ?> title="<?php echo e($label); ?>" <?php if($destinationRoute === 'chat.index'): ?> data-communication-label="<?php echo e($label); ?>" aria-label="<?php echo e($label); ?><?php echo e($communicationCounts['messages'] ? ', '.$communicationCounts['messages'].' unread' : ''); ?>" <?php else: ?> aria-label="<?php echo e($label); ?>" <?php endif; ?>><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => $icon]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><span class="workspace-nav-label"><?php echo e($label); ?></span><?php if($destinationRoute === 'chat.index'): ?><?php if (isset($component)) { $__componentOriginal8b79293dbbc0b4c049b98954b045a955 = $component; } ?>
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
<?php endif; ?><?php endif; ?></a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <div class="workspace-nav-group"><p class="workspace-nav-heading">Account</p>
            <a href="<?php echo e(route('login', ['__tab' => bin2hex(random_bytes(16)), '__new_account' => 1])); ?>" target="_blank" rel="noopener" data-tab-session="new-tab" class="workspace-nav-link" title="Sign in another account in a new tab"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'account']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'account']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><span class="workspace-nav-label">Sign in another account</span></a>
            <?php if($sidebarUser->role === 'seller' && $sidebarUser->status === 'approved'): ?><a class="workspace-nav-link" href="<?php echo e(route('shops.show', $sidebarUser)); ?>" title="View my shop"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'package1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'package1']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><span class="workspace-nav-label">View my shop</span></a><?php endif; ?>
            <a href="<?php echo e(route('notifications.index')); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['workspace-nav-link', 'is-active' => request()->routeIs('notifications.*')]); ?>" title="Notifications" data-communication-label="Notifications" aria-label="Notifications<?php echo e($communicationCounts['notifications'] ? ', '.$communicationCounts['notifications'].' unread' : ''); ?>"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?><span class="workspace-nav-label">Notifications</span><?php if (isset($component)) { $__componentOriginal8b79293dbbc0b4c049b98954b045a955 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8b79293dbbc0b4c049b98954b045a955 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.communication-count','data' => ['kind' => 'notifications','count' => $communicationCounts['notifications']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('communication-count'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['kind' => 'notifications','count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($communicationCounts['notifications'])]); ?>
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
            <a href="<?php echo e(route('account.profile.edit')); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['workspace-nav-link', 'is-active' => request()->routeIs('account.profile.*')]); ?>" title="Account & profile"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'settings']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'settings']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><span class="workspace-nav-label">Account & profile</span></a>
            <?php if($sidebarUser->role === 'buyer'): ?>
                <a href="<?php echo e(route('buyer.addresses.index')); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['workspace-nav-link', 'is-active' => request()->routeIs('buyer.addresses.*')]); ?>" title="Addresses" <?php if(request()->routeIs('buyer.addresses.*')): ?> aria-current="page" <?php endif; ?>><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'location']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'location']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><span class="workspace-nav-label">Addresses</span></a>
                <a href="<?php echo e(route('account.profile.edit')); ?>#buyer-password" class="workspace-nav-link" title="Change password"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'lock']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'lock']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><span class="workspace-nav-label">Change password</span></a>
            <?php endif; ?>
        </div>
    </nav>
    <div class="workspace-sidebar-footer">
        <a href="<?php echo e(route('account.profile.edit')); ?>" class="workspace-user-link"><span class="workspace-avatar"><?php if (isset($component)) { $__componentOriginal32077e48dfcf7eeccbd729b994858fc5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal32077e48dfcf7eeccbd729b994858fc5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.profile-avatar','data' => ['user' => $sidebarUser]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('profile-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($sidebarUser)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal32077e48dfcf7eeccbd729b994858fc5)): ?>
<?php $attributes = $__attributesOriginal32077e48dfcf7eeccbd729b994858fc5; ?>
<?php unset($__attributesOriginal32077e48dfcf7eeccbd729b994858fc5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal32077e48dfcf7eeccbd729b994858fc5)): ?>
<?php $component = $__componentOriginal32077e48dfcf7eeccbd729b994858fc5; ?>
<?php unset($__componentOriginal32077e48dfcf7eeccbd729b994858fc5); ?>
<?php endif; ?></span><span class="workspace-nav-label"><strong><?php echo e($sidebarUser->first_name); ?> <?php echo e($sidebarUser->last_name); ?></strong><small><?php echo e($sidebarRole); ?></small></span></a>
        <form action="<?php echo e(route('logout')); ?>" method="POST" data-decision="logout" data-decision-title="Sign out?" data-decision-message="You will leave your current session. You can sign in again when you are ready." data-decision-label="Sign out" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?><button type="submit" class="workspace-icon-button" title="Sign out" aria-label="Sign out"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?></button></form>
    </div>
</aside>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/workspace-sidebar.blade.php ENDPATH**/ ?>