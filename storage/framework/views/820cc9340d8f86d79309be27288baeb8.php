<?php $__env->startSection('title', 'Checkout | EZiCart'); ?>
<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/buyer/checkout.css'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/buyer-addresses.css')); ?>?v=<?php echo e(filemtime(public_path('css/buyer-addresses.css'))); ?>">
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<main class="checkout-container checkout-page">
    <header class="checkout-heading"><div><h1><?php echo e($checkoutMode === 'buy_now' ? 'Buy Now checkout' : 'Checkout'); ?></h1><p><?php echo e($checkoutMode === 'buy_now' ? 'Just this product and its selected variation. Your saved cart stays as it is.' : 'Review your selected items, delivery address and final total.'); ?></p></div><form method="POST" action="<?php echo e(route('checkout.cancel')); ?>"><?php echo csrf_field(); ?><button class="checkout-text-button" type="submit"><?php echo e($checkoutMode === 'buy_now' ? 'Cancel Buy Now' : 'Back to cart'); ?></button></form></header>
    <?php echo $__env->make('components.feature-feedback', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php if(session('error')): ?><p class="checkout-feedback" role="alert"><?php echo e(session('error')); ?></p><?php endif; ?>
    <section class="checkout-address-card" aria-labelledby="delivery-title">
        <header><h2 id="delivery-title"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> Delivery address</h2><?php if($selectedAddress?->is_default): ?><span class="checkout-default-tag">Default address</span><?php endif; ?></header>
        <?php if($selectedAddress): ?><?php if (isset($component)) { $__componentOriginal4c91879c9f8a347f0eecddbeb176f7fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4c91879c9f8a347f0eecddbeb176f7fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.buyer-address-summary','data' => ['address' => $selectedAddress]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('buyer-address-summary'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['address' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedAddress)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4c91879c9f8a347f0eecddbeb176f7fe)): ?>
<?php $attributes = $__attributesOriginal4c91879c9f8a347f0eecddbeb176f7fe; ?>
<?php unset($__attributesOriginal4c91879c9f8a347f0eecddbeb176f7fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4c91879c9f8a347f0eecddbeb176f7fe)): ?>
<?php $component = $__componentOriginal4c91879c9f8a347f0eecddbeb176f7fe; ?>
<?php unset($__componentOriginal4c91879c9f8a347f0eecddbeb176f7fe); ?>
<?php endif; ?><?php else: ?><p>Enter your delivery details below.</p><?php endif; ?>
        <details <?php if(!$selectedAddress): ?> open <?php endif; ?>><summary><?php echo e($selectedAddress ? 'Change address' : 'Choose a saved address'); ?></summary><form method="GET" action="<?php echo e(route('checkout.index')); ?>" class="checkout-address-picker"><label for="checkout-saved-address">Saved addresses<select id="checkout-saved-address" name="address_id"><?php $__currentLoopData = $addresses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $savedAddress): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($savedAddress->id); ?>" <?php if($selectedAddress?->id === $savedAddress->id): echo 'selected'; endif; ?>><?php echo e($savedAddress->label); ?> - <?php echo e($savedAddress->municipality); ?><?php echo e($savedAddress->is_default ? ' (default)' : ''); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><option value="new" <?php if($selectedAddress === null): echo 'selected'; endif; ?>>Enter a different address</option></select></label><button class="checkout-secondary" type="submit">Use this address</button><a href="<?php echo e(route('buyer.addresses.index')); ?>">Manage addresses</a></form></details>
    </section>

    <form id="checkout-voucher-form" method="POST" action="<?php echo e(route('checkout.voucher.apply')); ?>"><?php echo csrf_field(); ?></form>
    <form id="checkout-remove-voucher" method="POST" action="<?php echo e(route('checkout.voucher.remove')); ?>"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?></form>
    <form id="place-order" method="POST" action="<?php echo e(route('checkout.process')); ?>" class="checkout-order-form" data-decision="checkout.process" data-decision-title="Place this order?" data-decision-message="Confirm your delivery address, selected products and cash-on-delivery total before placing your order." data-decision-label="Place order" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="checkout_token" value="<?php echo e($token); ?>"><input type="hidden" name="quote_hash" value="<?php echo e($quote['hash']); ?>"><input type="hidden" name="saved_address_id" value="<?php echo e($selectedAddress?->id); ?>">
        <?php if (! ($selectedAddress)): ?>
            <section class="checkout-section"><h2>Delivery details</h2><div class="checkout-form-grid">
                <label for="checkout-recipient-name">Recipient name<input id="checkout-recipient-name" name="recipient_name" maxlength="255" value="<?php echo e(old('recipient_name', $user->first_name.' '.$user->last_name)); ?>" required></label>
                <label for="checkout-recipient-contact">Contact number<input id="checkout-recipient-contact" type="tel" name="recipient_contact" maxlength="13" pattern="(09[0-9]{9}|\+639[0-9]{9})" value="<?php echo e(old('recipient_contact', $user->contact_no)); ?>" required></label>
                <div class="checkout-form-full"><?php echo $__env->make('components.psgc-address-fields', ['prefix' => 'checkout-address', 'address' => $user, 'postalRequired' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
            </div></section>
        <?php endif; ?>

        <section class="checkout-section checkout-products" aria-labelledby="ordered-title">
            <header class="checkout-product-columns"><h2 id="ordered-title">Products ordered</h2><span>Unit price</span><span>Quantity</span><span>Item subtotal</span></header>
            <?php $__currentLoopData = $quote['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sellerId => $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php ($shopLine = reset($group['lines'])); ?>
                <section class="checkout-shop" aria-label="<?php echo e($shopLine['seller_name']); ?> shipment">
                    <header class="checkout-shop-heading"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?><a href="<?php echo e(route('shops.show', $sellerId)); ?>"><?php echo e($shopLine['seller_name']); ?></a><span>Shop shipment</span></header>
                    <?php $__currentLoopData = $group['lines']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <article class="checkout-product-line"><div><?php if($item['image_path']): ?><img src="<?php echo e(asset('storage/'.$item['image_path'])); ?>" alt=""><?php else: ?><span class="checkout-image-placeholder"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?></span><?php endif; ?><div><a href="<?php echo e(route('product.show', $item['product_id'])); ?>"><?php echo e($item['name']); ?></a><?php if($item['variation_info']): ?><p><?php echo e($item['variation_info']); ?></p><?php endif; ?></div></div><span data-label="Unit price">&#8369;<?php echo e(number_format($item['price'], 2)); ?></span><span data-label="Quantity"><?php echo e($item['quantity']); ?></span><strong data-label="Item subtotal">&#8369;<?php echo e(number_format($item['item_cents'] / 100, 2)); ?></strong></article>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <div class="checkout-shipping-row"><div><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?><strong>Standard delivery</strong><span>Shipping for this shop</span></div><strong>&#8369;<?php echo e(number_format($group['shipping_cents'] / 100, 2)); ?></strong></div>
                    <?php if($group['discount_cents'] > 0): ?><div class="checkout-shop-discount"><span>Voucher savings on this shipment</span><strong>&minus;&#8369;<?php echo e(number_format($group['discount_cents'] / 100, 2)); ?></strong></div><?php endif; ?>
                    <footer class="checkout-shop-total">Shop order total <strong>&#8369;<?php echo e(number_format($group['total_cents'] / 100, 2)); ?></strong></footer>
                </section>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </section>

        <section class="checkout-section checkout-voucher"><div><h2><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'invoice']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'invoice']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> Voucher</h2><p>Applied to eligible selected items only.</p></div><div class="checkout-voucher-controls"><label class="checkout-sr-only" for="voucher-code">Voucher code</label><input id="voucher-code" name="voucher_code" form="checkout-voucher-form" maxlength="50" placeholder="Enter voucher code" required value="<?php echo e(old('voucher_code', $appliedVoucher['code'] ?? '')); ?>"><button class="checkout-secondary" type="submit" form="checkout-voucher-form">Apply voucher</button><?php if($appliedVoucher): ?><button class="checkout-text-button" form="checkout-remove-voucher" type="submit">Remove <?php echo e($appliedVoucher['code']); ?></button><?php endif; ?></div></section>

        <section class="checkout-section checkout-notes"><label for="checkout-notes"><strong>Message for your seller</strong><span>Optional delivery landmarks or instructions for this shop's order.</span></label><textarea id="checkout-notes" name="notes" rows="2" maxlength="200" placeholder="Leave a message..."><?php echo e(old('notes')); ?></textarea></section>
        <section class="checkout-section checkout-payment"><div><h2>Payment method</h2><p>Pay your courier when your parcel arrives.</p></div><label><input type="radio" name="payment_method" value="COD" checked> Cash on delivery</label></section>
        <section class="checkout-section checkout-final" aria-labelledby="summary-title"><div class="checkout-shipment-preview"><h2 id="summary-title">Your shipment preview</h2><p><?php echo e(count($cart)); ?> selected <?php echo e(Str::plural('product', count($cart))); ?> from <?php echo e(count($quote['groups'])); ?> <?php echo e(Str::plural('shop', count($quote['groups']))); ?>.</p><p><?php echo e($checkoutMode === 'buy_now' ? 'This purchase is separate from your saved cart.' : 'Unchecked products stay saved in your cart.'); ?></p></div><dl class="checkout-totals"><div><dt>Merchandise subtotal</dt><dd>&#8369;<?php echo e(number_format($subtotal, 2)); ?></dd></div><div><dt>Shipping subtotal</dt><dd>&#8369;<?php echo e(number_format($shippingFee, 2)); ?></dd></div><?php if($discount > 0): ?><div><dt>Voucher discount</dt><dd>&minus;&#8369;<?php echo e(number_format($discount, 2)); ?></dd></div><?php endif; ?><div class="checkout-grand-total"><dt>Total payment</dt><dd>&#8369;<?php echo e(number_format($total, 2)); ?></dd></div></dl><footer class="checkout-place-row"><span>Review your items and address before confirming.</span><button class="checkout-primary" type="submit">Place order <span>&#8369;<?php echo e(number_format($total, 2)); ?></span></button></footer></section>
    </form>
</main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/checkout/index.blade.php ENDPATH**/ ?>