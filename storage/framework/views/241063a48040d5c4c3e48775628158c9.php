<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['order', 'compact' => false]));

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

foreach (array_filter((['order', 'compact' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<span class="<?php echo \Illuminate\Support\Arr::toCssClasses(['parcel-weight-label', 'is-missing' => !$order->parcel_weight_grams]); ?>">Packed weight: <?php echo e(\App\Services\ParcelLoadService::kg($order->parcel_weight_grams)); ?><?php if(!$order->parcel_weight_grams): ?> — pickup/dispatch blocked <?php endif; ?></span>
<?php if(!$compact): ?>
    <?php
        $estimate = app(\App\Services\ParcelLoadService::class)->estimate($order);
        $canWeigh = (auth()->user()->role === 'seller' && auth()->id() === $order->seller_id && in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true))
            || (auth()->user()->role === 'sorting_center' && auth()->id() === $order->sorting_center_id && in_array($order->status, ['AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER'], true) && !$order->hub_released_at);
    ?>
    <p class="web-muted">Item estimate: <?php echo e(\App\Services\ParcelLoadService::kg($estimate)); ?>. Actual packed weight must include packaging.<?php if($order->weight_recorded_at): ?> Last weighed <?php echo e($order->weight_recorded_at->format('d M Y, h:i A')); ?>.<?php endif; ?></p>
    <?php if(in_array(auth()->user()->role, ['seller', 'courier', 'sorting_center', 'logistics_admin'], true)): ?>
        <?php ($order->loadMissing(['pickupCourier', 'deliveryCourier'])); ?>
        <?php $__currentLoopData = collect([$order->pickupCourier, $order->deliveryCourier])->filter()->unique('id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignedRider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if (isset($component)) { $__componentOriginal2a13aa3c200396391be62a69d0753524 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a13aa3c200396391be62a69d0753524 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.vehicle-capacity','data' => ['rider' => $assignedRider]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('vehicle-capacity'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['rider' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($assignedRider)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a13aa3c200396391be62a69d0753524)): ?>
<?php $attributes = $__attributesOriginal2a13aa3c200396391be62a69d0753524; ?>
<?php unset($__attributesOriginal2a13aa3c200396391be62a69d0753524); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a13aa3c200396391be62a69d0753524)): ?>
<?php $component = $__componentOriginal2a13aa3c200396391be62a69d0753524; ?>
<?php unset($__componentOriginal2a13aa3c200396391be62a69d0753524); ?>
<?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <a class="dash-action-link" href="<?php echo e(route('load-planning.index')); ?>">Check combined vehicle load and remaining capacity</a>
    <?php endif; ?>
    <?php if($estimate !== null && $order->parcel_weight_grams && $order->parcel_weight_grams < $estimate): ?><p class="profile-field-error">Measured weight is below the item estimate. Check the scale and product weights before planning transport.</p><?php endif; ?>
    <?php if($canWeigh): ?>
        <details class="parcel-weight-editor"><summary><?php echo e($order->parcel_weight_grams ? 'Reweigh packed parcel' : 'Record packed parcel weight'); ?></summary>
            <form class="web-form" method="POST" action="<?php echo e(route('orders.weight', $order)); ?>" data-decision="parcel-weight" data-decision-title="Record measured parcel weight?" data-decision-message="Use the actual packed weight from a scale. This updates load planning and may require a different vehicle." data-decision-label="Record weight" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                <label for="parcel-weight-<?php echo e($order->id); ?>">Packed parcel weight (kg)<input id="parcel-weight-<?php echo e($order->id); ?>" type="number" name="parcel_weight_kg" min="0.001" max="100000" step="0.001" value="<?php echo e(old('parcel_weight_kg', $order->parcel_weight_grams ? $order->parcel_weight_grams / 1000 : '')); ?>" required></label>
                <label for="parcel-weight-reason-<?php echo e($order->id); ?>">Measurement note<input id="parcel-weight-reason-<?php echo e($order->id); ?>" name="reason" minlength="5" maxlength="500" required placeholder="Scale measurement and reason for any correction"></label><button class="dash-btn-sm dash-btn-primary" type="submit">Record actual weight</button>
            </form>
        </details>
    <?php endif; ?>
<?php endif; ?>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/components/parcel-weight.blade.php ENDPATH**/ ?>