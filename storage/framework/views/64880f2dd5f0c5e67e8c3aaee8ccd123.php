<?php
    $roleDashboardRoute = match (auth()->user()?->role) {
        'admin', 'ecommerce_admin' => 'admin.dashboard',
        'logistics_admin' => 'logistics-admin.dashboard',
        'courier' => 'courier.dashboard',
        'sorting_center' => 'logistics.dashboard',
        default => null,
    };
    $isRoleWorkspaceRoute = request()->routeIs('courier.*', 'logistics.*');
    $workspaceLabel = auth()->user()?->role === 'courier' ? 'Courier workspace' : 'Logistics workspace';
    $isDashboardWorkspace = auth()->check() && request()->routeIs(
        'admin.*', 'seller.*', 'courier.*', 'logistics.*', 'logistics-admin.*',
        'buyer.dashboard', 'buyer.orders.*', 'buyer.addresses.*', 'finance.*', 'parcel-transfers.*',
        'account.profile.*', 'notifications.*', 'buyer.for-you*', 'buyer.stock.*', 'chat.*', 'sales-reports.*', 'activity-logs.*', 'coverage.*', 'analytics.*', 'staff.*', 'parcel-evidence.*', 'buyer.vouchers.*', 'load-planning.*'
    );
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <script src="<?php echo e(asset('js/tab-sessions.js')); ?>?v=<?php echo e(filemtime(public_path('js/tab-sessions.js'))); ?>" data-tab-login-url="<?php echo e(route('login')); ?>" data-tab-authenticated="<?php echo e(auth()->check() ? '1' : '0'); ?>" defer></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $__env->yieldContent('title', 'EZiCart - A Better Marketplace'); ?></title>
    <meta name="description" content="<?php echo $__env->yieldContent('meta-description', 'Shop available products from sellers on the EZiCart marketplace.'); ?>">
    <!-- Base Stylesheet -->
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/app.css'); ?>
    <!-- Platform Bulletins & Banners Stylesheet -->
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/platform-controls.css'); ?>
    <!-- Shared navigation style for projects running against an older Vite manifest -->
    <link rel="stylesheet"
        href="<?php echo e(asset('css/navigation-fallback.css')); ?>?v=<?php echo e(filemtime(public_path('css/navigation-fallback.css'))); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/psgc-address.css')); ?>?v=<?php echo e(filemtime(public_path('css/psgc-address.css'))); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/profiles.css')); ?>?v=<?php echo e(filemtime(public_path('css/profiles.css'))); ?>">
    <!-- Dynamic Page Stylesheets -->
    <?php echo $__env->yieldPushContent('styles'); ?>
    <?php if(auth()->check()): ?>
        <link rel="stylesheet" href="<?php echo e(asset('css/communication-badges.css')); ?>?v=<?php echo e(filemtime(public_path('css/communication-badges.css'))); ?>">
        <script src="<?php echo e(asset('js/communication-badges.js')); ?>?v=<?php echo e(filemtime(public_path('js/communication-badges.js'))); ?>" data-communication-count-url="<?php echo e(route('communications.counts')); ?>" defer></script>
        <link rel="stylesheet" href="<?php echo e(asset('css/workflow-progress.css')); ?>?v=<?php echo e(filemtime(public_path('css/workflow-progress.css'))); ?>">
        <script src="<?php echo e(asset('js/workflow-progress.js')); ?>?v=<?php echo e(filemtime(public_path('js/workflow-progress.js'))); ?>" data-workflow-url="<?php echo e(route('workflow.attention')); ?>" defer></script>
        <link rel="stylesheet" href="<?php echo e(asset('css/message-drawer.css')); ?>?v=<?php echo e(filemtime(public_path('css/message-drawer.css'))); ?>">
        <script src="<?php echo e(asset('js/message-drawer.js')); ?>?v=<?php echo e(filemtime(public_path('js/message-drawer.js'))); ?>" defer></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/roadmap-features.css')); ?>?v=<?php echo e(filemtime(public_path('css/roadmap-features.css'))); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/web-operations.css')); ?>?v=<?php echo e(filemtime(public_path('css/web-operations.css'))); ?>">
    <script src="<?php echo e(asset('js/order-deadlines.js')); ?>?v=<?php echo e(filemtime(public_path('js/order-deadlines.js'))); ?>" defer></script>
    <?php if($isDashboardWorkspace): ?>
        <link rel="stylesheet" href="<?php echo e(asset('css/workspace.css')); ?>?v=<?php echo e(filemtime(public_path('css/workspace.css'))); ?>">
        <script src="<?php echo e(asset('js/workspace.js')); ?>?v=<?php echo e(filemtime(public_path('js/workspace.js'))); ?>" defer></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/table-layout.css')); ?>?v=<?php echo e(filemtime(public_path('css/table-layout.css'))); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/responsive-layout.css')); ?>?v=<?php echo e(filemtime(public_path('css/responsive-layout.css'))); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/interaction-feedback.css')); ?>?v=<?php echo e(filemtime(public_path('css/interaction-feedback.css'))); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/loading-skeleton.css')); ?>?v=<?php echo e(filemtime(public_path('css/loading-skeleton.css'))); ?>">
    <script src="<?php echo e(asset('js/page-loading.js')); ?>?v=<?php echo e(filemtime(public_path('js/page-loading.js'))); ?>" defer></script>
    <?php echo app('Illuminate\Foundation\Vite')('resources/js/app.js'); ?>
    <script src="<?php echo e(asset('js/password-visibility.js')); ?>?v=<?php echo e(filemtime(public_path('js/password-visibility.js'))); ?>" defer></script>
    <script src="<?php echo e(asset('js/registration-name-inputs.js')); ?>?v=<?php echo e(filemtime(public_path('js/registration-name-inputs.js'))); ?>" defer></script>
    <script src="<?php echo e(asset('js/email-input-constraint.js')); ?>?v=<?php echo e(filemtime(public_path('js/email-input-constraint.js'))); ?>" defer></script>
    <script src="<?php echo e(asset('js/psgc-address.js')); ?>?v=<?php echo e(filemtime(public_path('js/psgc-address.js'))); ?>" defer></script>
    <script src="<?php echo e(asset('js/registration-wizard.js')); ?>?v=<?php echo e(filemtime(public_path('js/registration-wizard.js'))); ?>" defer></script>
</head>

<body class="<?php echo \Illuminate\Support\Arr::toCssClasses([
    'landing-page' => request()->routeIs('home'),
    'loading-editorial' => request()->routeIs('home') && !auth()->check(),
    'catalog-search-page' => request()->routeIs('search.index'),
    'role-workspace' => $isRoleWorkspaceRoute,
    'dashboard-workspace' => $isDashboardWorkspace,
    'workspace-logistics' => $isDashboardWorkspace && in_array(auth()->user()?->role, ['courier', 'sorting_center', 'logistics_admin'], true),
]); ?>">

    <?php if($isDashboardWorkspace): ?>
        <?php echo $__env->make('components.workspace-topbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php else: ?>
    <!-- Header / Navbar -->
    <header class="navbar<?php echo e($isRoleWorkspaceRoute ? ' navbar--workspace' : ''); ?>">
            <div class="container navbar-container">

                <!-- Brand Logo -->
                <a href="<?php echo e($roleDashboardRoute ? route($roleDashboardRoute) : route('home')); ?>" class="brand-logo">
                    <img src="<?php echo e(asset('images/ezicart-logo.png')); ?>" alt="EZiCart Logo" class="brand-logo-img">
                </a>

                <?php if($isRoleWorkspaceRoute): ?>
                    <p class="navbar-workspace-label"><?php echo e($workspaceLabel); ?></p>
                <?php elseif(!$roleDashboardRoute): ?>
                    <div class="search-wrapper">
                        <form action="<?php echo e(route('search.index')); ?>" method="GET" class="search-form" role="search">
                            <input type="search" name="search" value="<?php echo e(request('search')); ?>" maxlength="150" aria-label="Search products and shops"
                                placeholder="Search for products, shops and more" class="search-input">
                            <button type="submit" class="search-button">
                                Search
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Cart & Auth Navigation -->
                <?php if($isRoleWorkspaceRoute): ?>
                    <div class="auth-nav">
                        <a href="<?php echo e(route($roleDashboardRoute)); ?>" class="nav-link">
                            <?php echo e(auth()->user()->role === 'courier' ? 'Work board' : 'Overview'); ?>

                        </a>
                    </div>
                <?php else: ?>
                <div class="auth-nav">
                    <!-- Cart Link Visible Only to Guests and Buyers -->
                    <?php if(!auth()->check() || auth()->user()->role === 'buyer'): ?>
                        <a href="<?php echo e(route('cart.index')); ?>" class="nav-link cart-link">
                            <span><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'cart2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'cart2']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> Cart</span>
                            <span class="cart-count-badge" data-cart-count <?php if(count(session('cart', [])) < 1): ?> hidden <?php endif; ?>><?php echo e(count(session('cart', []))); ?></span>
                        </a>
                    <?php endif; ?>

                    <?php if(auth()->guard()->check()): ?>
                        <?php if(auth()->user()->role !== 'buyer'): ?>
                            <a href="<?php echo e(route('notifications.index')); ?>" class="nav-link"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> Notifications
                                <?php if(auth()->user()->unreadNotifications()->exists()): ?>
                                    <span class="cart-count-badge"><?php echo e(auth()->user()->unreadNotifications()->count()); ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo e(route('account.profile.edit')); ?>" class="nav-link">Account</a>
                        <?php endif; ?>
                        <?php if(in_array(auth()->user()->role, ['buyer', 'seller', 'courier', 'admin', 'ecommerce_admin', 'logistics_admin'], true)): ?>
                            <a href="<?php echo e(route('finance.index')); ?>" class="nav-link">Financial records</a>
                        <?php endif; ?>
                        <?php if(auth()->user()->role === 'seller'): ?>
                            <a href="<?php echo e(route('seller.dashboard')); ?>" class="btn-primary nav-btn-compact">Dashboard</a>
                        <?php elseif(auth()->user()->isCommerceAdmin()): ?>
                            <a href="<?php echo e(route('admin.dashboard')); ?>" class="btn-primary nav-btn-compact">Admin Panel</a>
                        <?php elseif(auth()->user()->isLogisticsAdmin()): ?>
                            <a href="<?php echo e(route('logistics-admin.dashboard')); ?>" class="btn-primary nav-btn-compact">Logistics control tower</a>
                        <?php elseif(auth()->user()->role === 'courier'): ?>
                            <a href="<?php echo e(route('courier.dashboard')); ?>" class="btn-primary nav-btn-compact">Deliveries</a>
                        <?php elseif(auth()->user()->role === 'sorting_center'): ?>
                            <a href="<?php echo e(route('logistics.dashboard')); ?>" class="btn-primary nav-btn-compact">Sorting Hub</a>
                        <?php else: ?>
                            <a href="<?php echo e(route('account.profile.edit')); ?>" class="btn-primary nav-btn-compact">My Account
                                <?php if(auth()->user()->unreadNotifications()->exists()): ?>
                                    <span class="cart-count-badge"><?php echo e(auth()->user()->unreadNotifications()->count()); ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>

                        <form action="<?php echo e(route('logout')); ?>" method="POST" class="logout-form-inline" data-decision="logout" data-decision-title="Sign out?" data-decision-message="You will leave your current session. You can sign in again when you are ready." data-decision-label="Sign out" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="nav-link btn-logout">Logout</button>
                        </form>
                    <?php else: ?>
                        <a href="<?php echo e(route('login')); ?>" class="nav-link">Login</a>
                        <a href="<?php echo e(route('register')); ?>" class="btn-primary">Register</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </header>
    <?php endif; ?>

    <!-- Global Platform Announcements -->
    <?php
        $activeAnnouncements = \App\Models\Announcement::activeForUser(auth()->user())
            ->latest()
            ->take(3)
            ->get();
    ?>

    <?php if($activeAnnouncements->isNotEmpty()): ?>
        <div class="<?php echo e($isRoleWorkspaceRoute ? 'ops-announcements' : 'container u-extracted-dab43fb936'); ?>">
            <?php $__currentLoopData = $activeAnnouncements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $announcement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="announcement-banner banner-type-<?php echo e($announcement->type); ?>">
                    <span class="u-extracted-888f29e5c3">
                        <?php if($announcement->type === 'urgent'): ?>
                            <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'exclamationtriangle']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'exclamationtriangle']); ?>
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
                        <?php elseif($announcement->type === 'warning'): ?>
                            <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'warning']); ?>
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
                        <?php elseif($announcement->type === 'maintenance'): ?>
                            <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?>
                        <?php else: ?>
                            <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?>
                        <?php endif; ?>
                    </span>
                    <div>
                        <strong><?php echo e($announcement->title); ?>:</strong> <?php echo e($announcement->content); ?>

                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

    <!-- Main Content Body -->
    <?php if($isDashboardWorkspace): ?>
        <div class="workspace-frame">
            <?php echo $__env->make('components.workspace-sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <button type="button" class="workspace-overlay" data-workspace-close aria-label="Close navigation" tabindex="-1" hidden></button>
            <div class="workspace-page" id="workspace-content" tabindex="-1" data-page-content>
                <?php echo $__env->make('components.workflow-attention', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php echo $__env->yieldContent('content'); ?>
            </div>
        </div>
    <?php else: ?>
        <main class="container main-content" data-page-content>
            <?php echo $__env->yieldContent('content'); ?>
        </main>
    <?php endif; ?>

    <!-- Footer -->
    <?php if(auth()->user()?->role === 'buyer' && !$isDashboardWorkspace): ?>
        <nav class="marketplace-chat-dock" aria-label="Shopping shortcuts">
            <?php if(auth()->user()->role === 'buyer'): ?><a href="<?php echo e(route('buyer.for-you')); ?>">For you</a><?php endif; ?>
        </nav>
    <?php endif; ?>
    <?php if (! ($isRoleWorkspaceRoute || $isDashboardWorkspace)): ?>
        <footer class="footer">
            <div class="container footer-text">
                &copy; <?php echo e(date('Y')); ?> EZiCart Marketplace. All rights reserved.
            </div>
        </footer>
    <?php endif; ?>

    <?php if(auth()->check() && in_array(auth()->user()->role, ['buyer', 'seller', 'courier', 'sorting_center', 'admin', 'ecommerce_admin', 'logistics_admin'], true)): ?>
        <?php echo $__env->make('components.message-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>

    <?php if (isset($component)) { $__componentOriginald33f2de6723ee7b34defb10d4515258c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald33f2de6723ee7b34defb10d4515258c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-loading','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-loading'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald33f2de6723ee7b34defb10d4515258c)): ?>
<?php $attributes = $__attributesOriginald33f2de6723ee7b34defb10d4515258c; ?>
<?php unset($__attributesOriginald33f2de6723ee7b34defb10d4515258c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald33f2de6723ee7b34defb10d4515258c)): ?>
<?php $component = $__componentOriginald33f2de6723ee7b34defb10d4515258c; ?>
<?php unset($__componentOriginald33f2de6723ee7b34defb10d4515258c); ?>
<?php endif; ?>
</body>

</html>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/layouts/app.blade.php ENDPATH**/ ?>