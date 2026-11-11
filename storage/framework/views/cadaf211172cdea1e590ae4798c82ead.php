<?php $__env->startPush('styles'); ?> <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?> <?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<main class="container workflow-admin-page">
    <header class="dash-header"><div><h1 class="dash-title">Logistics control tower</h1><p class="dash-subtitle">Cross-hub parcel oversight, service areas, account reviews, and recovery coordination.</p></div><a href="<?php echo e(route('finance.index')); ?>" class="dash-btn-sm">COD and remittances</a></header>
    <section class="workspace-metrics" aria-label="Logistics network summary">
        <?php if (isset($component)) { $__componentOriginal427225e64668c174be9348a122c04dee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal427225e64668c174be9348a122c04dee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workspace-metric','data' => ['label' => 'Parcels in view','value' => $orders->total(),'icon' => 'package1','detail' => 'Matching the current parcel search']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workspace-metric'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Parcels in view','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($orders->total()),'icon' => 'package1','detail' => 'Matching the current parcel search']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $attributes = $__attributesOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__attributesOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $component = $__componentOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__componentOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal427225e64668c174be9348a122c04dee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal427225e64668c174be9348a122c04dee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workspace-metric','data' => ['label' => 'Aging parcels','value' => $aging,'icon' => 'warning','detail' => 'Awaiting an operational update','tone' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workspace-metric'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Aging parcels','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($aging),'icon' => 'warning','detail' => 'Awaiting an operational update','tone' => 'warning']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $attributes = $__attributesOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__attributesOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $component = $__componentOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__componentOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal427225e64668c174be9348a122c04dee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal427225e64668c174be9348a122c04dee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workspace-metric','data' => ['label' => 'Recovery exceptions','value' => $exceptions->count(),'icon' => 'shield','detail' => 'Open cases shown for review','tone' => 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workspace-metric'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Recovery exceptions','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($exceptions->count()),'icon' => 'shield','detail' => 'Open cases shown for review','tone' => 'danger']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $attributes = $__attributesOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__attributesOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $component = $__componentOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__componentOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal427225e64668c174be9348a122c04dee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal427225e64668c174be9348a122c04dee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workspace-metric','data' => ['label' => 'Approved sorting hubs','value' => $centers->count(),'icon' => 'location','detail' => 'Available centers in the network','tone' => 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workspace-metric'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Approved sorting hubs','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($centers->count()),'icon' => 'location','detail' => 'Available centers in the network','tone' => 'success']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $attributes = $__attributesOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__attributesOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal427225e64668c174be9348a122c04dee)): ?>
<?php $component = $__componentOriginal427225e64668c174be9348a122c04dee; ?>
<?php unset($__componentOriginal427225e64668c174be9348a122c04dee); ?>
<?php endif; ?>
    </section>
    <?php if(session('success')): ?><p role="status"><?php echo e(session('success')); ?></p><?php endif; ?>
    <?php if($errors->any()): ?><div role="alert"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><?php echo e($message); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
    <p><a href="<?php echo e(route('parcel-transfers.index')); ?>">Review planned and active hub transfers</a></p>
    <section class="dash-panel" id="network-exceptions"><h2>Aging and exceptions</h2><p><?php echo e($aging); ?> parcels have had no operational update for <?php echo e(config('commerce.parcel_alert_hours')); ?> hours.</p>
        <?php $__empty_1 = true; $__currentLoopData = $exceptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $exception): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><p>Order #<?php echo e($exception->order_id); ?>: <?php echo e($exception->type ?? 'Recovery exception'); ?> — <?php echo e($exception->reason ?? 'Review parcel custody before reassignment.'); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No open recovery exceptions.</p><?php endif; ?>
    </section>
    <div class="workspace-admin-grid">
    <section class="dash-panel" id="applications"><h2>Logistics applications <span class="workspace-count"><?php echo e($pendingUsers->count()); ?> shown</span></h2>
        <?php $__empty_1 = true; $__currentLoopData = $pendingUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $applicant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <article><h3><?php echo e($applicant->business_name ?? trim($applicant->first_name.' '.$applicant->last_name)); ?> (<?php echo e($applicant->role); ?>)</h3>
                <p><?php echo e($applicant->email); ?> · <?php echo e($applicant->contact_no); ?></p>
                <a class="dash-btn-sm dash-btn-outline" href="<?php echo e(route('logistics-admin.applications.show', $applicant)); ?>">Review details & documents</a>
                <form method="POST" action="<?php echo e(route('logistics-admin.users.approve', $applicant)); ?>" data-decision="logistics-admin.users.approve" data-decision-title="Approve this account?" data-decision-message="Confirm that you reviewed the submitted credentials and that this account meets the approval requirements." data-decision-label="Approve account" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?><button class="dash-btn-sm" type="submit">Approve reviewed application</button></form>
                <form method="POST" action="<?php echo e(route('logistics-admin.users.reject', $applicant)); ?>" data-decision="logistics-admin.users.reject" data-decision-title="Reject this application?" data-decision-message="Check the rejection reason before recording your decision." data-decision-label="Reject application" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?><label>Rejection reason<input name="reason" required minlength="5" maxlength="1000"></label><button class="dash-btn-sm" type="submit">Reject</button></form>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No pending logistics applications.</p><?php endif; ?>
    </section>
    <section class="dash-panel" id="service-areas"><h2>Service-area ownership</h2>
        <?php $__currentLoopData = $areas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $area): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <form method="POST" action="<?php echo e(route('logistics-admin.areas.assign', $area)); ?>" data-decision="logistics-admin.areas.assign" data-decision-title="Change service-area ownership?" data-decision-message="Check the selected hub and reason. This affects service-area routing." data-decision-label="Assign hub" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                <label><?php echo e($area->name); ?><select name="sorting_center_id" required><?php $__currentLoopData = $centers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $center): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($center->id); ?>" <?php if($area->sorting_center_id === $center->id): echo 'selected'; endif; ?>><?php echo e($center->business_name ?? $center->first_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                <label>Reason<input name="reason" required minlength="5" maxlength="500"></label><button type="submit" class="dash-btn-sm">Assign hub</button>
            </form>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php echo e($areas->links()); ?>

    </section>
    </div>
    <section class="dash-panel" id="managed-accounts"><h2>Sorting centers and riders</h2><div class="workspace-account-grid">
        <?php $__currentLoopData = $managedUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $managed): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><article><h3><?php echo e($managed->business_name ?? trim($managed->first_name.' '.$managed->last_name)); ?> — <?php echo e($managed->role); ?></h3><p><?php echo e($managed->status); ?></p>
            <?php if($managed->role === 'courier' && $managed->active_parcels_count > 0): ?><p>Suspending this rider will open <?php echo e($managed->active_parcels_count); ?> logistics recovery <?php echo e(\Illuminate\Support\Str::plural('case', $managed->active_parcels_count)); ?>.</p><?php endif; ?>
            <?php if($managed->status === 'approved'): ?><form method="POST" action="<?php echo e(route('logistics-admin.users.suspend', $managed)); ?>" data-decision="logistics-admin.users.suspend" data-decision-title="Suspend this account?" data-decision-message="This restricts the account access and may affect its active work. Review the reason before continuing." data-decision-label="Suspend account" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?><label>Suspension reason<textarea name="suspension_reason" maxlength="500" required></textarea></label><button type="submit" class="dash-btn-sm">Suspend account</button></form>
            <?php else: ?><form method="POST" action="<?php echo e(route('logistics-admin.users.reactivate', $managed)); ?>" data-decision="logistics-admin.users.reactivate" data-decision-title="Restore this account?" data-decision-message="Check the account review and intended status before restoring access." data-decision-label="Restore account" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?><button type="submit" class="dash-btn-sm">Restore prior account status</button></form><?php endif; ?>
        </article><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><div class="workspace-pagination"><?php echo e($managedUsers->links()); ?></div>
    </section>
    <section class="dash-panel" id="parcels"><h2>Parcels across hubs</h2>
        <form method="GET"><label for="parcel-search">Order number</label><input id="parcel-search" name="search" maxlength="100" value="<?php echo e(request('search')); ?>"><button type="submit" class="dash-btn-sm">Search</button></form>
        <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Parcel</th><th>Status</th><th>Hub</th><th>Coordination</th></tr></thead><tbody>
        <?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($order->order_number); ?><?php if (isset($component)) { $__componentOriginal2969209a6e703f0dcf1ea87165624c74 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2969209a6e703f0dcf1ea87165624c74 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.parcel-weight','data' => ['order' => $order,'compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('parcel-weight'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['order' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order),'compact' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2969209a6e703f0dcf1ea87165624c74)): ?>
<?php $attributes = $__attributesOriginal2969209a6e703f0dcf1ea87165624c74; ?>
<?php unset($__attributesOriginal2969209a6e703f0dcf1ea87165624c74); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2969209a6e703f0dcf1ea87165624c74)): ?>
<?php $component = $__componentOriginal2969209a6e703f0dcf1ea87165624c74; ?>
<?php unset($__componentOriginal2969209a6e703f0dcf1ea87165624c74); ?>
<?php endif; ?></td><td><?php echo e(str_replace('_', ' ', $order->status)); ?></td><td><?php echo e($order->sortingCenter->business_name ?? 'Awaiting intake'); ?></td><td>
            <a href="<?php echo e(route('logistics-admin.orders.messages.show', $order)); ?>">Messages</a>
            <?php if(in_array($order->status, ['AT_SORTING_CENTER', 'SORTED'], true) && ! $order->activeTransfer): ?>
                <form method="POST" action="<?php echo e(route('logistics-admin.orders.transfer', $order)); ?>" data-decision="logistics-admin.orders.transfer" data-decision-title="Plan hub transfer?" data-decision-message="Verify the destination hub and reason before creating a physical parcel transfer." data-decision-label="Plan transfer" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?>
                    <label>Destination hub<select name="destination_hub_id" required><?php $__currentLoopData = $centers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $center): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($center->id); ?>"><?php echo e($center->business_name ?? $center->first_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                    <label>Transfer reason<textarea name="reason" required minlength="5" maxlength="500"></textarea></label><button class="dash-btn-sm" type="submit">Plan physical transfer</button>
                </form>
            <?php elseif($order->activeTransfer): ?><p>Transfer: <?php echo e($order->activeTransfer->status); ?></p><?php endif; ?>
            <?php if($order->status === 'READY_FOR_PICKUP' && $order->pickup_arrived_at === null && $order->seller_handover_at === null): ?>
                <form method="POST" action="<?php echo e(route('logistics-admin.orders.pickup', $order)); ?>" data-decision="logistics-admin.orders.pickup" data-decision-title="Reassign pickup?" data-decision-message="Check the selected rider and reason before changing the pickup assignment." data-decision-label="Reassign pickup" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?>
                    <label>Pickup rider<select name="pickup_courier_id" required><?php $__currentLoopData = $riders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($rider->id); ?>"><?php echo e($rider->first_name); ?> <?php echo e($rider->last_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                    <label>Reason<input name="reason" required minlength="5" maxlength="500"></label><button class="dash-btn-sm" type="submit">Reassign before handover</button>
                </form>
            <?php endif; ?>
        </td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="4">No parcels match.</td></tr><?php endif; ?>
        </tbody></table></div><?php echo e($orders->links()); ?>

    </section>
</main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/admin/logistics/index.blade.php ENDPATH**/ ?>