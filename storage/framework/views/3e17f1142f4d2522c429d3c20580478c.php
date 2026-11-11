<?php $__env->startSection('title', ($seller->profile_display_name ?: $seller->business_name ?: 'Seller shop').' | EZiCart Shop'); ?>
<?php $__env->startSection('content'); ?>
<div class="shop-page profile-theme-<?php echo e($seller->profile_theme ?: 'rose'); ?>">
    <nav class="shop-breadcrumb" aria-label="Breadcrumb"><a href="<?php echo e(route('home')); ?>">Marketplace</a><span aria-hidden="true">/</span><span><?php echo e($seller->business_name); ?></span></nav>
    <header class="shop-header">
        <div class="profile-cover"><?php if($seller->profile_banner_path): ?><img src="<?php echo e(route('profiles.media', [$seller, 'banner', 'v' => $seller->updated_at?->timestamp])); ?>" alt="<?php echo e($seller->business_name); ?> banner" width="1600" height="500"><?php endif; ?><span class="profile-cover-wordmark">EZiCart</span></div>
        <div class="profile-identity"><?php if (isset($component)) { $__componentOriginal32077e48dfcf7eeccbd729b994858fc5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal32077e48dfcf7eeccbd729b994858fc5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.profile-avatar','data' => ['user' => $seller,'class' => 'profile-avatar-large']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('profile-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($seller),'class' => 'profile-avatar-large']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal32077e48dfcf7eeccbd729b994858fc5)): ?>
<?php $attributes = $__attributesOriginal32077e48dfcf7eeccbd729b994858fc5; ?>
<?php unset($__attributesOriginal32077e48dfcf7eeccbd729b994858fc5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal32077e48dfcf7eeccbd729b994858fc5)): ?>
<?php $component = $__componentOriginal32077e48dfcf7eeccbd729b994858fc5; ?>
<?php unset($__componentOriginal32077e48dfcf7eeccbd729b994858fc5); ?>
<?php endif; ?><div class="profile-identity-copy"><h1><?php echo e($seller->profile_display_name ?: $seller->business_name ?: 'Seller shop'); ?></h1><p><?php echo e($seller->business_name); ?> <?php if($seller->municipality || $seller->province): ?><span aria-hidden="true"> · </span><?php echo e(implode(', ', array_filter([$seller->municipality, $seller->province]))); ?><?php endif; ?></p><span class="shop-account-state">Approved seller</span></div>
            <div class="shop-actions">
                <?php if(auth()->guard()->check()): ?>
                    <?php if(auth()->user()->role === 'buyer'): ?><form method="POST" action="<?php echo e(route('shops.follow', $seller)); ?>"><?php echo csrf_field(); ?><input type="hidden" name="following" value="<?php echo e($following ? 0 : 1); ?>"><button type="submit" class="profile-button <?php echo e($following ? '' : 'profile-button-primary'); ?>" aria-pressed="<?php echo e($following ? 'true' : 'false'); ?>"><?php echo e($following ? 'Following · Unfollow' : 'Follow shop'); ?></button></form><form method="POST" action="<?php echo e(route('chat.start', $seller)); ?>"><?php echo csrf_field(); ?><button type="submit" class="profile-button"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> Chat with seller</button></form><?php elseif(auth()->id() === $seller->id): ?><a class="profile-button profile-button-primary" href="<?php echo e(route('account.profile.edit')); ?>#profile-appearance">Customize shop</a><?php endif; ?>
                <?php else: ?><a class="profile-button profile-button-primary" href="<?php echo e(route('login')); ?>">Sign in to follow</a><a class="profile-button" href="<?php echo e(route('login')); ?>">Chat with seller</a><?php endif; ?>
            </div>
        </div>
        <dl class="shop-stats"><div><dt>Available products</dt><dd><?php echo e(number_format($productCount)); ?></dd></div><div><dt>Product rating</dt><dd><?php echo e($rating === null ? 'Not rated yet' : number_format($rating, 1).' / 5'); ?> <small><?php echo e(number_format($reviewCount)); ?> <?php echo e(\Illuminate\Support\Str::plural('review', $reviewCount)); ?></small></dd></div><div><dt>Followers</dt><dd><?php echo e(number_format($followerCount)); ?></dd></div><div><dt>On EZiCart since</dt><dd><?php echo e($seller->created_at?->format('M Y') ?? 'Not recorded'); ?></dd></div></dl>
    </header>
    <?php if (isset($component)) { $__componentOriginal2a57c4884e76622b11c541e29b5abeb4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a57c4884e76622b11c541e29b5abeb4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user-activity','data' => ['user' => $seller]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user-activity'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($seller)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a57c4884e76622b11c541e29b5abeb4)): ?>
<?php $attributes = $__attributesOriginal2a57c4884e76622b11c541e29b5abeb4; ?>
<?php unset($__attributesOriginal2a57c4884e76622b11c541e29b5abeb4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a57c4884e76622b11c541e29b5abeb4)): ?>
<?php $component = $__componentOriginal2a57c4884e76622b11c541e29b5abeb4; ?>
<?php unset($__componentOriginal2a57c4884e76622b11c541e29b5abeb4); ?>
<?php endif; ?>
    <?php if(session('success')): ?><p class="profile-notice" role="status"><?php echo e(session('success')); ?></p><?php endif; ?>
    <?php if($errors->any()): ?><p class="profile-field-error" role="alert"><?php echo e($errors->first()); ?></p><?php endif; ?>
    <nav class="shop-tabs" aria-label="Shop sections"><a href="#shop-products">Products</a><a href="#shop-about">About this shop</a></nav>
    <section id="shop-products" aria-labelledby="shop-products-title">
        <div class="shop-products-heading"><div><h2 id="shop-products-title">Shop products</h2><p><?php echo e(number_format($products->total())); ?> matching <?php echo e(\Illuminate\Support\Str::plural('product', $products->total())); ?></p></div></div>
        <form method="GET" action="<?php echo e(route('shops.show', $seller)); ?>#shop-products" class="shop-filters" role="search"><label for="shop-search">Search in this shop<input id="shop-search" type="search" name="q" maxlength="100" value="<?php echo e($filters['q'] ?? ''); ?>" placeholder="Search products"></label><label for="shop-category">Category<select id="shop-category" name="category"><option value="">All categories</option><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option <?php if(($filters['category'] ?? '') === $category): echo 'selected'; endif; ?>><?php echo e($category); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><label for="shop-sort">Sort by<select id="shop-sort" name="sort"><?php $__currentLoopData = ['latest' => 'Newest first', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'rating' => 'Top rated']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if($filters['sort'] === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><button class="profile-button profile-button-primary" type="submit">Apply</button><a class="shop-reset" href="<?php echo e(route('shops.show', $seller)); ?>#shop-products">Reset</a></form>
        <?php if($products->isNotEmpty()): ?><div class="shop-product-grid"><?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><article class="shop-product"><a href="<?php echo e(route('product.show', $product)); ?>"><div class="shop-product-image"><?php if($product->image_path): ?><img src="<?php echo e(asset('storage/'.$product->image_path)); ?>" alt="<?php echo e($product->name); ?>" width="400" height="400" loading="lazy"><?php else: ?><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?><?php endif; ?></div><div class="shop-product-copy"><span><?php echo e($product->category_label); ?></span><h3><?php echo e($product->name); ?></h3><strong>₱<?php echo e(number_format($product->price, 2)); ?></strong><p><?php if($product->shop_review_count): ?><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'star']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'star']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> <?php echo e(number_format($product->shop_rating, 1)); ?> <small>(<?php echo e($product->shop_review_count); ?> reviews)</small><?php else: ?> No ratings yet <?php endif; ?></p></div></a></article><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><div class="shop-pagination"><?php echo e($products->fragment('shop-products')->links()); ?></div><?php else: ?><div class="shop-empty"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?><h3>No matching products</h3><p>Try another keyword or category, or check back when this shop adds available products.</p></div><?php endif; ?>
    </section>
    <section class="shop-about" id="shop-about" aria-labelledby="shop-about-title"><h2 id="shop-about-title">About this shop</h2><p><?php echo e($seller->profile_bio ?: 'This seller has not added a shop introduction yet.'); ?></p><?php if($seller->line_of_business): ?><p class="shop-business-line"><?php echo e($seller->line_of_business); ?></p><?php endif; ?><small>Rating includes reviews of approved, non-archived products, including items currently out of stock.</small></section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/shops/show.blade.php ENDPATH**/ ?>