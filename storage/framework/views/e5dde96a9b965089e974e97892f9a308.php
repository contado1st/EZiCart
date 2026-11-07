<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <?php echo $__env->make('components.order-deadlines', ['order' => $order], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="dash-wrapper">
<main class="dash-main">
            <?php if(session('success')): ?>
                <p role="status"><?php echo e(session('success')); ?></p>
            <?php endif; ?>
            <?php if($errors->any()): ?>
                <div role="alert">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <p><?php echo e($message); ?></p>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
            <?php if($order->status === 'PLACED' && $order->seller_response_due_at): ?><p role="status">Confirm by <?php echo e($order->seller_response_due_at->format('M d, Y h:i A')); ?>.</p><?php endif; ?>
            <?php if(in_array($order->status, ['CONFIRMED', 'PREPARING'], true) && $order->preparation_due_at): ?><p role="status">Finish preparation by <?php echo e($order->preparation_due_at->format('M d, Y h:i A')); ?>.</p><?php endif; ?>
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Order <?php echo e($order->order_number); ?></h1>
                    <p class="dash-subtitle">Placed <?php echo e($order->created_at->format('M d, Y h:i A')); ?></p>
                </div>
                <a href="<?php echo e(route('seller.orders.index')); ?>" class="dash-btn-sm dash-btn-outline">Back to orders</a>
            </div>

            <section class="dash-panel">
                <div class="order-card-header">
                    <div>
                        <span class="order-number"><?php echo e($order->order_number); ?></span>
                        <div class="order-date">Payment: <?php echo e($order->payment_method); ?></div>
                    </div>
                    <span
                        class="status-pill status-<?php echo e(strtolower(str_replace('_', '-', $order->status))); ?>"><?php echo e(str_replace('_', ' ', $order->status)); ?></span>
                </div>

                <h2 class="courier-section-title">Recipient</h2>
                <p><strong><?php echo e($order->recipient_name); ?></strong> · <?php echo e($order->recipient_contact); ?></p>
                <p><?php echo e($order->street_address); ?>, <?php echo e($order->barangay); ?>, <?php echo e($order->municipality); ?>,
                    <?php echo e($order->province); ?></p>

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

                <h2 class="courier-section-title">Order summary</h2>
                <?php if($order->payment_method !== 'COD' && in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true)): ?>
                    <p role="status">Fulfillment is on hold because payment verification for <?php echo e($order->payment_method); ?> is not configured yet.</p>
                <?php endif; ?>
                <p>Subtotal: <?php echo e(number_format($order->subtotal, 2)); ?></p>
                <?php if($order->discount_amount): ?>
                    <p>Discount: −<?php echo e(number_format($order->discount_amount, 2)); ?> (<?php echo e($order->voucher_code); ?>)</p>
                <?php endif; ?>
                <p>Platform commission: <?php echo e(number_format($order->commission_fee, 2)); ?></p>
                <p>Shipping: <?php echo e(number_format($order->shipping_fee, 2)); ?></p>
                <p><strong>Total: <?php echo e(number_format($order->total_amount, 2)); ?></strong></p>
                <?php if($order->notes): ?>
                    <p>Order notes: <?php echo e($order->notes); ?></p>
                <?php endif; ?>

                <div class="order-card-footer">
                    <a href="<?php echo e(route('seller.orders.waybill', $order->id)); ?>" class="dash-btn-sm dash-btn-outline">View
                        waybill</a>
                    <a href="<?php echo e(route('seller.orders.messages.show', $order)); ?>" class="dash-btn-sm dash-btn-outline">Message buyer</a>
                    <?php if($order->payment_method !== 'COD' && in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true)): ?>
                        <span>Fulfillment is on hold until payment verification is configured.</span>
                    <?php elseif($order->status === 'PLACED'): ?>
                        <form action="<?php echo e(route('seller.orders.updateStatus', $order->id)); ?>" method="POST" data-decision="seller.orders.updateStatus" data-decision-title="Update order status?" data-decision-message="Check the selected status. This updates the order workflow and may notify the buyer and delivery staff." data-decision-label="Update status" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <input type="hidden" name="status" value="CONFIRMED">
                            <button type="submit" class="dash-btn-sm dash-btn-primary">Accept order</button>
                        </form>
                    <?php elseif($order->status === 'CONFIRMED'): ?>
                        <form action="<?php echo e(route('seller.orders.updateStatus', $order->id)); ?>" method="POST" data-decision="seller.orders.updateStatus" data-decision-title="Update order status?" data-decision-message="Check the selected status. This updates the order workflow and may notify the buyer and delivery staff." data-decision-label="Update status" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <input type="hidden" name="status" value="PREPARING">
                            <button type="submit" class="dash-btn-sm dash-btn-primary">Start preparing</button>
                        </form>
                    <?php elseif($order->status === 'PREPARING'): ?>
                        <form action="<?php echo e(route('seller.orders.updateStatus', $order->id)); ?>" method="POST" data-decision="seller.orders.updateStatus" data-decision-title="Update order status?" data-decision-message="Check the selected status. This updates the order workflow and may notify the buyer and delivery staff." data-decision-label="Update status" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <input type="hidden" name="status" value="READY_FOR_PICKUP">
                            <button type="submit" class="dash-btn-sm dash-btn-primary">Mark ready for pickup</button>
                        </form>
                    <?php elseif($order->status === 'READY_FOR_PICKUP' && ! $order->pickup_requested_at): ?>
                        <form action="<?php echo e(route('seller.orders.schedulePickup', $order)); ?>" method="POST" class="order-schedule-form" data-decision="seller.orders.schedulePickup" data-decision-title="Schedule pickup?" data-decision-message="Confirm the selected pickup details before requesting a rider." data-decision-label="Schedule pickup" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                            <?php echo csrf_field(); ?>
                            <label for="pickup-scheduled-for">Pickup date and time</label>
                            <input id="pickup-scheduled-for" type="datetime-local" name="pickup_scheduled_for" min="<?php echo e(now()->addMinutes(15)->format('Y-m-d\\TH:i')); ?>" required>
                            <label for="pickup-window">Pickup window</label>
                            <select id="pickup-window" name="pickup_window" required>
                                <option value="">Choose a window</option>
                                <option value="Morning (8 AM–12 PM)">Morning (8 AM–12 PM)</option>
                                <option value="Afternoon (12 PM–5 PM)">Afternoon (12 PM–5 PM)</option>
                                <option value="Evening (5 PM–8 PM)">Evening (5 PM–8 PM)</option>
                            </select>
                            <label for="pickup-notes">Pickup instructions (optional)</label>
                            <textarea id="pickup-notes" name="pickup_notes" maxlength="1000"></textarea>
                            <button type="submit" class="dash-btn-sm dash-btn-primary">Request pickup</button>
                        </form>
                    <?php elseif($order->status === 'READY_FOR_PICKUP' && $order->pickup_requested_at && ! $order->pickup_arrived_at): ?>
                        <p>Pickup requested for <?php echo e($order->pickup_scheduled_for?->format('M d, Y h:i A')); ?> · <?php echo e($order->pickup_window); ?>. Waiting for Logistics to assign a rider.</p>
                    <?php elseif($order->status === 'READY_FOR_PICKUP' && $order->pickup_arrived_at && ! $order->seller_handover_at): ?>
                        <form action="<?php echo e(route('seller.orders.confirmHandover', $order)); ?>" method="POST" data-decision="seller.orders.confirmHandover" data-decision-title="Confirm physical handover?" data-decision-message="Continue only after you physically handed the parcel to the assigned rider." data-decision-label="Confirm handover" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                            <?php echo csrf_field(); ?>
                            <label for="handover-rider-badge">Scan assigned rider badge QR/code</label>
                            <input id="handover-rider-badge" name="rider_badge" data-qr-input required maxlength="100" placeholder="EZR:…" autocomplete="off">
                            <input type="hidden" name="method" value="manual">
                            <button type="button" data-qr-start>Use camera</button><button type="button" data-qr-stop hidden>Stop camera</button>
                            <video data-qr-video playsinline hidden></video><p data-qr-status role="status">Camera, handheld scanner, or manual code entry is supported.</p>
                            <button type="submit" class="dash-btn-sm dash-btn-primary">Confirm rider handover</button>
                        </form>
                    <?php elseif($order->status === 'RETURN_IN_TRANSIT'): ?>
                        <?php if($order->return_handed_to_seller_at): ?>
                            <form action="<?php echo e(route('seller.orders.confirmReturn', $order)); ?>" method="POST" data-decision="seller.orders.confirmReturn" data-decision-title="Confirm returned parcel?" data-decision-message="Continue only after you physically received the returned parcel." data-decision-label="Confirm return" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                <?php echo csrf_field(); ?>
                                <label for="return-rider-badge">Scan return rider badge QR/code</label>
                                <input id="return-rider-badge" name="rider_badge" data-qr-input required maxlength="100" placeholder="EZR:…" autocomplete="off">
                                <input type="hidden" name="method" value="manual">
                                <button type="button" data-qr-start>Use camera</button><button type="button" data-qr-stop hidden>Stop camera</button>
                                <video data-qr-video playsinline hidden></video><p data-qr-status role="status">Camera, handheld scanner, or manual entry.</p>
                                <button type="submit" class="dash-btn-sm dash-btn-primary">Confirm scanned return received</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted-small">Awaiting courier handoff</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if(!$order->pendingCancellation && in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true) && $order->picked_up_at === null && $order->seller_handover_at === null): ?>
                        <form action="<?php echo e(route('seller.orders.cancel', $order)); ?>" method="POST" class="order-schedule-form" data-decision="seller.orders.cancel" data-decision-title="Cancel this order?" data-decision-message="Review the order and cancellation reason before continuing." data-decision-label="Cancel order" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                            <?php echo csrf_field(); ?>
                            <label for="seller-cancel-reason">Reason for cancelling</label>
                            <textarea id="seller-cancel-reason" name="reason" maxlength="500" required></textarea>
                            <button type="submit" class="dash-btn-sm dash-btn-outline">Cancel order</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
            <?php if($order->status === 'RETURNED_TO_SELLER' && $order->restock_inspected_at === null && $order->inventory_restored_at === null): ?>
                <section class="dash-panel"><h2>Inspect returned items</h2>
                    <form method="POST" action="<?php echo e(route('seller.orders.return-inspection', $order)); ?>" data-decision="seller.orders.return-inspection" data-decision-title="Submit return inspection?" data-decision-message="Check the inspection outcome and evidence before recording this return assessment." data-decision-label="Submit inspection" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?>
                        <label>Inspection result<select name="restock" required><option value="">Choose a result</option><option value="1">All items are sellable — restock</option><option value="0">Items are damaged or unsuitable — exclude from stock</option></select></label>
                        <label>Inspection notes<textarea name="reason" required minlength="5" maxlength="500"></textarea></label><button class="dash-btn-sm" type="submit">Record inspection</button>
                    </form>
                </section>
            <?php endif; ?>
            <?php if($order->dispute?->status === 'REPLACEMENT_APPROVED' && $order->status === 'RETURNED_TO_SELLER'): ?>
                <form method="POST" action="<?php echo e(route('seller.replacements.store', $order->dispute)); ?>" data-decision="seller.replacements.store" data-decision-title="Create replacement shipment?" data-decision-message="Review the replacement items and delivery details before creating the shipment." data-decision-label="Create replacement" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?><button class="dash-btn-sm" type="submit">Create approved no-charge replacement</button></form>
            <?php endif; ?>
            <a href="<?php echo e(route('finance.index')); ?>" class="dash-btn-sm">Order financial records</a>
        </main>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/seller/orders/show.blade.php ENDPATH**/ ?>