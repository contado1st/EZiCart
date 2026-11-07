<?php $__env->startSection('title', 'Messages | EZiCart'); ?>
<?php $__env->startSection('communications-title', 'Messages'); ?>
<?php $__env->startSection('communications-description', 'Private conversations with your contacts.'); ?>
<?php $__env->startSection('communications-content'); ?>
<div class="messaging-frame messaging-frame--inbox">
    <?php echo $__env->make('components.conversation-list', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <section class="conversation-empty" aria-label="Select a conversation"><span class="communications-empty-icon"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?></span><h2>Your private conversations</h2><p>Select a conversation or start a new chat with a related buyer, seller, delivery contact or administrator.</p><a class="communications-text-link" href="<?php echo e(route('chat.contacts', ['panel' => request()->boolean('panel') ? 1 : null])); ?>">Start a new chat</a><small>Only you and the other person can access your conversation.</small></section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.communications', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/orders/inbox.blade.php ENDPATH**/ ?>