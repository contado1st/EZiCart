<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waybill - <?php echo e($order->order_number); ?></title>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/seller/waybill.css'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/interaction-feedback.css')); ?>?v=<?php echo e(filemtime(public_path('css/interaction-feedback.css'))); ?>">
    <?php echo app('Illuminate\Foundation\Vite')('resources/js/app.js'); ?>
</head>

<body class="waybill-body">

    <div class="waybill-wrapper">
        <!-- Screen Actions Toolbar -->
        <div class="waybill-actions-bar">
            <a href="<?php echo e(route('seller.orders.index')); ?>" class="waybill-back-link">
                ← Back to Order Management
            </a>
            <button type="button" onclick="window.print()" class="waybill-print-btn">
                Print Waybill / Shipping Slip
            </button>
        </div>

        <?php if(in_array($order->status, ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'], true) &&
                $order->pickup_claimed_at === null &&
                $order->seller_handover_at === null): ?>
            <section class="waybill-actions-bar" aria-label="Replace parcel label">
                <p>Current parcel label version: <?php echo e($order->parcel_code_version); ?>. Replacing the code invalidates any
                    previously printed label.</p>
                <form method="POST" action="<?php echo e(route('seller.orders.waybill.reprint', $order)); ?>" data-decision="seller.orders.waybill.reprint" data-decision-title="Replace this parcel label?" data-decision-message="Replacing the label changes the parcel identification used for scanning. Use the new printed label for future handoffs." data-decision-label="Replace label" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    <?php echo csrf_field(); ?>
                    <label for="reprint-reason">Reason for replacement</label>
                    <input id="reprint-reason" name="reason" maxlength="500" required>
                    <button type="submit" class="waybill-print-btn">Issue replacement label</button>
                </form>
            </section>
        <?php endif; ?>

        <?php if(session('success')): ?>
            <p role="status"><?php echo e(session('success')); ?></p>
        <?php endif; ?>

        <!-- Official Printable Waybill -->
        <div class="waybill-sheet">
            <div class="waybill-brand-row">
                <div>
                    <h1 class="waybill-title">EZiCart Express</h1>
                    <div class="u-extracted-88437780af">Standard Courier Parcel
                        Dispatch</div>
                </div>
                <div class="waybill-tracking-block">
                    <div class="waybill-tracking-number"><?php echo e($order->order_number); ?></div>
                    <div class="waybill-parcel-code">Parcel: <?php echo e($order->parcel_code); ?></div>
                    <div class="waybill-qr"><?php echo $parcelQr; ?></div>
                </div>
            </div>

            <!-- Sender / Recipient Address Information -->
            <div class="waybill-parties-grid">
                <div class="waybill-party-cell">
                    <div class="waybill-section-tag">Sender (Merchant)</div>
                    <div class="waybill-party-name"><?php echo e($order->seller->business_name ?? 'EZiCart Seller'); ?></div>
                    <div class="waybill-party-text"><?php echo e($order->seller->contact_no); ?></div>
                    <div class="waybill-party-text"><?php echo e(trim(($order->seller->house_number ? $order->seller->house_number.' ' : '').$order->seller->street_address)); ?>,
                        <?php echo e($order->seller->barangay); ?>

                    </div>
                    <div class="waybill-party-text"><?php echo e($order->seller->municipality); ?>, <?php echo e($order->seller->province); ?>

                    </div>
                </div>

                <div class="waybill-party-cell">
                    <div class="waybill-section-tag">Recipient (Buyer)</div>
                    <div class="waybill-party-name"><?php echo e($order->recipient_name); ?></div>
                    <div class="waybill-party-text"><?php echo e($order->recipient_contact); ?></div>
                    <div class="waybill-party-text"><?php echo e(trim(($order->house_number ? $order->house_number.' ' : '').$order->street_address)); ?>, <?php echo e($order->barangay); ?></div>
                    <div class="waybill-party-text"><?php echo e($order->municipality); ?>, <?php echo e($order->province); ?></div>
                </div>
            </div>

            <!-- Parcel Items Detail -->
            <table class="waybill-items-table">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th class="u-extracted-188b5f39d0">Qty</th>
                        <th class="u-extracted-f337f3c9a6">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><?php echo e($item->product_name); ?></td>
                            <td class="u-extracted-72a68ac344"><?php echo e($item->quantity); ?></td>
                            <td class="u-extracted-13cbe03b9a">₱<?php echo e(number_format($item->item_total, 2)); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>

            <!-- Footnote & Collection Protocol -->
            <div class="waybill-footer-grid">
                <div class="waybill-instructions-cell">
                    <div class="waybill-section-tag">Actual packed parcel weight</div>
                    <div class="waybill-party-text"><?php echo e(\App\Services\ParcelLoadService::kg($order->parcel_weight_grams)); ?></div>
                    <div class="waybill-section-tag">Delivery Instructions & Remarks</div>
                    <div>
                        <?php echo e($order->notes ?? 'No special courier notes provided. Deliver during standard dispatch hours.'); ?>

                    </div>
                </div>

                <div class="waybill-amount-cell">
                    <div class="waybill-amount-label">Payment Method: <?php echo e($order->payment_method); ?></div>
                    <div class="waybill-total-price">
                        ₱<?php echo e(number_format($order->total_amount, 2)); ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

</body>

</html>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/seller/orders/waybill.blade.php ENDPATH**/ ?>