<?php $__env->startSection('workspace'); ?>
    <header class="ops-heading">
        <div>
            <h1>My dispatch board</h1>
            <p><?php echo e(now()->format('D, d M Y')); ?> · Pickups, hub handoffs and doorstep deliveries assigned to you.</p>
        </div>
        <div class="courier-actions">
            <span class="ops-status <?php echo e($courier->status === 'approved' ? 'ops-status--green' : 'ops-status--amber'); ?>">
                <?php echo e(ucfirst($courier->status)); ?>

            </span>
        </div>
    </header>

    <section class="ops-stats ops-stats--priority" aria-label="Today's work summary">
        <div class="ops-stat ops-stat--action">
            <span class="workspace-stat-icon" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?></span>
            <span>Pickup claims</span>
            <strong><?php echo e($stats['claimed_pickups']); ?></strong>
            <small class="workspace-stat-detail">Accepted pickup tasks</small>
        </div>
        <div class="ops-stat">
            <span class="workspace-stat-icon" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?></span>
            <span>Parcels to hub</span>
            <strong><?php echo e($stats['in_transit_hub']); ?></strong>
            <small class="workspace-stat-detail">Awaiting hub handoff</small>
        </div>
        <div class="ops-stat ops-stat--action">
            <span class="workspace-stat-icon" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?></span>
            <span>Delivery workload</span>
            <strong><?php echo e($stats['assigned_delivery']); ?></strong>
            <small class="workspace-stat-detail">Your assigned deliveries</small>
        </div>
        <div class="ops-stat">
            <span class="workspace-stat-icon" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?></span>
            <span>Returns to seller</span>
            <strong><?php echo e($stats['returning_to_seller']); ?></strong>
            <small class="workspace-stat-detail">Pending seller handoff</small>
        </div>
        <div class="ops-stat">
            <span class="workspace-stat-icon" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'checklist']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'checklist']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></span>
            <span>Delivered today</span>
            <strong><?php echo e($stats['completed_today']); ?></strong>
            <small class="workspace-stat-detail">Successful deliveries today</small>
        </div>
        <div class="ops-stat ops-stat--danger">
            <span class="workspace-stat-icon" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'warning']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></span>
            <span>Failed attempts today</span>
            <strong><?php echo e($stats['failed_today']); ?></strong>
            <small class="workspace-stat-detail">Recorded delivery attempts</small>
        </div>
    </section>

    
    <section class="courier-hero-card" aria-labelledby="courier-next-action-title">
        <div>
            <span class="courier-hero-card__badge">Priority Action</span>
            <h2 id="courier-next-action-title">
                <?php if(count($myDeliveryAssignments) > 0): ?>
                    Doorstep delivery: <?php echo e($myDeliveryAssignments->first()->order_number); ?>

                <?php elseif($claimedPickups->isNotEmpty()): ?>
                    Pickup in progress: <?php echo e($claimedPickups->first()->order_number); ?>

                <?php elseif($myActivePickups->isNotEmpty()): ?>
                    Hub handoff: <?php echo e($myActivePickups->first()->order_number); ?>

                <?php elseif($myReturns->isNotEmpty()): ?>
                    Seller return: <?php echo e($myReturns->first()->order_number); ?>

                <?php elseif(count($availablePickups) > 0): ?>
                    Available pickup request
                <?php elseif($myFailedDeliveries->isNotEmpty()): ?>
                    Failed delivery under review: <?php echo e($myFailedDeliveries->first()->order_number); ?>

                <?php else: ?>
                    All caught up!
                <?php endif; ?>
            </h2>
            <p>
                <?php if(count($myDeliveryAssignments) > 0): ?>
                    Deliver to <?php echo e($myDeliveryAssignments->first()->recipient_name); ?> in <?php echo e($myDeliveryAssignments->first()->delivery_area ?? $myDeliveryAssignments->first()->municipality); ?>.
                <?php elseif($claimedPickups->isNotEmpty()): ?>
                    <?php if($claimedPickups->first()->pickup_arrived_at && $claimedPickups->first()->seller_handover_at): ?>
                        Scan the parcel label to confirm possession of pickup <?php echo e($claimedPickups->first()->order_number); ?>.
                    <?php elseif($claimedPickups->first()->pickup_arrived_at): ?>
                        Record the seller handoff for pickup <?php echo e($claimedPickups->first()->order_number); ?>.
                    <?php else: ?>
                        Travel to the seller for pickup <?php echo e($claimedPickups->first()->order_number); ?> and record your arrival.
                    <?php endif; ?>
                <?php elseif($myActivePickups->isNotEmpty()): ?>
                    Take collected parcel <?php echo e($myActivePickups->first()->order_number); ?> to the sorting hub.
                <?php elseif($myReturns->isNotEmpty()): ?>
                    Complete the seller return for parcel <?php echo e($myReturns->first()->order_number); ?>.
                <?php elseif(count($availablePickups) > 0): ?>
                    Review the seller pickup requests assigned to you.
                <?php elseif($myFailedDeliveries->isNotEmpty()): ?>
                    A failed delivery is with Logistics for review. Check its recorded attempt and messages.
                <?php else: ?>
                    You have no open parcel tasks right now. Great job!
                <?php endif; ?>
            </p>
        </div>
        <div>
            <?php if(count($myDeliveryAssignments) > 0): ?>
                <a class="ops-btn ops-btn--primary" href="<?php echo e(route('courier.orders.show', $myDeliveryAssignments->first())); ?>">Open delivery</a>
            <?php elseif($claimedPickups->isNotEmpty()): ?>
                <a class="ops-btn ops-btn--primary" href="#accepted-pickups">Continue pickup</a>
            <?php elseif($myActivePickups->isNotEmpty()): ?>
                <a class="ops-btn ops-btn--primary" href="#hub-custody">View parcels for hub handoff</a>
            <?php elseif($myReturns->isNotEmpty()): ?>
                <a class="ops-btn ops-btn--primary" href="#seller-returns">Continue return</a>
            <?php elseif(count($availablePickups) > 0): ?>
                <a class="ops-btn ops-btn--primary" href="#pickup-requests">Review pickups</a>
            <?php elseif($myFailedDeliveries->isNotEmpty()): ?>
                <a class="ops-btn ops-btn--danger" href="#failed-deliveries">Review failed delivery</a>
            <?php endif; ?>
        </div>
    </section>

    
    <nav class="courier-jump-nav" aria-label="Workflow quick jump">
        <a class="courier-jump-pill" href="#pickup-requests">
            Available pickups <span><?php echo e($availablePickups->total()); ?></span>
        </a>
        <a class="courier-jump-pill" href="#accepted-pickups">
            Claimed pickups <span><?php echo e($claimedPickups->count()); ?></span>
        </a>
        <a class="courier-jump-pill" href="#hub-custody">
            Parcels to hub <span><?php echo e($myActivePickups->count()); ?></span>
        </a>
        <a class="courier-jump-pill" href="#delivery-assignments">
            Deliveries <span><?php echo e($myDeliveryAssignments->total()); ?></span>
        </a>
        <a class="courier-jump-pill" href="#failed-deliveries">
            Failed review <span><?php echo e($myFailedDeliveries->count()); ?></span>
        </a>
        <a class="courier-jump-pill" href="#seller-returns">
            Returns <span><?php echo e($myReturns->count()); ?></span>
        </a>
    </nav>

    


    
    <section class="ops-panel courier-pickup" id="pickup-requests">
        <h2>Available seller pickups <span class="ops-muted"><?php echo e($availablePickups->total()); ?> available</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Seller pickup point</th>
                        <th>Destination</th>
                        <th>Contents</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $availablePickups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="ops-mono" data-label="Order"><?php echo e($order->order_number); ?><?php if (isset($component)) { $__componentOriginal2969209a6e703f0dcf1ea87165624c74 = $component; } ?>
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
<?php endif; ?></td>
                            <td data-label="Seller">
                                <strong><?php echo e($order->seller?->business_name ?? $order->seller?->first_name); ?></strong>
                                <div class="ops-muted"><?php echo e(trim(($order->seller?->house_number ? $order->seller->house_number.' ' : '').($order->seller?->street_address ?? ''))); ?>, <?php echo e($order->seller?->municipality); ?></div>
                                <?php if($order->seller?->contact_no): ?>
                                    <div>
                                        <a href="tel:<?php echo e($order->seller->contact_no); ?>" class="courier-tel-link">
                                            <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'phone']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'phone']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> <?php echo e($order->seller->contact_no); ?>

                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Destination"><?php echo e($order->municipality); ?>, <?php echo e($order->province); ?></td>
                            <td data-label="Contents"><?php echo e($order->items->count()); ?> item(s)</td>
                            <td data-label="Action">
                                <div class="courier-actions">
                                    <form method="POST" action="<?php echo e(route('courier.orders.claim', $order)); ?>" data-decision="courier.orders.claim" data-decision-title="Accept pickup?" data-decision-message="This accepts responsibility for the selected pickup. Check the parcel and service area." data-decision-label="Accept pickup" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        <?php echo csrf_field(); ?>
                                        <button class="ops-btn ops-btn--primary" type="submit" <?php if($courier->status !== 'approved'): echo 'disabled'; endif; ?>>
                                            Accept assigned pickup
                                        </button>
                                    </form>
                                    <form method="POST" action="<?php echo e(route('courier.orders.declinePickup', $order)); ?>" data-decision="courier.orders.declinePickup" data-decision-title="Decline pickup?" data-decision-message="This returns the pickup for reassignment. Check your reason before continuing." data-decision-label="Decline pickup" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        <?php echo csrf_field(); ?>
                                        <input type="text" name="reason" maxlength="500" placeholder="Reason (optional)" aria-label="Reason for declining pickup">
                                        <button class="ops-btn" type="submit" <?php if($courier->status !== 'approved'): echo 'disabled'; endif; ?>>
                                            Decline assignment
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5">
                                <div class="ops-empty">No unclaimed seller pickups are available.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="ops-pagination"><?php echo e($availablePickups->links()); ?></div>
    </section>

    
    <section class="ops-panel courier-pickup" id="accepted-pickups">
        <h2>Accepted pickups <span class="ops-muted"><?php echo e($claimedPickups->count()); ?> active</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Seller & Location</th>
                        <th>Claimed</th>
                        <th>Pickup custody confirmation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $claimedPickups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="ops-mono" data-label="Order"><?php echo e($order->order_number); ?><?php if (isset($component)) { $__componentOriginal2969209a6e703f0dcf1ea87165624c74 = $component; } ?>
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
<?php endif; ?></td>
                            <td data-label="Seller">
                                <strong><?php echo e($order->seller?->business_name ?? $order->seller?->first_name); ?></strong>
                                <div class="ops-muted"><?php echo e(trim(($order->seller?->house_number ? $order->seller->house_number.' ' : '').($order->seller?->street_address ?? ''))); ?>, <?php echo e($order->seller?->municipality); ?></div>
                                <?php if($order->seller?->contact_no): ?>
                                    <div>
                                        <a href="tel:<?php echo e($order->seller->contact_no); ?>" class="courier-tel-link">
                                            <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'phone']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'phone']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> <?php echo e($order->seller->contact_no); ?>

                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Claimed"><?php echo e($order->pickup_claimed_at?->format('d M H:i')); ?></td>
                            <td data-label="Next step">
                                <form method="POST" action="<?php echo e(route('courier.orders.confirmPickup', $order)); ?>" data-decision="courier.orders.confirmPickup" data-decision-title="Confirm physical pickup?" data-decision-message="Continue only after you physically received the parcel from the seller." data-decision-label="Confirm pickup" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                    <?php echo csrf_field(); ?>
                                    <?php if($order->pickup_arrived_at && $order->seller_handover_at): ?>
                                        <div class="ops-field">
                                            <label for="pickup-parcel-<?php echo e($order->id); ?>">Scan parcel label QR/code</label>
                                            <input id="pickup-parcel-<?php echo e($order->id); ?>" name="parcel_reference" data-qr-input required maxlength="100" placeholder="EZP:…" autocomplete="off">
                                        </div>
                                        <input type="hidden" name="method" value="manual">
                                        <div class="courier-actions">
                                            <button type="button" class="ops-btn" data-qr-start>Use camera</button>
                                            <button type="button" class="ops-btn" data-qr-stop hidden>Stop camera</button>
                                        </div>
                                        <video data-qr-video playsinline hidden></video>
                                        <p data-qr-status role="status" class="ops-muted">Scan the parcel label, or enter its code manually.</p>
                                    <?php endif; ?>
                                    <button class="ops-btn ops-btn--primary" <?php if($courier->status !== 'approved'): echo 'disabled'; endif; ?>>
                                        <?php echo e($order->pickup_arrived_at && $order->seller_handover_at ? 'Confirm scanned parcel possession' : ($order->pickup_arrived_at ? 'Waiting for seller handover' : 'Record arrival at seller')); ?>

                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="4">
                                <div class="ops-empty">No accepted pickups waiting for collection confirmation.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    
    <section class="ops-panel courier-pickup" id="hub-custody">
        <h2>Collected parcels heading to the hub <span class="ops-muted"><?php echo e($myActivePickups->count()); ?> parcels</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Seller</th>
                        <th>Collected</th>
                        <th>Hub status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $myActivePickups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="ops-mono" data-label="Order"><?php echo e($order->order_number); ?><?php if (isset($component)) { $__componentOriginal2969209a6e703f0dcf1ea87165624c74 = $component; } ?>
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
<?php endif; ?></td>
                            <td data-label="Seller"><?php echo e($order->seller?->business_name ?? $order->seller?->first_name); ?></td>
                            <td data-label="Collected"><?php echo e($order->picked_up_at?->format('d M H:i')); ?></td>
                            <td data-label="Status">
                                <span class="ops-status ops-status--amber">Awaiting hub receipt</span>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="4">
                                <div class="ops-empty">No parcels in transit to the sorting center.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    
    <section class="ops-panel courier-delivery" id="seller-returns">
        <h2>Return parcels to sellers <span class="ops-muted"><?php echo e($myReturns->count()); ?> pending</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Seller return address</th>
                        <th>Next step</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $myReturns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="ops-mono" data-label="Order"><?php echo e($order->order_number); ?><?php if (isset($component)) { $__componentOriginal2969209a6e703f0dcf1ea87165624c74 = $component; } ?>
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
<?php endif; ?></td>
                            <td data-label="Return address">
                                <strong><?php echo e($order->seller?->business_name ?? $order->seller?->first_name); ?></strong>
                                <div class="ops-muted"><?php echo e(trim(($order->seller?->house_number ? $order->seller->house_number.' ' : '').($order->seller?->street_address ?? ''))); ?>, <?php echo e($order->seller?->barangay); ?>, <?php echo e($order->seller?->municipality); ?></div>
                                <?php if($order->seller?->contact_no): ?>
                                    <div>
                                        <a href="tel:<?php echo e($order->seller->contact_no); ?>" class="courier-tel-link">
                                            <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'phone']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'phone']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> <?php echo e($order->seller->contact_no); ?>

                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Action">
                                <form method="POST" action="<?php echo e(route('courier.orders.confirmReturnDelivery', $order)); ?>" data-decision="courier.orders.confirmReturnDelivery" data-decision-title="Confirm return handover?" data-decision-message="Continue only after you physically handed the returned parcel to the seller." data-decision-label="Confirm handover" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                    <?php echo csrf_field(); ?>
                                    <div class="ops-field">
                                        <label for="return-parcel-<?php echo e($order->id); ?>">Scan parcel label QR/code</label>
                                        <input id="return-parcel-<?php echo e($order->id); ?>" name="parcel_reference" data-qr-input required maxlength="100" placeholder="EZP:…" autocomplete="off">
                                    </div>
                                    <input type="hidden" name="method" value="manual">
                                    <div class="courier-actions">
                                        <button type="button" class="ops-btn" data-qr-start>Use camera</button>
                                        <button type="button" class="ops-btn" data-qr-stop hidden>Stop camera</button>
                                    </div>
                                    <video data-qr-video playsinline hidden></video>
                                    <p data-qr-status role="status" class="ops-muted">Camera, handheld scanner, or manual entry.</p>
                                    <button class="ops-btn ops-btn--primary" <?php if($courier->status !== 'approved'): echo 'disabled'; endif; ?>>
                                        Record scanned seller handoff
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="3">
                                <div class="ops-empty">No return parcels are waiting for seller handoff.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    
    <section class="ops-panel courier-delivery courier-failed" id="failed-deliveries">
        <h2>
            Failed deliveries requiring logistics follow-up
            <span class="ops-muted"><?php echo e($myFailedDeliveries->count()); ?> active</span>
        </h2>
        <?php $__empty_1 = true; $__currentLoopData = $myFailedDeliveries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <article class="courier-failed-alert-card">
                <div class="courier-failed-header">
                    <div>
                        <div class="ops-mono">
                            <a href="<?php echo e(route('courier.orders.show', $order)); ?>"><strong>Order <?php echo e($order->order_number); ?></strong><?php if (isset($component)) { $__componentOriginal2969209a6e703f0dcf1ea87165624c74 = $component; } ?>
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
<?php endif; ?></a>
                        </div>
                        <div class="ops-muted">Recipient: <?php echo e($order->recipient_name); ?> · <?php echo e($order->delivery_area ?? $order->municipality); ?></div>
                    </div>
                    <span class="courier-failed-reason-tag">
                        <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'warning']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> <?php echo e(str_replace('_', ' ', ucfirst($order->delivery_failure_reason))); ?>

                    </span>
                </div>
                <div class="courier-failed-guidance">
                    <strong>What happens next?</strong>
                    Logistics will review the recorded attempt and decide the next step. You cannot retry or return this parcel from the courier screen. The parcel must be physically checked into the hub before retry or return.
                </div>
                <div class="courier-actions">
                    <span class="ops-muted">Recorded: <?php echo e($order->failed_at?->format('d M Y H:i')); ?></span>
                    <a class="ops-btn" href="<?php echo e(route('courier.orders.show', $order)); ?>">View delivery record</a>
                    <a class="ops-btn" href="<?php echo e(route('courier.orders.messages.show', $order)); ?>">Order messages</a>
                </div>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="ops-empty">No failed deliveries need follow-up. All parcels are clear.</div>
        <?php endif; ?>
    </section>

    
    <section class="ops-panel courier-delivery" id="delivery-assignments">
        <h2>Doorstep delivery work <span class="ops-muted"><?php echo e($myDeliveryAssignments->total()); ?> assignments</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Order / Stage</th>
                        <th>Recipient & Destination</th>
                        <th>Payment</th>
                        <th>Delivery action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $myDeliveryAssignments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="ops-mono" data-label="Order">
                                <a href="<?php echo e(route('courier.orders.show', $order)); ?>"><strong><?php echo e($order->order_number); ?></strong><?php if (isset($component)) { $__componentOriginal2969209a6e703f0dcf1ea87165624c74 = $component; } ?>
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
<?php endif; ?></a>
                                <div><span class="ops-status <?php echo e($order->status === 'OUT_FOR_DELIVERY' ? 'ops-status--pink' : 'ops-status--amber'); ?>"><?php echo e(str_replace('_', ' ', $order->status)); ?></span></div>
                                <div class="ops-muted">Assigned <?php echo e($order->assigned_at?->diffForHumans()); ?></div>
                            </td>
                            <td data-label="Recipient">
                                <strong><?php echo e($order->recipient_name); ?></strong>
                                <div class="ops-muted"><?php echo e(trim(($order->house_number ? $order->house_number.' ' : '').$order->street_address)); ?>, <?php echo e($order->barangay); ?>, <?php echo e($order->municipality); ?></div>
                                <div class="ops-muted"><?php echo e($order->delivery_area); ?></div>
                                <div>
                                    <a href="tel:<?php echo e($order->recipient_contact); ?>" class="courier-tel-link">
                                        <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'phone']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'phone']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> <?php echo e($order->recipient_contact); ?>

                                    </a>
                                </div>
                            </td>
                            <td data-label="Payment">
                                <?php if($order->payment_method === 'COD'): ?>
                                    <div class="courier-cod-badge">
                                        <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'money']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'money']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?> COD: ₱<?php echo e(number_format($order->total_amount, 2)); ?>

                                    </div>
                                <?php else: ?>
                                    <span class="ops-status ops-status--green"><?php echo e($order->payment_method); ?></span>
                                    <div class="ops-muted">₱<?php echo e(number_format($order->total_amount, 2)); ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Delivery action">
                                <?php if($order->status === 'ASSIGNED_TO_RIDER'): ?>
                                    <?php if($order->hub_released_at): ?>
                                        <form method="POST" action="<?php echo e(route('courier.orders.startDelivery', $order)); ?>" data-decision="courier.orders.startDelivery" data-decision-title="Start delivery?" data-decision-message="This records that the parcel is going out for delivery." data-decision-label="Start delivery" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                            <?php echo csrf_field(); ?>
                                            <div class="ops-field">
                                                <label for="start-parcel-reference-<?php echo e($order->id); ?>">Parcel QR/code (optional manual handoff fallback)</label>
                                                <input id="start-parcel-reference-<?php echo e($order->id); ?>" name="parcel_reference" maxlength="100" placeholder="EZP:…" autocomplete="off">
                                            </div>
                                            <input type="hidden" name="method" value="manual">
                                            <button class="ops-btn ops-btn--primary" <?php if($courier->status !== 'approved'): echo 'disabled'; endif; ?>>
                                                Start delivery
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="ops-banner ops-banner--urgent">
                                            <span>Waiting for Logistics hub release</span>
                                        </div>
                                        <form class="ops-form" method="POST" action="<?php echo e(route('courier.orders.declineDeliveryAssignment', $order)); ?>" data-decision="courier.orders.declineDeliveryAssignment" data-decision-title="Decline delivery assignment?" data-decision-message="This returns the assignment to dispatch for review and reassignment." data-decision-label="Decline assignment" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                            <?php echo csrf_field(); ?>
                                            <div class="ops-field">
                                                <label for="decline-reason-<?php echo e($order->id); ?>">Reason (optional)</label>
                                                <input id="decline-reason-<?php echo e($order->id); ?>" name="reason" maxlength="500">
                                            </div>
                                            <button class="ops-btn ops-btn--danger" type="submit" <?php if($courier->status !== 'approved'): echo 'disabled'; endif; ?>>
                                                Decline assignment
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <details>
                                        <summary class="ops-btn ops-btn--primary">Complete delivery</summary>
                                        <form class="ops-form" method="POST" action="<?php echo e(route('courier.orders.completeDelivery', $order)); ?>" enctype="multipart/form-data" data-decision="courier.orders.completeDelivery" data-decision-title="Confirm successful delivery?" data-decision-message="Continue only after handing the parcel to the recipient and recording the required delivery evidence and COD details." data-decision-label="Confirm delivery" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('PATCH'); ?>
                                            <div class="ops-field">
                                                <label for="recipient-confirmation-<?php echo e($order->id); ?>">Recipient confirmation</label>
                                                <input id="recipient-confirmation-<?php echo e($order->id); ?>" name="recipient_confirmation" maxlength="120" required>
                                            </div>
                                            <div class="ops-field">
                                                <label for="buyer-delivery-code-<?php echo e($order->id); ?>">Buyer delivery code</label>
                                                <input id="buyer-delivery-code-<?php echo e($order->id); ?>" class="courier-code-input" name="delivery_code" inputmode="numeric" autocomplete="off" maxlength="32" required placeholder="000000">
                                            </div>
                                            <?php if($order->payment_method === 'COD'): ?>
                                                <div class="ops-field">
                                                    <label for="cod-collected-amount-<?php echo e($order->id); ?>">Cash collected (₱<?php echo e(number_format($order->total_amount, 2)); ?>)</label>
                                                    <input id="cod-collected-amount-<?php echo e($order->id); ?>" type="number" step="0.01" min="0" name="cod_collected_amount" required value="<?php echo e($order->total_amount); ?>">
                                                </div>
                                            <?php endif; ?>
                                            <div class="ops-field">
                                                <label for="delivery-notes-<?php echo e($order->id); ?>">Delivery notes</label>
                                                <input id="delivery-notes-<?php echo e($order->id); ?>" name="delivery_notes" maxlength="1000">
                                            </div>
                                            <div class="ops-field courier-file-card">
                                                <label for="proof-file-<?php echo e($order->id); ?>">Proof of delivery photo or document</label>
                                                <input id="proof-file-<?php echo e($order->id); ?>" type="file" name="proof_file" accept=".jpg,.jpeg,.png,.pdf" required>
                                            </div>
                                            <button class="ops-btn ops-btn--primary" type="submit">Confirm delivered</button>
                                        </form>
                                    </details>
                                    <details>
                                        <summary class="ops-btn ops-btn--danger">Record failed attempt</summary>
                                        <form class="ops-form" method="POST" action="<?php echo e(route('courier.orders.failDelivery', $order)); ?>" data-decision="courier.orders.failDelivery" data-decision-title="Record failed delivery?" data-decision-message="Check the failure reason and evidence. This records a delivery attempt against the parcel." data-decision-label="Record failed attempt" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('PATCH'); ?>
                                            <div class="ops-field">
                                                <label for="failure-reason-<?php echo e($order->id); ?>">Reason</label>
                                                <select id="failure-reason-<?php echo e($order->id); ?>" name="failure_reason" required>
                                                    <option value="">Select reason</option>
                                                    <option value="recipient_unavailable">Recipient unavailable</option>
                                                    <option value="incorrect_address">Incorrect address</option>
                                                    <option value="recipient_refused">Recipient refused</option>
                                                    <option value="unreachable_contact">Unreachable contact</option>
                                                    <option value="access_issue">Access issue</option>
                                                    <option value="damaged_parcel">Damaged parcel</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                            <div class="ops-field">
                                                <label for="failure-notes-<?php echo e($order->id); ?>">Notes</label>
                                                <input id="failure-notes-<?php echo e($order->id); ?>" name="delivery_notes" maxlength="1000">
                                            </div>
                                            <button class="ops-btn ops-btn--danger" type="submit">Record failure</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="4">
                                <div class="ops-empty">No delivery parcels are assigned to you.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="ops-pagination"><?php echo e($myDeliveryAssignments->links()); ?></div>
    </section>

    
    <details class="ops-panel courier-badge-details">
        <summary>Rider identification badge</summary>
        <p class="ops-muted">Show this QR at the hub or seller handoff. Staff actions still require their own authenticated account and assignment checks.</p>
        <p class="ops-mono">EZR:<?php echo e($badgeCode); ?></p>
        <div class="courier-badge-qr" role="img" aria-label="QR code for rider identification">
            <?php echo $badgeQr; ?>

        </div>
        <form method="POST" action="<?php echo e(route('courier.badge.rotate')); ?>" data-decision="courier.badge.rotate" data-decision-title="Replace your rider badge?" data-decision-message="Printed copies of the old QR code will stop identifying your badge." data-decision-label="Replace badge" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
            <?php echo csrf_field(); ?>
            <button class="ops-btn" type="submit">Rotate rider badge</button>
        </form>
    </details>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.courier', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/courier/dashboard.blade.php ENDPATH**/ ?>