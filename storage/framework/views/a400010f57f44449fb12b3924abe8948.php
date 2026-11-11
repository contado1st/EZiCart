<?php $__env->startSection('workspace'); ?>
    <?php echo $__env->make('components.order-deadlines', ['order' => $order], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <header class="ops-heading">
        <div>
            <h1>Delivery details</h1>
            <p>Order <?php echo e($order->order_number); ?> · <?php echo e($order->recipient_name); ?> · <?php echo e($order->recipient_contact); ?> · <?php echo e($order->delivery_area ?? $order->municipality); ?></p>
        </div>
        <div class="courier-actions">
            <span class="ops-status <?php echo e($order->status === 'DELIVERY_FAILED' ? 'ops-status--danger' : ($order->status === 'OUT_FOR_DELIVERY' ? 'ops-status--pink' : 'ops-status--amber')); ?>">
                <?php echo e(str_replace('_', ' ', $order->status)); ?>

            </span>
            <a class="ops-btn" href="<?php echo e(route('courier.orders.messages.show', $order)); ?>">Order messages</a>
            <a class="ops-btn" href="<?php echo e(route('courier.dashboard')); ?>">Back to board</a>
        </div>
    </header>

    <?php if($order->payment_method !== 'COD' && !in_array($order->status, ['CANCELLED', 'COMPLETED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'], true)): ?>
        <section class="ops-panel ops-alert--error" role="status">
            Fulfillment is on hold because payment verification for <?php echo e($order->payment_method); ?> is not configured yet.
        </section>
    <?php endif; ?>

    
    <?php if($order->status === 'DELIVERY_FAILED'): ?>
        <section class="ops-panel courier-failed" role="status" aria-live="polite">
            <div class="courier-failed-header">
                <h2>Delivery attempt recorded</h2>
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
                <strong>Logistics Hub Review Pending</strong>
                <p class="courier-status-copy">
                    Logistics will review the recorded attempt and decide whether to assign another delivery or arrange a return. No retry or return action is available on the courier screen yet. The parcel must be physically checked into the hub before retry or return.
                </p>
            </div>
            <div class="courier-actions">
                <a class="ops-btn" href="<?php echo e(route('courier.dashboard')); ?>">Return to work board</a>
                <a class="ops-btn" href="<?php echo e(route('courier.orders.messages.show', $order)); ?>">Message Logistics</a>
            </div>
        </section>
    <?php endif; ?>

    <section class="ops-grid courier-order-summary" aria-label="Delivery and parcel information">
        <div class="ops-panel">
            <h2>Drop-off destination</h2>
            <p><strong><?php echo e($order->recipient_name); ?></strong></p>
            <p><?php echo e(trim(($order->house_number ? $order->house_number.' ' : '').$order->street_address)); ?>, <?php echo e($order->barangay); ?>, <?php echo e($order->municipality); ?>, <?php echo e($order->province); ?></p>
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
<?php endif; ?> Call <?php echo e($order->recipient_contact); ?>

                </a>
            </div>
            <div class="ops-field">
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
<?php endif; ?> Cash on Delivery: ₱<?php echo e(number_format($order->total_amount, 2)); ?>

                    </div>
                <?php else: ?>
                    <span class="ops-status ops-status--green"><?php echo e($order->payment_method); ?></span>
                    <span class="ops-muted">₱<?php echo e(number_format($order->total_amount, 2)); ?></span>
                <?php endif; ?>
            </div>
            <p class="ops-muted">Order notes: <?php echo e($order->notes ?? 'None provided'); ?></p>
        </div>

        <div class="ops-panel">
            <h2>Parcel contents</h2>
            <?php $__empty_1 = true; $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <p><strong><?php echo e($item->product_name); ?></strong> × <?php echo e($item->quantity); ?></p>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="ops-muted">No item summary recorded.</p>
            <?php endif; ?>
            <div class="ops-muted">
                <p>Rider assignment: <?php echo e($order->assigned_at?->format('d M Y H:i') ?? '—'); ?></p>
                <p>Out for delivery: <?php echo e($order->out_for_delivery_at?->format('d M Y H:i') ?? 'Not started'); ?></p>
            </div>
        </div>
    </section>

    <?php if($order->delivery_courier_id === auth()->id() && $order->status === 'ASSIGNED_TO_RIDER'): ?>
        <section class="ops-panel courier-delivery">
            <h2>Start delivery</h2>
            <?php if($order->payment_method !== 'COD'): ?>
                <p class="ops-muted">Delivery is on hold until payment verification for this method is configured.</p>
            <?php elseif($order->hub_released_at): ?>
                <div class="ops-banner ops-banner--success">
                    <span>Logistics confirmed the hub release at <?php echo e($order->hub_released_at->format('d M Y H:i')); ?>.</span>
                </div>
                <form class="ops-form" method="POST" action="<?php echo e(route('courier.orders.startDelivery', $order)); ?>" data-decision="courier.orders.startDelivery" data-decision-title="Start delivery?" data-decision-message="This records that the parcel is going out for delivery." data-decision-label="Start delivery" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    <?php echo csrf_field(); ?>
                    <div class="ops-field">
                        <label for="start-parcel-reference">Parcel QR/code (optional manual handoff fallback)</label>
                        <input id="start-parcel-reference" name="parcel_reference" maxlength="100" placeholder="EZP:…" autocomplete="off">
                    </div>
                    <input type="hidden" name="method" value="manual">
                    <button class="ops-btn ops-btn--primary ops-btn--lg">Start delivery</button>
                </form>
            <?php else: ?>
                <div class="ops-banner ops-banner--urgent">
                    <span>Waiting for Logistics to verify and release this parcel from the hub.</span>
                </div>
                <form class="ops-form" method="POST" action="<?php echo e(route('courier.orders.declineDeliveryAssignment', $order)); ?>" data-decision="courier.orders.declineDeliveryAssignment" data-decision-title="Decline delivery assignment?" data-decision-message="This returns the assignment to dispatch for review and reassignment." data-decision-label="Decline assignment" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    <?php echo csrf_field(); ?>
                    <div class="ops-field">
                        <label for="assignment-decline-reason">Reason (optional)</label>
                        <input id="assignment-decline-reason" name="reason" maxlength="500">
                    </div>
                    <button class="ops-btn ops-btn--danger" type="submit">Decline assignment</button>
                </form>
            <?php endif; ?>
        </section>
    <?php elseif($order->delivery_courier_id === auth()->id() && $order->status === 'OUT_FOR_DELIVERY'): ?>
        <section class="ops-panel courier-delivery">
            <h2>Delivery result</h2>
            <div class="ops-grid">
                <?php if($order->payment_method === 'COD'): ?>
                    <div class="ops-panel">
                        <h3>Complete delivery</h3>
                        <form class="ops-form" method="POST" action="<?php echo e(route('courier.orders.completeDelivery', $order)); ?>" enctype="multipart/form-data" data-decision="courier.orders.completeDelivery" data-decision-title="Confirm successful delivery?" data-decision-message="Continue only after handing the parcel to the recipient and recording the required delivery evidence and COD details." data-decision-label="Confirm delivery" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <div class="ops-field">
                                <label for="recipient-confirmation">Recipient confirmation</label>
                                <input id="recipient-confirmation" name="recipient_confirmation" maxlength="120" required placeholder="Name of person receiving parcel">
                            </div>
                            <div class="ops-field">
                                <label for="buyer-delivery-code">Buyer delivery code</label>
                                <input id="buyer-delivery-code" class="courier-code-input" name="delivery_code" inputmode="numeric" autocomplete="off" maxlength="32" required placeholder="000000">
                            </div>
                            <div class="ops-field">
                                <label for="cod-collected-amount">Cash collected (₱<?php echo e(number_format($order->total_amount, 2)); ?>)</label>
                                <input id="cod-collected-amount" name="cod_collected_amount" type="number" min="0" step="0.01" required value="<?php echo e($order->total_amount); ?>">
                            </div>
                            <div class="ops-field">
                                <label for="delivery-notes">Delivery notes</label>
                                <input id="delivery-notes" name="delivery_notes" maxlength="1000" placeholder="Optional notes (e.g. left with front desk)">
                            </div>
                            <div class="ops-field courier-file-card">
                                <label for="proof-file">Proof of delivery (photo or PDF)</label>
                                <input id="proof-file" type="file" name="proof_file" accept=".jpg,.jpeg,.png,.pdf" required>
                            </div>
                            <button class="ops-btn ops-btn--primary ops-btn--lg" type="submit">Confirm delivered</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="ops-panel">
                        <p class="ops-muted">Delivery confirmation is unavailable until payment verification is configured.</p>
                    </div>
                <?php endif; ?>

                <div class="ops-panel ops-panel--urgent">
                    <h3>Record failed attempt</h3>
                    <form class="ops-form" method="POST" action="<?php echo e(route('courier.orders.failDelivery', $order)); ?>" data-decision="courier.orders.failDelivery" data-decision-title="Record failed delivery?" data-decision-message="Check the failure reason and evidence. This records a delivery attempt against the parcel." data-decision-label="Record failed attempt" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <div class="ops-field">
                            <label for="failure-reason">Failure reason</label>
                            <select id="failure-reason" name="failure_reason" required>
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
                            <label for="failure-notes">Notes</label>
                            <input id="failure-notes" name="delivery_notes" maxlength="1000" placeholder="Describe reason for delivery failure">
                        </div>
                        <button class="ops-btn ops-btn--danger ops-btn--lg" type="submit">Record failure</button>
                    </form>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="ops-panel">
        <h2>Delivery attempts <span class="ops-muted"><?php echo e($order->deliveryAttempts->count()); ?> recorded</span></h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Attempt</th>
                        <th>Outcome</th>
                        <th>Reason</th>
                        <th>Time</th>
                        <th>Proof</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $order->deliveryAttempts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attempt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td data-label="Attempt">Attempt <?php echo e($attempt->attempt_no); ?></td>
                            <td data-label="Outcome">
                                <span class="ops-status <?php echo e($attempt->outcome === 'failed' ? 'ops-status--danger' : 'ops-status--green'); ?>">
                                    <?php echo e(ucfirst($attempt->outcome)); ?>

                                </span>
                            </td>
                            <td data-label="Reason"><?php echo e($attempt->reason ? str_replace('_', ' ', $attempt->reason) : '—'); ?></td>
                            <td data-label="Time" class="ops-mono">
                                <?php echo e($attempt->attempted_at?->format('d M Y H:i') ?? 'Not recorded'); ?>

                                <?php if($attempt->scheduled_at): ?>
                                    <div class="ops-muted">Scheduled: <?php echo e($attempt->scheduled_at->format('d M Y H:i')); ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Proof">
                                <?php if($attempt->proof_path): ?>
                                    <a class="ops-btn" href="<?php echo e(route('delivery-attempts.proof', $attempt)); ?>">Download proof</a>
                                <?php else: ?>
                                    <span class="ops-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5">
                                <div class="ops-empty">No delivery attempts recorded.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="ops-panel">
        <h2>Parcel tracking timeline</h2>
        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Time</th>
                        <th>Location</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $order->trackingEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td data-label="Event"><?php echo e(str_replace('_', ' ', $event->event_type)); ?></td>
                            <td data-label="Time" class="ops-mono"><?php echo e($event->created_at->format('d M Y H:i')); ?></td>
                            <td data-label="Location"><?php echo e($event->location ?? '—'); ?></td>
                            <td data-label="Notes"><?php echo e($event->notes ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="4">
                                <div class="ops-empty">No tracking updates recorded.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.courier', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/courier/orders/show.blade.php ENDPATH**/ ?>