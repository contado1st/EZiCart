<?php $__env->startSection('title', 'New private chat | EZiCart'); ?>
<?php $__env->startSection('communications-title', 'New private chat'); ?>
<?php $__env->startSection('communications-description', 'Choose one person to message.'); ?>
<?php $__env->startSection('communications-content'); ?>
<section class="conversation-contacts" aria-labelledby="contacts-title">
    <header class="conversation-thread-heading"><div><h2 id="contacts-title">Choose a contact</h2><p><?php echo e($order ? 'Order '.$order->order_number.' — choose one recipient.' : 'Related contacts and relevant administrators.'); ?></p></div><a href="<?php echo e(route('chat.index', ['panel' => request()->boolean('panel') ? 1 : null])); ?>">Back to messages</a></header>
    <?php if (! ($order)): ?>
    <form class="conversation-search" method="GET" action="<?php echo e(route('chat.contacts')); ?>" role="search">
        <?php if(request()->boolean('panel')): ?><input type="hidden" name="panel" value="1"><?php endif; ?>
        <label class="communications-sr-only" for="contact-search">Search contacts by name or shop</label><input id="contact-search" name="q" type="search" maxlength="100" value="<?php echo e(request('q')); ?>" placeholder="Search name or shop"><button type="submit" aria-label="Search contacts"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'search']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></button>
    </form>
    <?php endif; ?>
    <div class="conversation-list-items">
    <?php $__empty_1 = true; $__currentLoopData = $contacts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $contact): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <form class="conversation-contact" action="<?php echo e(route('chat.start', $contact)); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <?php if(request()->boolean('panel')): ?><input type="hidden" name="panel" value="1"><?php endif; ?>
            <?php if($order): ?><input type="hidden" name="order_id" value="<?php echo e($order->id); ?>"><?php endif; ?>
            <div><strong><?php echo e($contact->business_name ?: trim($contact->first_name.' '.$contact->last_name)); ?></strong><small><?php echo e(str_replace('_', ' ', $contact->role)); ?></small></div>
            <button type="submit">Message<span class="communications-sr-only"> <?php echo e($contact->first_name); ?> <?php echo e($contact->last_name); ?></span></button>
        </form>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="conversation-list-empty"><p>No available contacts<?php echo e(request('q') ? ' match your search' : ' yet'); ?>.</p></div><?php endif; ?>
    </div>
    <?php echo e($contacts->links('pagination::simple-tailwind')); ?>

</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.communications', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/orders/contacts.blade.php ENDPATH**/ ?>