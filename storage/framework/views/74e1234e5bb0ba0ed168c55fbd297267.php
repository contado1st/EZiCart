<aside class="conversation-list" aria-label="Private conversations">
    <header class="conversation-list-heading"><h2>All conversations</h2><a href="<?php echo e(route('chat.contacts', ['panel' => request()->boolean('panel') ? 1 : null])); ?>">New chat</a></header>
    <form action="<?php echo e(route('chat.index')); ?>" method="GET" class="conversation-search" role="search">
        <?php if(request()->boolean('panel')): ?><input type="hidden" name="panel" value="1"><?php endif; ?>
        <label class="communications-sr-only" for="conversation-search">Search conversations by name or shop</label><input id="conversation-search" name="q" type="search" value="<?php echo e(request('q')); ?>" maxlength="100" placeholder="Search name or shop"><button type="submit" aria-label="Search conversations"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
    <div class="conversation-list-items">
    <?php $__empty_1 = true; $__currentLoopData = $threads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $thread): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
            $person = $thread->otherUser(auth()->user());
            $partnerName = $person->business_name ?: trim($person->first_name.' '.$person->last_name);
            $latest = $thread->latestMessage;
            $isSelected = isset($conversation) && $conversation->id === $thread->id;
        ?>
        <a href="<?php echo e(route('chat.show', [$thread, 'panel' => request()->boolean('panel') ? 1 : null])); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['conversation-item', 'is-selected' => $isSelected]); ?>" <?php if($isSelected): ?> aria-current="page" <?php endif; ?>>
            <span class="conversation-avatar" aria-hidden="true"><?php echo e(mb_strtoupper(mb_substr($partnerName, 0, 1))); ?></span>
            <span class="conversation-copy"><span class="conversation-name"><?php echo e($partnerName); ?></span><span class="conversation-order"><?php echo e(str_replace('_', ' ', $person->role)); ?></span><span class="conversation-preview"><?php echo e($latest ? (auth()->id() === $latest->sender_id ? 'You: ' : '').$latest->body : 'Start a private conversation'); ?></span></span>
            <?php if($thread->unread_count): ?><span class="conversation-unread" aria-label="<?php echo e($thread->unread_count); ?> unread messages"><?php echo e($thread->unread_count > 99 ? '99+' : $thread->unread_count); ?></span><?php elseif($latest): ?><time datetime="<?php echo e($latest->created_at->toIso8601String()); ?>"><?php echo e($latest->created_at->format('M d')); ?></time><?php endif; ?>
        </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="conversation-list-empty"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?><p><?php echo e(request('q') ? 'No conversations match your search.' : 'Your private conversations will appear here.'); ?></p></div><?php endif; ?>
    </div>
    <?php if($threads->hasPages()): ?><nav class="conversation-list-pagination" aria-label="Conversation pages"><?php echo e($threads->onEachSide(0)->links('pagination::simple-tailwind')); ?></nav><?php endif; ?>
</aside>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/conversation-list.blade.php ENDPATH**/ ?>