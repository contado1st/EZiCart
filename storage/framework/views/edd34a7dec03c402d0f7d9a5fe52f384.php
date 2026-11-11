<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['variant' => 'dashboard']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['variant' => 'dashboard']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div <?php echo e($attributes->class(['ezi-skeleton'])->merge(['aria-hidden' => 'true'])); ?>>
    <?php if(in_array($variant, ['shop', 'profile'], true)): ?>
        <div class="ezi-skeleton-image ezi-skeleton-hero"></div><div class="ezi-skeleton-profile-identity"><span class="ezi-skeleton-avatar"></span><div><span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span></div></div><?php if($variant === 'shop'): ?><div class="ezi-skeleton-metrics"><?php for($i = 0; $i < 4; $i++): ?><div><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-value"></span></div><?php endfor; ?></div><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'catalog']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'catalog']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?><?php else: ?><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'form-block']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'form-block']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?><?php endif; ?>
    <?php elseif($variant === 'table-body'): ?>
        <div class="ezi-skeleton-table"><?php for($i = 0; $i < 6; $i++): ?><div><?php for($j = 0; $j < 4; $j++): ?><span class="ezi-skeleton-bar"></span><?php endfor; ?></div><?php endfor; ?></div>
    <?php elseif($variant === 'form-block'): ?>
        <div class="ezi-skeleton-panel"><span class="ezi-skeleton-bar ezi-skeleton-title"></span><?php for($i = 0; $i < 4; $i++): ?><div class="ezi-skeleton-field"><span class="ezi-skeleton-bar ezi-skeleton-short"></span><span class="ezi-skeleton-input"></span></div><?php endfor; ?><span class="ezi-skeleton-input ezi-skeleton-button"></span></div>
    <?php elseif($variant === 'product'): ?>
        <span class="ezi-skeleton-bar ezi-skeleton-short"></span><div class="ezi-skeleton-product-detail"><div class="ezi-skeleton-image ezi-skeleton-gallery"></div><div><span class="ezi-skeleton-bar ezi-skeleton-short"></span><span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-value"></span><div class="ezi-skeleton-description"><?php for($i = 0; $i < 3; $i++): ?><span class="ezi-skeleton-bar"></span><?php endfor; ?></div><span class="ezi-skeleton-input"></span><span class="ezi-skeleton-input ezi-skeleton-button"></span></div></div>
    <?php elseif(in_array($variant, ['cart', 'checkout', 'order'], true)): ?>
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span>
        <div class="ezi-skeleton-split"><div><?php if($variant === 'checkout'): ?><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'form-block']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'form-block']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?><?php else: ?><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'table-body']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'table-body']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?><?php endif; ?></div><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'form-block']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'form-block']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?></div>
    <?php elseif($variant === 'announcements'): ?>
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span><div class="ezi-skeleton-announcements"><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'form-block']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'form-block']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'table-body']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'table-body']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?></div>
    <?php elseif($variant === 'table'): ?>
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span><div class="ezi-skeleton-toolbar"><span class="ezi-skeleton-input"></span><span class="ezi-skeleton-input"></span><span class="ezi-skeleton-input"></span></div><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'table-body']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'table-body']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
    <?php elseif($variant === 'analytics'): ?>
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><div class="ezi-skeleton-toolbar"><span class="ezi-skeleton-input"></span><span class="ezi-skeleton-input"></span><span class="ezi-skeleton-input"></span></div><div class="ezi-skeleton-metrics"><?php for($i = 0; $i < 4; $i++): ?><div><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-value"></span></div><?php endfor; ?></div><div class="ezi-skeleton-chart-grid"><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'charts']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'charts']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?><div class="ezi-skeleton-panel"><span class="ezi-skeleton-bar ezi-skeleton-title"></span><div class="ezi-skeleton-ring"></div><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar"></span></div></div>
    <?php elseif($variant === 'charts'): ?>
        <div class="ezi-skeleton-chart"><span class="ezi-skeleton-bar ezi-skeleton-title"></span><div class="ezi-skeleton-plot"></div><span class="ezi-skeleton-bar ezi-skeleton-short"></span></div>
    <?php elseif(in_array($variant, ['messages', 'thread', 'notifications'], true)): ?>
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><div class="ezi-skeleton-toolbar"><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar"></span></div><div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['ezi-skeleton-message-layout', 'ezi-skeleton-thread' => $variant === 'thread', 'ezi-skeleton-notifications' => $variant === 'notifications']); ?>"><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'message-list']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'message-list']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?><div class="ezi-skeleton-panel"><?php if($variant === 'thread'): ?><?php for($i = 0; $i < 5; $i++): ?><div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['ezi-skeleton-bubble', 'ezi-skeleton-bubble-reply' => $i % 2]); ?>"><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span></div><?php endfor; ?><span class="ezi-skeleton-input"></span><?php elseif($variant === 'notifications'): ?><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'message-list']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'message-list']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?><?php else: ?><div class="ezi-skeleton-empty-thread"><span class="ezi-skeleton-avatar"></span><span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar"></span></div><?php endif; ?></div></div>
    <?php elseif($variant === 'message-list'): ?>
        <div class="ezi-skeleton-messages"><?php for($i = 0; $i < 5; $i++): ?><div class="ezi-skeleton-contact"><span class="ezi-skeleton-avatar"></span><div><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span></div></div><?php endfor; ?></div>
    <?php elseif(in_array($variant, ['form', 'auth', 'login'], true)): ?>
        <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['ezi-skeleton-form', 'ezi-skeleton-form-wide' => $variant === 'form']); ?>"><span class="ezi-skeleton-bar ezi-skeleton-title"></span><div class="ezi-skeleton-form-fields"><?php for($i = 0; $i < ($variant === 'login' ? 2 : 6); $i++): ?><div class="ezi-skeleton-field"><span class="ezi-skeleton-bar ezi-skeleton-short"></span><span class="ezi-skeleton-input"></span></div><?php endfor; ?></div><span class="ezi-skeleton-input ezi-skeleton-button"></span></div>
    <?php elseif(in_array($variant, ['search', 'storefront'], true)): ?>
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><?php if($variant === 'storefront'): ?><div class="ezi-skeleton-image ezi-skeleton-hero"></div><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'catalog']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'catalog']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?><?php else: ?><div class="ezi-skeleton-search"><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'form-block']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'form-block']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'catalog']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'catalog']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?></div><?php endif; ?>
    <?php elseif($variant === 'catalog'): ?>
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span>
        <div class="ezi-skeleton-products"><?php for($i = 0; $i < 6; $i++): ?><div><div class="ezi-skeleton-image"></div><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span></div><?php endfor; ?></div>
    <?php elseif(in_array($variant, ['dashboard', 'purchases'], true)): ?>
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span>
        <div class="ezi-skeleton-metrics"><?php for($i = 0; $i < 4; $i++): ?><div><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-value"></span></div><?php endfor; ?></div>
        <?php if($variant === 'purchases'): ?><?php for($i = 0; $i < 3; $i++): ?><div class="ezi-skeleton-purchase"><span class="ezi-skeleton-bar ezi-skeleton-short"></span><div class="ezi-skeleton-contact"><span class="ezi-skeleton-image"></span><div><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span></div></div></div><?php endfor; ?> <?php else: ?><?php if (isset($component)) { $__componentOriginal4aa275bfcaf452a13922774018fb2f04 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4aa275bfcaf452a13922774018fb2f04 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-skeleton','data' => ['variant' => 'table-body']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-skeleton'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'table-body']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $attributes = $__attributesOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__attributesOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4aa275bfcaf452a13922774018fb2f04)): ?>
<?php $component = $__componentOriginal4aa275bfcaf452a13922774018fb2f04; ?>
<?php unset($__componentOriginal4aa275bfcaf452a13922774018fb2f04); ?>
<?php endif; ?><?php endif; ?>
    <?php else: ?>
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><div class="ezi-skeleton-panel"><?php for($i = 0; $i < 7; $i++): ?><span class="ezi-skeleton-bar"></span><?php endfor; ?></div>
    <?php endif; ?>
</div>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/loading-skeleton.blade.php ENDPATH**/ ?>