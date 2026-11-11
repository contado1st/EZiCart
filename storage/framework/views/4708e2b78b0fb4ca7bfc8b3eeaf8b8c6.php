<?php $__env->startSection('workspace'); ?>
    <header class="ops-heading">
        <div>
            <h1>Delivery tracking</h1>
            <p>Current stage, rider ownership, milestone progression, and persisted parcel events.</p>
        </div>
    </header>

    <section class="ops-panel">
        <form class="ops-form" method="GET" action="<?php echo e(route('logistics.tracking')); ?>" aria-label="Filter parcel tracking">
            <div class="ops-field">
                <label for="tracking-order-search">Order number</label>
                <input id="tracking-order-search" name="search" value="<?php echo e(request('search')); ?>" maxlength="100" placeholder="e.g. ORD-10293">
            </div>
            <div class="ops-field">
                <label for="tracking-status">Status</label>
                <select id="tracking-status" name="status">
                    <option value="">All active</option>
                    <?php $__currentLoopData = ['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($status); ?>" <?php if(request('status') === $status): echo 'selected'; endif; ?>>
                            <?php echo e(str_replace('_', ' ', $status)); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="ops-field">
                <label for="tracking-rider">Rider</label>
                <select id="tracking-rider" name="rider">
                    <option value="">All riders</option>
                    <?php $__currentLoopData = $riders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($rider->id); ?>" <?php if((string) request('rider') === (string) $rider->id): echo 'selected'; endif; ?>>
                            <?php echo e($rider->first_name); ?> <?php echo e($rider->last_name); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="ops-field">
                <label for="tracking-rider-search">Search rider list</label>
                <input id="tracking-rider-search" name="rider_search" value="<?php echo e(request('rider_search')); ?>" maxlength="100" placeholder="First or last name">
                <span class="ops-muted">Up to 50 hub riders. Search to find others.</span>
            </div>
            <div class="ops-field">
                <label for="tracking-area">Area</label>
                <select id="tracking-area" name="area">
                    <option value="">All areas</option>
                    <?php $__currentLoopData = $areas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $area): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($area->id); ?>" <?php if((string) request('area') === (string) $area->id): echo 'selected'; endif; ?>>
                            <?php echo e($area->name); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="ops-field">
                <label for="tracking-date">Updated date</label>
                <input id="tracking-date" type="date" name="date" value="<?php echo e(request('date')); ?>">
            </div>
            <button class="ops-btn ops-btn--primary" type="submit">Apply filters</button>
            <?php if(request()->filled('search') || request()->filled('status') || request()->filled('rider') || request()->filled('rider_search') || request()->filled('area') || request()->filled('date')): ?>
                <a class="ops-btn" href="<?php echo e(route('logistics.tracking')); ?>">Clear filters</a>
            <?php endif; ?>
        </form>

        <?php if(request()->filled('search') || request()->filled('status') || request()->filled('rider') || request()->filled('rider_search') || request()->filled('area') || request()->filled('date')): ?>
            <ul class="ops-filter-chips" aria-label="Active filters">
                <?php if(request()->filled('search')): ?>
                    <li>Order: <strong><?php echo e(request('search')); ?></strong></li>
                <?php endif; ?>
                <?php if(request()->filled('status')): ?>
                    <li>Status: <strong><?php echo e(str_replace('_', ' ', request('status'))); ?></strong></li>
                <?php endif; ?>
                <?php if(request()->filled('rider') && ($selectedRider = $riders->firstWhere('id', request('rider')))): ?>
                    <li>Rider: <strong><?php echo e($selectedRider->first_name); ?> <?php echo e($selectedRider->last_name); ?></strong></li>
                <?php endif; ?>
                <?php if(request()->filled('rider_search')): ?>
                    <li>Rider search: <strong><?php echo e(request('rider_search')); ?></strong></li>
                <?php endif; ?>
                <?php if(request()->filled('area') && ($selectedArea = $areas->firstWhere('id', request('area')))): ?>
                    <li>Area: <strong><?php echo e($selectedArea->name); ?></strong></li>
                <?php endif; ?>
                <?php if(request()->filled('date')): ?>
                    <li>Date: <strong><?php echo e(request('date')); ?></strong></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <section class="ops-panel <?php echo e($order->status === 'DELIVERY_FAILED' ? 'ops-panel--urgent' : ''); ?>">
            <div class="ops-heading">
                <div>
                    <p class="ops-mono">Order <?php echo e($order->order_number); ?></p>
                    <h2><?php echo e($order->recipient_name); ?> · <?php echo e($order->municipality); ?></h2>
                    <p>
                        Rider: <strong><?php echo e($order->deliveryCourier ? $order->deliveryCourier->first_name . ' ' . $order->deliveryCourier->last_name : 'No delivery rider assigned'); ?></strong>
                        · Updated <?php echo e($order->updated_at->diffForHumans()); ?>

                    </p>
                </div>
                <div class="courier-actions">
                    <span class="ops-status <?php echo e($order->status === 'DELIVERY_FAILED' ? 'ops-status--danger' : ($order->status === 'RETURN_IN_TRANSIT' ? 'ops-status--amber' : ($order->status === 'DELIVERED' || $order->status === 'COMPLETED' ? 'ops-status--green' : 'ops-status--pink'))); ?>">
                        <?php echo e(str_replace('_', ' ', $order->status)); ?>

                    </span>
                    <?php if($order->status === 'DELIVERY_FAILED'): ?>
                        <form method="POST" action="<?php echo e(route('logistics.orders.return', $order)); ?>" data-decision="logistics.orders.return" data-decision-title="Start return handling?" data-decision-message="This moves the selected parcel into the return workflow. Check the reason first." data-decision-label="Start return" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                            <?php echo csrf_field(); ?>
                            <button class="ops-btn ops-btn--danger" type="submit">Return to sender</button>
                        </form>
                    <?php endif; ?>
                    <?php if($order->messageParticipants()->contains('id', auth()->id())): ?>
                        <a class="ops-btn" href="<?php echo e(route('logistics.orders.messages.show', $order)); ?>">Order messages</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if($order->status === 'RETURN_IN_TRANSIT'): ?>
                <div class="ops-banner ops-banner--urgent">
                    <span><?php echo e($order->return_handed_to_seller_at ? 'Seller receipt confirmation pending' : 'Awaiting courier to record seller handoff'); ?></span>
                </div>
            <?php endif; ?>

            
            <?php
                $stages = [
                    'PICKED_UP' => 'Picked up',
                    'AT_SORTING_CENTER' => 'At hub',
                    'SORTED' => 'Sorted',
                    'OUT_FOR_DELIVERY' => 'Out for delivery',
                    'DELIVERED' => 'Delivered',
                ];
                $orderStatus = $order->status;
                $isFailed = $orderStatus === 'DELIVERY_FAILED';
                $isReturn = in_array($orderStatus, ['RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'], true);
                $statusOrder = ['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERED', 'COMPLETED'];
                $currentIndex = array_search($orderStatus, $statusOrder, true);
                if ($currentIndex === false) {
                    $currentIndex = $isFailed ? 4 : ($isReturn ? 4 : 0);
                }
            ?>
            <div class="ops-stepper" aria-label="Parcel milestone progression">
                <div class="ops-step <?php echo e($currentIndex >= 0 ? 'is-done' : ''); ?>">
                    <span class="ops-step__indicator">1</span>
                    <span class="ops-step__label">Picked up</span>
                </div>
                <div class="ops-step <?php echo e($currentIndex >= 1 ? 'is-done' : ($currentIndex === 0 ? 'is-active' : '')); ?>">
                    <span class="ops-step__indicator">2</span>
                    <span class="ops-step__label">At Hub</span>
                </div>
                <div class="ops-step <?php echo e($currentIndex >= 2 ? 'is-done' : ($currentIndex === 1 ? 'is-active' : '')); ?>">
                    <span class="ops-step__indicator">3</span>
                    <span class="ops-step__label">Sorted</span>
                </div>
                <div class="ops-step <?php echo e($isFailed ? 'is-failed' : ($currentIndex >= 4 ? 'is-done' : ($currentIndex >= 3 ? 'is-active' : ''))); ?>">
                    <span class="ops-step__indicator"><?php echo e($isFailed ? '!' : '4'); ?></span>
                    <span class="ops-step__label"><?php echo e($isFailed ? 'Failed' : ($isReturn ? 'Returning' : 'Delivery')); ?></span>
                </div>
                <div class="ops-step <?php echo e(in_array($orderStatus, ['DELIVERED', 'COMPLETED', 'RETURNED_TO_SELLER'], true) ? 'is-done' : ''); ?>">
                    <span class="ops-step__indicator">✓</span>
                    <span class="ops-step__label"><?php echo e($isReturn ? 'Returned' : 'Complete'); ?></span>
                </div>
            </div>

            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Location</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $order->trackingEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td data-label="Event"><?php echo e(str_replace('_', ' ', $event->event_type)); ?></td>
                                <td data-label="Time" class="ops-mono"><?php echo e($event->created_at->format('d M Y H:i')); ?></td>
                                <td data-label="Actor"><?php echo e($event->actor ? $event->actor->first_name . ' ' . $event->actor->last_name : 'System'); ?></td>
                                <td data-label="Location"><?php echo e($event->location ?? '—'); ?></td>
                                <td data-label="Notes"><?php echo e($event->notes ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="ops-empty">No recorded events yet.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    <?php if($orders->isEmpty()): ?>
        <div class="ops-empty">No parcels match the selected filters.</div>
    <?php endif; ?>

    <div class="ops-pagination"><?php echo e($orders->links()); ?></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.logistics', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/logistics/tracking.blade.php ENDPATH**/ ?>