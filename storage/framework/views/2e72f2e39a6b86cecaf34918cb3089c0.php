<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/disputes.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <?php echo $__env->make('components.order-deadlines', ['order' => $order], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="dash-wrapper">
<main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Order <?php echo e($order->order_number); ?></h1>
                    <p class="dash-subtitle">Placed <?php echo e($order->created_at->format('M d, Y h:i A')); ?> with
                        <?php echo e($order->seller->business_name ?? 'Merchant'); ?></p>
                </div>
                <a href="<?php echo e(route('buyer.dashboard')); ?>" class="dash-btn-sm dash-btn-outline">Back to orders</a>
            </div>

            <?php if($order->purchase): ?>
                <section class="dash-panel"><h2>Purchase #<?php echo e($order->purchase->id); ?></h2><p>Purchase total: ₱<?php echo e(number_format($order->purchase->total_cents / 100, 2)); ?></p>
                    <?php $__currentLoopData = $order->purchase->orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sibling): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><a href="<?php echo e(route('buyer.orders.show', $sibling)); ?>"><?php echo e($sibling->order_number); ?></a> — <?php echo e($sibling->seller->business_name ?? 'Merchant'); ?><?php echo e($sibling->replacement_for_order_id ? ' (replacement shipment)' : ''); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </section>
            <?php endif; ?>

            <section class="dash-panel">
                <div class="order-card-header">
                    <div>
                        <span class="order-number"><?php echo e($order->order_number); ?></span>
                        <div class="order-date"><?php echo e($order->payment_method); ?></div>
                    </div>
                    <span
                        class="status-pill status-<?php echo e(strtolower(str_replace('_', '-', $order->status))); ?>"><?php echo e(str_replace('_', ' ', $order->status)); ?></span>
                </div>

                <?php if($order->payment_method !== 'COD' && ! in_array($order->status, ['CANCELLED', 'COMPLETED', 'RETURN_IN_TRANSIT', 'RETURNED_TO_SELLER'], true)): ?>
                    <p role="status">Fulfillment is on hold because payment verification for <?php echo e($order->payment_method); ?> is not configured yet.</p>
                <?php endif; ?>

                <?php if($order->status === 'OUT_FOR_DELIVERY' && $order->payment_method === 'COD'): ?>
                    <section aria-labelledby="delivery-code-heading">
                        <h2 id="delivery-code-heading" class="courier-section-title">Your delivery confirmation code</h2>
                        <?php if($deliveryCode): ?>
                            <p>Show this one-time code to your courier when your parcel arrives:</p>
                            <p><strong><?php echo e($deliveryCode); ?></strong></p>
                            <div aria-label="QR code for delivery confirmation"><?php echo $deliveryCodeQr; ?></div>
                            <p>Expires <?php echo e($order->delivery_code_expires_at?->format('M d, Y h:i A')); ?>. Keep it private until the courier is with you.</p>
                        <?php else: ?>
                            <p>The code expired or is locked. Generate a new one before the courier arrives.</p>
                        <?php endif; ?>
                        <?php if($order->delivery_code_used_at === null): ?>
                            <form method="POST" action="<?php echo e(route('buyer.orders.delivery-code.refresh', $order)); ?>" data-decision="buyer.orders.delivery-code.refresh" data-decision-title="Replace the delivery code?" data-decision-message="The previous code will stop working. Share the replacement only when you receive your parcel." data-decision-label="Replace code" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                <?php echo csrf_field(); ?>
                                <button class="dash-btn-sm dash-btn-outline" type="submit">Generate a new delivery code</button>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <h2 class="courier-section-title">Items</h2>
                <?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="order-item-row">
                        <div>
                            <strong><?php echo e($item->product_name); ?></strong>
                            <?php if($item->variation_info): ?>
                                <span>(<?php echo e($item->variation_info); ?>)</span>
                            <?php endif; ?>
                            <span>&times; <?php echo e($item->quantity); ?></span>
                        </div>
                        <div><?php echo e(number_format($item->item_total, 2)); ?></div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                <h2 class="courier-section-title">Delivery</h2>
                <p><strong><?php echo e($order->recipient_name); ?></strong> · <?php echo e($order->recipient_contact); ?></p>
                <p><?php echo e(trim(($order->house_number ? $order->house_number.' ' : '').$order->street_address)); ?>, <?php echo e($order->barangay); ?>, <?php echo e($order->municipality); ?>,
                    <?php echo e($order->province); ?></p>
                <?php if($order->notes): ?>
                    <p>Delivery notes: <?php echo e($order->notes); ?></p>
                <?php endif; ?>

                <h2 class="courier-section-title">Payment summary</h2>
                <p>Items: <?php echo e(number_format($order->subtotal, 2)); ?></p>
                <?php if($order->discount_amount): ?>
                    <p>Discount: −<?php echo e(number_format($order->discount_amount, 2)); ?> (<?php echo e($order->voucher_code); ?>)</p>
                <?php endif; ?>
                <p>Shipping: <?php echo e(number_format($order->shipping_fee, 2)); ?></p>
                <p><strong>Total: <?php echo e(number_format($order->total_amount, 2)); ?></strong></p>

                <h2 class="courier-section-title">Tracking history</h2>
                <?php $__empty_1 = true; $__currentLoopData = $order->trackingEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <p><strong><?php echo e(str_replace('_', ' ', $event->event_type)); ?></strong> ·
                        <?php echo e($event->created_at->format('M d, Y h:i A')); ?> · <?php echo e($event->location ?? '—'); ?> <?php if($event->actor): ?>
                            · <?php echo e(match ($event->actor->role) { 'admin' => 'Platform support', 'sorting_center' => 'Logistics', 'courier' => 'Courier', 'seller' => 'Seller', default => 'EZiCart' }); ?>

                        <?php endif; ?>
                    </p>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p>Tracking events will appear as the order moves through fulfillment.</p>
                <?php endif; ?>

                <h2 class="courier-section-title">Delivery attempts and proof</h2>
                <?php $__empty_1 = true; $__currentLoopData = $order->deliveryAttempts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attempt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <p>Attempt <?php echo e($attempt->attempt_no); ?> · <?php echo e(ucfirst($attempt->outcome)); ?>

                        <?php if($attempt->reason): ?> · <?php echo e(str_replace('_', ' ', $attempt->reason)); ?> <?php endif; ?>
                        · <?php echo e($attempt->attempted_at?->format('M d, Y h:i A') ?? 'Time unavailable'); ?>

                        <?php if($attempt->proof_path): ?>
                            · <a href="<?php echo e(route('delivery-attempts.proof', $attempt)); ?>">Download proof</a>
                        <?php endif; ?>
                    </p>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p>No delivery attempts are recorded yet.</p>
                <?php endif; ?>

                <div class="order-card-footer">
                    <a href="<?php echo e(route('buyer.orders.messages.show', $order)); ?>" class="dash-btn-sm dash-btn-outline">Message seller</a>
                    <?php if($order->dispute): ?>
                        <span class="status-pill">Dispute: <?php echo e(str_replace('_', ' ', $order->dispute->status)); ?></span>
                    <?php elseif(in_array($order->status, ['DELIVERED', 'COMPLETED'])): ?>
                        <a href="<?php echo e(route('buyer.orders.dispute.create', $order->id)); ?>" class="dash-btn-sm">File a
                            dispute</a>
                    <?php endif; ?>

                    <?php if($order->status === 'DELIVERED'): ?>
                        <form action="<?php echo e(route('buyer.orders.confirm', $order->id)); ?>" method="POST" data-decision="buyer.orders.confirm" data-decision-title="Confirm receipt?" data-decision-message="Confirm only after you have received the parcel and checked your items. This records your receipt of the order." data-decision-label="Confirm receipt" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="dash-btn-sm dash-btn-success">Confirm receipt</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/buyer/orders/show.blade.php ENDPATH**/ ?>