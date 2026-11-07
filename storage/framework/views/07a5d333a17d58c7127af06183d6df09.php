<?php $__env->startSection('title', $product->name.' | EZiCart'); ?>
<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/product-detail.css')); ?>?v=<?php echo e(filemtime(public_path('css/product-detail.css'))); ?>">
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<?php
    $seller = $product->seller;
    $shopName = $seller->profile_display_name ?: $seller->business_name ?: 'Seller shop';
    $publicShop = $seller->role === 'seller' && $seller->status === 'approved';
    $location = implode(', ', array_filter([$seller->municipality, $seller->province]));
?>
<main class="pdp">
    <nav class="pdp-breadcrumb" aria-label="Breadcrumb"><a href="<?php echo e(route('home')); ?>">EZiCart</a><span aria-hidden="true">/</span><a href="<?php echo e(route('search.index', ['category' => $product->category])); ?>"><?php echo e($product->category_label); ?></a><span aria-hidden="true">/</span><span><?php echo e($product->name); ?></span></nav>
    <?php if(session('success')): ?><p class="pdp-feedback" role="status"><?php echo e(session('success')); ?></p><?php endif; ?>
    <?php if(session('error') || $errors->any()): ?><div class="pdp-feedback pdp-error" role="alert"><?php echo e(session('error')); ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
    <section class="pdp-overview" aria-labelledby="product-title">
        <div class="pdp-media">
            <?php if (isset($component)) { $__componentOriginal3e02afc58783df8e9f8209bbd7eced29 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3e02afc58783df8e9f8209bbd7eced29 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.product-gallery','data' => ['product' => $product]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('product-gallery'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['product' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($product)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3e02afc58783df8e9f8209bbd7eced29)): ?>
<?php $attributes = $__attributesOriginal3e02afc58783df8e9f8209bbd7eced29; ?>
<?php unset($__attributesOriginal3e02afc58783df8e9f8209bbd7eced29); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3e02afc58783df8e9f8209bbd7eced29)): ?>
<?php $component = $__componentOriginal3e02afc58783df8e9f8209bbd7eced29; ?>
<?php unset($__componentOriginal3e02afc58783df8e9f8209bbd7eced29); ?>
<?php endif; ?>
            <div class="pdp-media-actions"><button type="button" class="pdp-text-button" data-product-share>Share product</button><span data-share-status role="status"></span><?php if(auth()->user()?->role === 'buyer' && $publicShop): ?><form method="POST" action="<?php echo e(route('shops.follow', $seller)); ?>"><?php echo csrf_field(); ?><input type="hidden" name="following" value="1"><button class="pdp-text-button" type="submit">Follow shop</button></form><?php endif; ?></div>
        </div>
        <div class="pdp-purchase">
            <h1 id="product-title"><?php echo e($product->name); ?></h1>
            <div class="pdp-rating-line"><a href="#product-ratings"><strong><?php echo e($reviewCount ? number_format($averageRating, 1) : 'New'); ?></strong><span class="pdp-stars" aria-label="<?php echo e($averageRating); ?> out of 5 stars"><?php for($i=1; $i<=5; $i++): ?><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'star','class' => \Illuminate\Support\Arr::toCssClasses(['is-filled' => $i <= round($averageRating)])]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'star','class' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\Illuminate\Support\Arr::toCssClasses(['is-filled' => $i <= round($averageRating)]))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><?php endfor; ?></span></a><a href="#product-ratings"><?php echo e(number_format($reviewCount)); ?> Ratings</a><span><?php echo e($product->stock > 0 ? 'In stock' : 'Out of stock'); ?></span></div>
            <div class="pdp-price-band"><strong id="basePriceDisplay">?<?php echo e(number_format($product->price, 2)); ?></strong><span>Cash on delivery</span></div>
            <dl class="pdp-purchase-facts">
                <?php if($vouchers->isNotEmpty()): ?><div><dt>Shop vouchers</dt><dd><a class="pdp-voucher-tag" href="#shop-vouchers"><?php echo e($vouchers->first()->type === 'percent' ? $vouchers->first()->value.'%' : '?'.number_format($vouchers->first()->value)); ?> off � View vouchers</a></dd></div><?php endif; ?>
                <div><dt>Shipping</dt><dd><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'delivery1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'delivery1']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><div>Ships from <?php echo e($location ?: 'the seller�s registered address'); ?><small>Delivery availability and shipping fees are shown at checkout.</small></div></dd></div>
                <div><dt>Payment</dt><dd><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'shield']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'shield']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><div>Pay when your order arrives<small>Cash on delivery is the available payment method.</small></div></dd></div>
            </dl>
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
            <?php if(auth()->user()?->role === 'buyer'): ?>
                <form action="<?php echo e(route('cart.add', $product)); ?>" method="POST" class="pdp-cart-form" data-product-selection data-stock="<?php echo e($product->stock); ?>" data-price="<?php echo e($product->price); ?>" data-shop-open="<?php echo e($seller->acceptsWebOrders() ? '1' : '0'); ?>">
                    <?php echo csrf_field(); ?>
                    <?php if($product->variations->isNotEmpty()): ?>
                        <div class="pdp-option-row"><label for="variationSelect">Variation</label><div>
                            <select name="variation_id" id="variationSelect" required aria-describedby="variation-hint"><option value="">Choose an option</option><?php $__currentLoopData = $product->variations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $variation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($variation->id); ?>" data-adjustment="<?php echo e($variation->price_adjustment); ?>" data-image="<?php echo e($variation->image_path ? asset('storage/'.$variation->image_path) : ''); ?>" data-stock="<?php echo e($variation->stock); ?>" <?php if($variation->stock < 1): echo 'disabled'; endif; ?>><?php echo e($variation->type); ?>: <?php echo e($variation->value); ?> � <?php echo e($variation->stock); ?> available</option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
                            <div class="pdp-variation-buttons" data-variation-buttons hidden><?php $__currentLoopData = $product->variations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $variation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><button type="button" data-variation-choice="<?php echo e($variation->id); ?>" aria-pressed="false" <?php if($variation->stock < 1): echo 'disabled'; endif; ?>><?php if($variation->image_path): ?><img src="<?php echo e(asset('storage/'.$variation->image_path)); ?>" alt=""><?php endif; ?><?php echo e($variation->value); ?></button><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
                            <small id="variation-hint">Choose a variation to see its price and available stock.</small><img data-variation-image class="pdp-variation-image" hidden alt="Selected variation">
                        </div></div>
                    <?php else: ?><img data-variation-image hidden alt="Selected variation"><?php endif; ?>
                    <div class="pdp-option-row"><label for="qtyInput">Quantity</label><div class="pdp-quantity-line"><div class="pdp-quantity-control"><button type="button" data-quantity-step="-1" aria-label="Decrease quantity">-</button><input type="number" name="quantity" id="qtyInput" value="1" min="1" max="<?php echo e(min(20, $product->stock)); ?>" required><button type="button" data-quantity-step="1" aria-label="Increase quantity">+</button></div><span id="stockDisplay"><?php echo e($product->stock); ?> pieces available</span></div></div>
                    <div class="pdp-buy-actions"><button type="submit" class="pdp-button pdp-button-soft" <?php if(! $seller->acceptsWebOrders()): echo 'disabled'; endif; ?>><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> Add to cart</button><button type="submit" name="intent" value="buy_now" class="pdp-button pdp-button-primary" <?php if(! $seller->acceptsWebOrders()): echo 'disabled'; endif; ?>>Buy now</button></div><p class="pdp-checkout-note">Buy now checks out this product separately. Add to cart saves it for later.</p>
                    <p class="pdp-cart-feedback" data-cart-feedback role="status" aria-live="polite" hidden></p>
                </form>
                <div class="pdp-stock-actions"><form method="POST" action="<?php echo e(route('buyer.stock.watch', $product)); ?>"><?php echo csrf_field(); ?><button class="pdp-text-button" type="submit"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> Watch stock availability</button></form><a href="<?php echo e(route('buyer.stock.index')); ?>">Manage stock alerts</a></div>
            <?php elseif(auth()->check()): ?><p class="pdp-sign-in">You are signed in as <?php echo e(str_replace('_', ' ', auth()->user()->role)); ?>. A buyer account is required to purchase.</p>
            <?php else: ?><p class="pdp-sign-in"><a href="<?php echo e(route('login')); ?>">Sign in</a> or <a href="<?php echo e(route('register')); ?>">create a buyer account</a> to choose options and purchase.</p><?php endif; ?>
        </div>
    </section>
    <section class="pdp-shop" aria-label="Seller information"><div class="pdp-shop-identity"><?php if (isset($component)) { $__componentOriginal32077e48dfcf7eeccbd729b994858fc5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal32077e48dfcf7eeccbd729b994858fc5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.profile-avatar','data' => ['user' => $seller]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('profile-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($seller)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal32077e48dfcf7eeccbd729b994858fc5)): ?>
<?php $attributes = $__attributesOriginal32077e48dfcf7eeccbd729b994858fc5; ?>
<?php unset($__attributesOriginal32077e48dfcf7eeccbd729b994858fc5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal32077e48dfcf7eeccbd729b994858fc5)): ?>
<?php $component = $__componentOriginal32077e48dfcf7eeccbd729b994858fc5; ?>
<?php unset($__componentOriginal32077e48dfcf7eeccbd729b994858fc5); ?>
<?php endif; ?><div><h2><?php echo e($shopName); ?></h2><span><?php echo e($location ?: 'EZiCart seller'); ?></span><div class="pdp-shop-actions"><?php if(auth()->user()?->role === 'buyer' && $publicShop): ?><form action="<?php echo e(route('chat.start', $seller)); ?>" method="POST"><?php echo csrf_field(); ?><button class="pdp-button pdp-button-soft" type="submit"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> Chat now</button></form><?php elseif(!auth()->check()): ?><a class="pdp-button pdp-button-soft" href="<?php echo e(route('login')); ?>">Chat with seller</a><?php endif; ?> <?php if($publicShop): ?><a class="pdp-button" href="<?php echo e(route('shops.show', $seller)); ?>"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> View shop</a><?php endif; ?></div></div></div><dl class="pdp-shop-stats"><div><dt>Products</dt><dd><?php echo e(number_format($shopProductCount)); ?></dd></div><div><dt>Followers</dt><dd><?php echo e(number_format($followerCount)); ?></dd></div><div><dt>Joined</dt><dd><?php echo e($seller->created_at?->format('M Y') ?: 'Not recorded'); ?></dd></div></dl></section>
    <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['pdp-content-grid', 'pdp-content-grid--full' => $vouchers->isEmpty() && $sameShop->isEmpty()]); ?>"><div class="pdp-details">
        <section class="pdp-panel"><h2>Product specifications</h2><dl class="pdp-specifications"><div><dt>Category</dt><dd><?php echo e($product->category_label); ?></dd></div><div><dt>Stock</dt><dd><?php echo e(number_format($product->stock)); ?> available</dd></div><div><dt>Weight per unit</dt><dd><?php echo e(\App\Services\ParcelLoadService::kg($product->weight_grams)); ?></dd></div><div><dt>Shop</dt><dd><?php echo e($shopName); ?></dd></div><div><dt>Ships from</dt><dd><?php echo e($location ?: 'Not specified'); ?></dd></div><?php if($product->variations->isNotEmpty()): ?><div><dt>Available options</dt><dd><?php echo e($product->variations->pluck('value')->join(', ')); ?></dd></div><?php endif; ?></dl><h2>Product description</h2><div class="pdp-description"><?php echo e($product->description ?: 'The seller has not added a description for this product.'); ?></div></section>
        <section class="pdp-panel" id="product-ratings"><h2>Product ratings</h2><div class="pdp-review-overview"><div class="pdp-review-score"><strong><?php echo e(number_format($averageRating, 1)); ?></strong><span>out of 5</span><small><?php echo e($reviewCount); ?> <?php echo e(Str::plural('review', $reviewCount)); ?></small></div><nav class="pdp-review-filters" aria-label="Filter product reviews"><a href="<?php echo e(request()->fullUrlWithQuery(['rating' => null, 'comments' => null, 'reviews_page' => null])); ?>#product-ratings" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-active' => !request('rating') && !request()->boolean('comments')]); ?>">All (<?php echo e($reviewCount); ?>)</a><?php for($i=5; $i>=1; $i--): ?><a href="<?php echo e(request()->fullUrlWithQuery(['rating' => $i, 'comments' => null, 'reviews_page' => null])); ?>#product-ratings" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-active' => (int)request('rating') === $i]); ?>"><?php echo e($i); ?> star (<?php echo e($ratingCounts[$i] ?? 0); ?>)</a><?php endfor; ?><a href="<?php echo e(request()->fullUrlWithQuery(['rating' => null, 'comments' => 1, 'reviews_page' => null])); ?>#product-ratings" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-active' => request()->boolean('comments')]); ?>">With comments</a></nav></div>
            <?php $__empty_1 = true; $__currentLoopData = $reviews; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $review): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><article class="pdp-review"><?php if (isset($component)) { $__componentOriginal32077e48dfcf7eeccbd729b994858fc5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal32077e48dfcf7eeccbd729b994858fc5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.profile-avatar','data' => ['user' => $review->buyer]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('profile-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($review->buyer)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal32077e48dfcf7eeccbd729b994858fc5)): ?>
<?php $attributes = $__attributesOriginal32077e48dfcf7eeccbd729b994858fc5; ?>
<?php unset($__attributesOriginal32077e48dfcf7eeccbd729b994858fc5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal32077e48dfcf7eeccbd729b994858fc5)): ?>
<?php $component = $__componentOriginal32077e48dfcf7eeccbd729b994858fc5; ?>
<?php unset($__componentOriginal32077e48dfcf7eeccbd729b994858fc5); ?>
<?php endif; ?><div><strong><?php echo e($review->buyer->first_name); ?> <?php echo e(mb_substr($review->buyer->last_name, 0, 1)); ?>.</strong><div class="pdp-stars" aria-label="<?php echo e($review->rating); ?> out of 5"><?php for($i=1; $i<=5; $i++): ?><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'star','class' => \Illuminate\Support\Arr::toCssClasses(['is-filled' => $i <= $review->rating])]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'star','class' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\Illuminate\Support\Arr::toCssClasses(['is-filled' => $i <= $review->rating]))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><?php endfor; ?></div><time datetime="<?php echo e($review->created_at->toIso8601String()); ?>"><?php echo e($review->created_at->format('d M Y, h:i A')); ?></time><p><?php echo e($review->comment ?: 'The buyer left a rating without a comment.'); ?></p></div></article><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="pdp-empty"><?php echo e($reviewCount ? 'No reviews match this filter. Choose another rating or view all reviews.' : 'No reviews yet. Reviews appear after buyers receive and review their orders.'); ?></p><?php endif; ?>
            <?php echo e($reviews->fragment('product-ratings')->links()); ?>

        </section>
    </div><aside class="pdp-sidebar"><?php if($vouchers->isNotEmpty()): ?><section class="pdp-panel" id="shop-vouchers"><h2>Shop vouchers</h2><?php $__currentLoopData = $vouchers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $voucher): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><article class="pdp-voucher"><strong><?php echo e($voucher->type === 'percent' ? $voucher->value.'%' : '?'.number_format($voucher->value, 2)); ?> off</strong><span>Min. spend ?<?php echo e(number_format($voucher->min_spend, 2)); ?></span><code><?php echo e($voucher->code); ?></code><?php if($voucher->expires_at): ?><small>Valid until <?php echo e($voucher->expires_at->format('d M Y')); ?></small><?php endif; ?> <?php if(auth()->user()?->role === 'buyer'): ?><form method="POST" action="<?php echo e(route('buyer.vouchers.claim', $voucher)); ?>"><?php echo csrf_field(); ?><button class="pdp-button pdp-button-primary" type="submit">Save voucher</button></form><?php else: ?><a href="<?php echo e(route('login')); ?>">Sign in to save</a><?php endif; ?></article><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></section><?php endif; ?> <?php if($sameShop->isNotEmpty()): ?><section class="pdp-panel"><h2>More from this shop</h2><div class="pdp-sidebar-products"><?php $__currentLoopData = $sameShop->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo $__env->make('products.partials.recommendation', ['item' => $item], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></section><?php endif; ?></aside></div>
    <?php if($sameShop->isNotEmpty()): ?><section class="pdp-recommendations"><div class="pdp-section-heading"><h2>From the same shop</h2><?php if($publicShop): ?><a href="<?php echo e(route('shops.show', $seller)); ?>">View all</a><?php endif; ?></div><div class="pdp-product-grid"><?php $__currentLoopData = $sameShop; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo $__env->make('products.partials.recommendation', ['item' => $item], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></section><?php endif; ?>
    <section class="pdp-recommendations pdp-other-shops" id="you-may-also-like" aria-labelledby="other-shops-title">
        <div class="pdp-section-heading"><h2 id="other-shops-title">You may also like</h2></div>
        <?php if($suggestions->isNotEmpty()): ?>
            <div class="pdp-product-grid">
                <?php $__currentLoopData = $suggestions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php echo $__env->make('products.partials.recommendation', ['item' => $item, 'showShop' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <nav class="pdp-recommendation-pages" aria-label="More products from other shops">
                <?php if($suggestions->onFirstPage() === false): ?><a class="pdp-button" href="<?php echo e($suggestions->previousPageUrl()); ?>">Previous products</a><?php endif; ?>
                <?php if($suggestions->hasMorePages()): ?><a class="pdp-button" href="<?php echo e($suggestions->nextPageUrl()); ?>">See more</a><?php endif; ?>
            </nav>
        <?php else: ?>
            <p class="pdp-empty pdp-recommendation-empty">Products from other shops will appear here when approved, in-stock listings are available.</p>
        <?php endif; ?>
    </section>
</main>
<script src="<?php echo e(asset('js/product-selection.js')); ?>?v=<?php echo e(filemtime(public_path('js/product-selection.js'))); ?>" defer></script>
<script src="<?php echo e(asset('js/product-detail.js')); ?>?v=<?php echo e(filemtime(public_path('js/product-detail.js'))); ?>" defer></script>
<script src="<?php echo e(asset('js/cart-feedback.js')); ?>?v=<?php echo e(filemtime(public_path('js/cart-feedback.js'))); ?>" defer></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/products/show.blade.php ENDPATH**/ ?>