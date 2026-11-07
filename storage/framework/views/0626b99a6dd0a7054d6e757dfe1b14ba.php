<?php $__env->startSection('title', 'Shopping cart | EZiCart'); ?>
<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/buyer/cart.css'); ?>
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<main class="cart-container cart-page" data-cart-selection data-buyer="<?php echo e(auth()->id()); ?>">
    <header class="cart-heading"><div><h1>Shopping cart</h1><p>Buy several products from one shop per checkout. Everything else stays saved for later.</p></div><a href="<?php echo e(route('home')); ?>">Continue shopping</a></header>
    <?php echo $__env->make('components.feature-feedback', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php if(session('error')): ?><p class="cart-feedback" role="alert"><?php echo e(session('error')); ?></p><?php endif; ?>
    <?php if(empty($cart)): ?>
        <section class="cart-empty"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?><h2>Your next find is waiting</h2><p>Your saved cart is empty. Explore products from EZiCart shops.</p><a class="cart-primary" href="<?php echo e(route('home')); ?>">Explore marketplace</a></section>
    <?php else: ?>
        <?php
            $available = array_filter($cart, fn ($item) => $item['available']);
            $firstShop = $available ? reset($available)['seller_id'] : null;
            $selectedKeys = session('checkout_context.mode') === 'cart' ? array_keys(session('checkout_context.items', [])) : array_keys(array_filter($available, fn ($item) => $item['seller_id'] === $firstShop));
            $selected = array_filter($cart, fn ($key) => in_array($key, $selectedKeys), ARRAY_FILTER_USE_KEY);
            $selected = array_filter($selected, fn ($item) => $item['available']);
            $selectedShop = $selected ? reset($selected)['seller_id'] : null;
            $selected = array_filter($selected, fn ($item) => $item['seller_id'] === $selectedShop);
            $selectedCents = array_sum(array_map(fn ($item) => $item['unit_cents'] * $item['quantity'], $selected));
        ?>
        <form id="cart-checkout-selection" method="POST" action="<?php echo e(route('cart.checkout')); ?>"><?php echo csrf_field(); ?></form>
        <div class="cart-column-head"><strong>Product</strong><span>Unit price</span><span>Quantity</span><span>Item total</span><span>Actions</span></div>
        <div class="cart-shop-list">
        <?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sellerId => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <section class="cart-shop" data-shop="<?php echo e($sellerId); ?>" aria-label="<?php echo e($items->first()['seller_name']); ?> products">
                <header class="cart-shop-heading"><label><input type="checkbox" data-select-shop aria-label="Select available products from <?php echo e($items->first()['seller_name']); ?>"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?><strong><?php echo e($items->first()['seller_name']); ?></strong></label><?php if($sellerId): ?><a href="<?php echo e(route('shops.show', $sellerId)); ?>">Visit shop</a><?php endif; ?></header>
                <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <article class="<?php echo \Illuminate\Support\Arr::toCssClasses(['cart-line', 'cart-line-unavailable' => !$item['available']]); ?>">
                        <div class="cart-product">
                            <input type="checkbox" name="selected_keys[]" value="<?php echo e($key); ?>" form="cart-checkout-selection" data-cart-item data-shop-id="<?php echo e($sellerId); ?>" data-cents="<?php echo e($item['unit_cents']); ?>" data-quantity="<?php echo e($item['quantity']); ?>" <?php if(isset($selected[$key])): echo 'checked'; endif; ?> <?php if(!$item['available']): echo 'disabled'; endif; ?> aria-label="Buy <?php echo e($item['name']); ?>">
                            <?php if($item['image_path']): ?><img src="<?php echo e(asset('storage/'.$item['image_path'])); ?>" alt="" loading="lazy"><?php else: ?><span class="cart-product-placeholder"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?></span><?php endif; ?>
                            <div><a class="cart-product-title" href="<?php echo e(route('product.show', $item['product_id'])); ?>"><?php echo e($item['name']); ?></a><?php if($item['variation_info']): ?><p><?php echo e($item['variation_info']); ?></p><?php endif; ?><p class="cart-unit-mobile">&#8369;<?php echo e(number_format($item['price'], 2)); ?> each</p><?php if($item['unavailable_reason']): ?><p class="cart-line-warning"><?php echo e($item['unavailable_reason']); ?></p><?php endif; ?></div>
                        </div>
                        <span class="cart-unit-price">&#8369;<?php echo e(number_format($item['price'], 2)); ?></span>
                        <form class="cart-quantity" method="POST" action="<?php echo e(route('cart.update', $key)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><div><button type="button" data-qty-step="-1" aria-label="Decrease <?php echo e($item['name']); ?> quantity">&minus;</button><label class="cart-sr-only" for="quantity-<?php echo e($key); ?>">Quantity for <?php echo e($item['name']); ?></label><input id="quantity-<?php echo e($key); ?>" type="number" name="quantity" value="<?php echo e($item['quantity']); ?>" min="1" max="20" required><button type="button" data-qty-step="1" aria-label="Increase <?php echo e($item['name']); ?> quantity">+</button></div><button class="cart-text-button" type="submit">Update</button></form>
                        <strong class="cart-line-total">&#8369;<?php echo e(number_format($item['price'] * $item['quantity'], 2)); ?></strong>
                        <div class="cart-line-actions"><form method="POST" action="<?php echo e(route('cart.remove', $key)); ?>" data-decision="cart.remove" data-decision-title="Remove this item?" data-decision-message="This removes the item from your saved cart." data-decision-label="Remove item" data-decision-tone="danger"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="cart-text-button" type="submit">Remove</button></form><a href="<?php echo e(route('search.index', ['search' => $item['name']])); ?>">Find similar</a></div>
                    </article>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <footer class="cart-shop-footer"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?><span>Shipping and eligible vouchers are calculated for your selected items at checkout.</span></footer>
            </section>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <footer class="cart-selection-bar">
            <strong>One shop per checkout</strong>
            <p class="cart-saved-note"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> Saved to your account, even after logout</p>
            <div class="cart-selection-total" aria-live="polite"><span><strong data-selected-count><?php echo e(count($selected)); ?></strong> products selected</span><strong data-selected-total>&#8369;<?php echo e(number_format($selectedCents / 100, 2)); ?></strong><small>Before shipping and vouchers</small></div>
            <button class="cart-primary" type="submit" form="cart-checkout-selection">Check out <span data-selected-button-count>(<?php echo e(count($selected)); ?>)</span></button>
        </footer>
        <p class="cart-selection-hint" data-selection-hint>Select multiple products within one shop. Selecting another shop switches your selection. Other products stay saved in your cart.</p>
    <?php endif; ?>
</main>
<script src="<?php echo e(asset('js/cart-selection.js')); ?>?v=<?php echo e(filemtime(public_path('js/cart-selection.js'))); ?>" defer></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/cart/index.blade.php ENDPATH**/ ?>