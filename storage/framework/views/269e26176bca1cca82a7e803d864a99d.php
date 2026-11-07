<?php $__env->startSection('title', 'Private messages | EZiCart'); ?>
<?php $__env->startSection('communications-title', 'Messages'); ?>
<?php $__env->startSection('communications-description', 'Private conversations with your contacts.'); ?>
<?php $__env->startSection('communications-content'); ?>
<div class="messaging-frame messaging-frame--thread">
    <?php echo $__env->make('components.conversation-list', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <section class="conversation-thread" aria-labelledby="conversation-title">
        <header class="conversation-thread-heading"><div><a class="conversation-mobile-back" href="<?php echo e(route('chat.index', ['panel' => request()->boolean('panel') ? 1 : null])); ?>">All conversations</a><h2 id="conversation-title"><?php echo e($partner->business_name ?: trim($partner->first_name.' '.$partner->last_name)); ?></h2><p>Private conversation &middot; <?php echo e(str_replace('_', ' ', $partner->role)); ?></p><?php if (isset($component)) { $__componentOriginal2a57c4884e76622b11c541e29b5abeb4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a57c4884e76622b11c541e29b5abeb4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user-activity','data' => ['user' => $partner]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user-activity'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($partner)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a57c4884e76622b11c541e29b5abeb4)): ?>
<?php $attributes = $__attributesOriginal2a57c4884e76622b11c541e29b5abeb4; ?>
<?php unset($__attributesOriginal2a57c4884e76622b11c541e29b5abeb4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a57c4884e76622b11c541e29b5abeb4)): ?>
<?php $component = $__componentOriginal2a57c4884e76622b11c541e29b5abeb4; ?>
<?php unset($__componentOriginal2a57c4884e76622b11c541e29b5abeb4); ?>
<?php endif; ?></div><a class="communications-text-link" href="<?php echo e(url()->full()); ?>">Refresh</a></header>
        <?php if($order): ?><div class="conversation-order-context"><div><strong><?php echo e($order->order_number); ?></strong><span><?php echo e(str_replace('_', ' ', $order->status)); ?></span></div><a href="<?php echo e($orderUrl); ?>" <?php if(request()->boolean('panel')): ?> target="_top" <?php endif; ?>>View order</a></div><?php endif; ?>
        <div class="conversation-history" data-conversation-history tabindex="0" aria-label="Message history">
            <?php $__empty_1 = true; $__currentLoopData = $messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php ($ownMessage = $message->sender_id === auth()->id()); ?>
                <article class="<?php echo \Illuminate\Support\Arr::toCssClasses(['conversation-message', 'is-own' => $ownMessage]); ?>">
                    <div class="conversation-message-meta"><strong><?php echo e($ownMessage ? 'You' : trim($partner->first_name.' '.$partner->last_name)); ?></strong></div>
                    <p class="conversation-bubble"><?php echo e($message->body); ?></p>
                    <div class="web-attachment-list"><?php $__currentLoopData = $message->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attachment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a class="web-attachment" href="<?php echo e(route('chat.attachments.show', $attachment)); ?>"><?php if(in_array($attachment->mime_type, ['image/jpeg','image/png','image/webp'])): ?><img src="<?php echo e(route('chat.attachments.show', [$attachment, 'preview' => 1])); ?>" alt="<?php echo e($attachment->original_name); ?>" loading="lazy"><?php endif; ?><strong><?php echo e($attachment->original_name); ?></strong><small><?php echo e($attachment->mime_type); ?> · <?php echo e(number_format($attachment->size / 1024, 1)); ?> KB · Download</small></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
                    <time datetime="<?php echo e($message->created_at->toIso8601String()); ?>"><?php echo e($message->created_at->format('M d, Y h:i A')); ?></time>
                </article>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="conversation-first-message"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?><h3>No messages yet</h3><p>Only you and <?php echo e($partner->first_name); ?> can access this conversation.</p></div><?php endif; ?>
            <?php if($messages->hasPages()): ?><nav class="conversation-history-pages" aria-label="Message history pages"><?php echo e($messages->links('pagination::simple-tailwind')); ?></nav><?php endif; ?>
        </div>
        <?php if($canSend): ?>
        <form class="conversation-composer" method="POST" action="<?php echo e(route('chat.store', $conversation)); ?>" enctype="multipart/form-data" data-message-composer>
            <?php echo csrf_field(); ?> <?php if(request()->boolean('panel')): ?><input type="hidden" name="panel" value="1"><?php endif; ?>
            <?php if($order): ?><input type="hidden" name="order_id" value="<?php echo e($order->id); ?>"><?php endif; ?>
            <label for="message-body">Message</label><textarea id="message-body" name="body" maxlength="2000" rows="3" placeholder="Write a private message..." data-message-input><?php echo e(old('body')); ?></textarea>
            <label for="message-files">Attach files (up to 3; <?php echo e(number_format(\App\Services\ChatAttachmentService::maxKilobytes() / 1024, 1)); ?> MB each)</label><input id="message-files" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt" data-message-files data-max-bytes="<?php echo e(\App\Services\ChatAttachmentService::maxKilobytes() * 1024); ?>"><div class="web-attachment-list" data-message-file-preview></div><p class="web-upload-feedback" data-message-upload-feedback role="status">Images and business documents are shared only with this conversation.</p>
            <div class="conversation-composer-footer"><span><span data-message-count>0</span>/2,000 <span class="composer-shortcut">&middot; Ctrl+Enter to send</span></span><button type="submit" data-message-send>Send message <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?></button></div>
        </form>
        <?php else: ?><p class="conversation-list-empty">Messaging is unavailable because this contact is no longer active or related to your account. Your history remains available.</p><?php endif; ?>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.communications', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/orders/messages.blade.php ENDPATH**/ ?>