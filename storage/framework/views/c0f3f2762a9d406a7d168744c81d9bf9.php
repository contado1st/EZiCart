<?php $__env->startPush('styles'); ?> <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?> <?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<main class="container workflow-admin-page">
    <header class="dash-header"><div><h1 class="dash-title"><?php echo e($actor->role === 'courier' ? 'Assigned hub transfers' : 'Hub transfers'); ?></h1><p class="dash-subtitle">A planned movement becomes physical custody only after the source and destination scans.</p></div></header>
    <?php if($actor->role === 'courier'): ?><section class="dash-panel"><h2>Dedicated hub transport</h2><?php if (isset($component)) { $__componentOriginal2a13aa3c200396391be62a69d0753524 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a13aa3c200396391be62a69d0753524 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.vehicle-capacity','data' => ['rider' => $actor]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('vehicle-capacity'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['rider' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($actor)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a13aa3c200396391be62a69d0753524)): ?>
<?php $attributes = $__attributesOriginal2a13aa3c200396391be62a69d0753524; ?>
<?php unset($__attributesOriginal2a13aa3c200396391be62a69d0753524); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a13aa3c200396391be62a69d0753524)): ?>
<?php $component = $__componentOriginal2a13aa3c200396391be62a69d0753524; ?>
<?php unset($__componentOriginal2a13aa3c200396391be62a69d0753524); ?>
<?php endif; ?><p>Collect only parcels released by your source hub. Take them to the named destination hub and present each parcel label for receipt scanning. Destination receipt closes the transfer and releases your reserved load.</p><a href="<?php echo e(route('load-planning.index')); ?>">Check vehicle load</a></section><?php endif; ?>
    <?php if(session('success')): ?><p role="status"><?php echo e(session('success')); ?></p><?php endif; ?>
    <?php if($errors->any()): ?><div role="alert"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><?php echo e($message); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
    <?php $__empty_1 = true; $__currentLoopData = $transfers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transfer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><section class="dash-panel">
        <h2><?php echo e($transfer->order->order_number); ?></h2><?php if (isset($component)) { $__componentOriginal2969209a6e703f0dcf1ea87165624c74 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2969209a6e703f0dcf1ea87165624c74 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.parcel-weight','data' => ['order' => $transfer->order,'compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('parcel-weight'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['order' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($transfer->order),'compact' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2969209a6e703f0dcf1ea87165624c74)): ?>
<?php $attributes = $__attributesOriginal2969209a6e703f0dcf1ea87165624c74; ?>
<?php unset($__attributesOriginal2969209a6e703f0dcf1ea87165624c74); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2969209a6e703f0dcf1ea87165624c74)): ?>
<?php $component = $__componentOriginal2969209a6e703f0dcf1ea87165624c74; ?>
<?php unset($__componentOriginal2969209a6e703f0dcf1ea87165624c74); ?>
<?php endif; ?><?php if($transfer->carrier): ?><p>Transport: <?php echo e($transfer->carrier->first_name); ?> <?php echo e($transfer->carrier->last_name); ?></p><?php if (isset($component)) { $__componentOriginal2a13aa3c200396391be62a69d0753524 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2a13aa3c200396391be62a69d0753524 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.vehicle-capacity','data' => ['rider' => $transfer->carrier]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('vehicle-capacity'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['rider' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($transfer->carrier)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2a13aa3c200396391be62a69d0753524)): ?>
<?php $attributes = $__attributesOriginal2a13aa3c200396391be62a69d0753524; ?>
<?php unset($__attributesOriginal2a13aa3c200396391be62a69d0753524); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2a13aa3c200396391be62a69d0753524)): ?>
<?php $component = $__componentOriginal2a13aa3c200396391be62a69d0753524; ?>
<?php unset($__componentOriginal2a13aa3c200396391be62a69d0753524); ?>
<?php endif; ?><?php endif; ?><p><?php echo e($transfer->sourceHub->business_name ?? $transfer->source_hub_id); ?> → <?php echo e($transfer->destinationHub->business_name ?? $transfer->destination_hub_id); ?> — <?php echo e($transfer->status); ?></p>
        <p><?php echo e($transfer->reason); ?></p><?php if($transfer->transport_reference): ?><p>Transport manifest: <?php echo e($transfer->transport_reference); ?></p><?php endif; ?>
        <p class="web-muted">Released: <?php echo e($transfer->released_at ? \Illuminate\Support\Carbon::parse($transfer->released_at)->format('d M Y H:i') : 'Awaiting source scan'); ?> | Received: <?php echo e($transfer->received_at ? \Illuminate\Support\Carbon::parse($transfer->received_at)->format('d M Y H:i') : 'Awaiting destination scan'); ?></p>
        <?php if($actor->role === 'courier' && $transfer->status === 'IN_TRANSIT'): ?><p>Deliver to <?php echo e($transfer->destinationHub->business_name); ?>: <?php echo e($transfer->destinationHub->street_address); ?>, <?php echo e($transfer->destinationHub->municipality); ?>, <?php echo e($transfer->destinationHub->province); ?>.</p><p>Parcel label: <strong>EZP:<?php echo e($transfer->order->parcel_code); ?></strong></p><?php endif; ?>
        <?php if($actor->role === 'sorting_center' && $actor->id === $transfer->source_hub_id && $transfer->status === 'PLANNED'): ?>
            <form method="POST" action="<?php echo e(route('parcel-transfers.release', $transfer)); ?>" data-decision="parcel-transfers.release" data-decision-title="Confirm transfer release?" data-decision-message="Continue only after physically releasing the parcel for the planned hub transfer." data-decision-label="Confirm release" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?><label>Parcel label (EZP code)<input name="parcel_reference" required maxlength="100"></label><label>Transport driver / vehicle<select name="carrier_rider_id" required><option value="">Select a registered source-hub driver</option><?php $__currentLoopData = $carriers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $carrier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($carrier->id); ?>"><?php echo e($carrier->first_name); ?> <?php echo e($carrier->last_name); ?> · <?php echo e($carrier->vehicle_type); ?> · <?php echo e($carrier->vehicle_capacity_verified_at ? \App\Services\ParcelLoadService::kg($carrier->vehicle_capacity_grams) : 'Capacity not verified'); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><label>Transport manifest or carrier handover reference<input name="transport_reference" required maxlength="120"></label><button class="dash-btn-sm" type="submit">Scan and release transfer</button></form>
        <?php endif; ?>
        <?php if($actor->role === 'sorting_center' && $actor->id === $transfer->destination_hub_id && $transfer->status === 'IN_TRANSIT'): ?>
            <form method="POST" action="<?php echo e(route('parcel-transfers.receive', $transfer)); ?>" data-decision="parcel-transfers.receive" data-decision-title="Confirm transfer receipt?" data-decision-message="Continue only after the destination hub physically received the transferred parcel." data-decision-label="Confirm receipt" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?><label>Parcel label (EZP code)<input name="parcel_reference" required maxlength="100"></label><button class="dash-btn-sm" type="submit">Scan physical receipt</button></form>
        <?php endif; ?>
        <?php if($actor->isLogisticsAdmin() && $transfer->status === 'PLANNED'): ?>
            <form method="POST" action="<?php echo e(route('parcel-transfers.cancel', $transfer)); ?>" data-decision="parcel-transfers.cancel" data-decision-title="Cancel planned transfer?" data-decision-message="Check the parcel and cancellation reason before cancelling this transfer." data-decision-label="Cancel transfer" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?><label>Cancellation reason<textarea name="reason" required minlength="5" maxlength="500"></textarea></label><button class="dash-btn-sm" type="submit">Cancel unreleased transfer</button></form>
        <?php endif; ?>
    </section><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No transfers in your workspace.</p><?php endif; ?><?php echo e($transfers->links()); ?>

</main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/logistics/transfers.blade.php ENDPATH**/ ?>