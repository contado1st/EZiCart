<?php $__env->startPush('styles'); ?> <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?> <?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<main class="container workflow-admin-page">
    <header class="dash-header"><div><h1 class="dash-title">Financial records</h1><p class="dash-subtitle">COD collection, verified cash remittances, refunds, and seller settlements.</p></div></header>
    <?php if(session('success')): ?><p role="status"><?php echo e(session('success')); ?></p><?php endif; ?>
    <?php if($errors->any()): ?><div role="alert"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><?php echo e($message); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
    <section class="dash-panel"><h2>Order balances and COD</h2>
        <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Order</th><th>COD due</th><th>Collected</th><th>Remittance</th><th>Actions</th></tr></thead><tbody>
        <?php $__empty_1 = true; $__currentLoopData = $finances; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $finance): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr>
            <td><?php echo e($finance->order->order_number); ?></td><td>₱<?php echo e(number_format($finance->due_cents / 100, 2)); ?></td>
            <td><?php echo e($finance->collected_cents === null ? 'Not recorded' : '₱'.number_format($finance->collected_cents / 100, 2)); ?></td>
            <td><?php echo e(str_replace('_', ' ', $finance->remittance_status)); ?><?php if($finance->remitted_cents !== null): ?><p>Submitted: ₱<?php echo e(number_format($finance->remitted_cents / 100, 2)); ?></p><?php endif; ?></td>
            <td>
                <?php if($finance->platform_subsidy_cents > 0): ?>
                    <p>Platform promotion: ₱<?php echo e(number_format($finance->platform_subsidy_cents / 100, 2)); ?> — <?php echo e($finance->funded_cents >= $finance->platform_subsidy_cents ? 'funded' : 'funding pending'); ?></p>
                    <?php if($actor->isCommerceAdmin() && $finance->funded_cents < $finance->platform_subsidy_cents): ?>
                        <form method="POST" enctype="multipart/form-data" action="<?php echo e(route('finance.subsidy', $finance->order)); ?>" data-decision="finance.subsidy" data-decision-title="Record subsidy funding?" data-decision-message="Check the amount and evidence before recording this funding entry." data-decision-label="Record funding" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?>
                            <?php echo $__env->make('components.finance-evidence-fields', ['prefix' => 'subsidy-'.$finance->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><button class="dash-btn-sm" type="submit">Record platform promotion funding</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if($finance->receipt_path && ($actor->isCommerceAdmin() || $actor->isLogisticsAdmin() || $actor->role === 'courier')): ?><a href="<?php echo e(route('finance.evidence', ['remittance', $finance->id])); ?>">Cash receipt</a><?php endif; ?>
                <?php if($actor->role === 'courier' && in_array($finance->remittance_status, ['COLLECTED', 'DISCREPANCY'], true)): ?>
                    <form method="POST" enctype="multipart/form-data" action="<?php echo e(route('finance.remittance', $finance->order)); ?>" data-decision="finance.remittance" data-decision-title="Submit COD remittance?" data-decision-message="Check the recorded amount and supporting evidence before submitting the collected cash for verification." data-decision-label="Submit remittance" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?>
                        <label for="remitted-<?php echo e($finance->id); ?>">Amount handed over</label><input id="remitted-<?php echo e($finance->id); ?>" name="amount" type="number" min="0" step="0.01" required>
                        <?php echo $__env->make('components.finance-evidence-fields', ['prefix' => 'remittance-'.$finance->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <button class="dash-btn-sm" type="submit">Submit cash remittance</button>
                    </form>
                <?php endif; ?>
                <?php if($actor->isLogisticsAdmin() && $finance->remittance_status === 'SUBMITTED'): ?>
                    <form method="POST" action="<?php echo e(route('finance.remittance.verify', $finance->order)); ?>" data-decision="finance.remittance.verify" data-decision-title="Verify COD remittance?" data-decision-message="Verify the amount and evidence against the cash received before confirming." data-decision-label="Verify remittance" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?><label>Receipt verification notes<textarea name="reason" required minlength="5" maxlength="500"></textarea></label><button class="dash-btn-sm" type="submit">Verify cash received</button></form>
                <?php endif; ?>
                <?php if($actor->role === 'seller' && $finance->order->status === 'COMPLETED' && $finance->remittance_status === 'VERIFIED'): ?>
                    <p>Seller payable: ₱<?php echo e(number_format($finance->seller_payable_cents / 100, 2)); ?> after commission.</p>
                    <form method="POST" action="<?php echo e(route('finance.payout.request', $finance->order)); ?>" data-decision="finance.payout.request" data-decision-title="Request seller payout?" data-decision-message="Review the order and payout details before submitting the request." data-decision-label="Request payout" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?><button class="dash-btn-sm" type="submit">Request seller payout</button></form>
                <?php endif; ?>
            </td>
        </tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5">No financial records available.</td></tr><?php endif; ?>
        </tbody></table></div><?php echo e($finances->links()); ?>

    </section>
    <section class="dash-panel"><h2>Refunds</h2><p>Authorization records a decision. Execution records an external refund supported by a transaction reference and proof.</p>
        <?php $__empty_1 = true; $__currentLoopData = $refunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $refund): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><article><h3><?php echo e($refund->order->order_number); ?> — ₱<?php echo e(number_format($refund->amount_cents / 100, 2)); ?></h3><p><?php echo e($refund->status); ?> <?php echo e($refund->reference); ?></p>
            <?php if($refund->evidence_path && ($actor->isCommerceAdmin() || $actor->role === 'buyer')): ?><a href="<?php echo e(route('finance.evidence', ['refund', $refund->id])); ?>">Refund proof</a><?php endif; ?>
            <?php if($actor->isCommerceAdmin() && $refund->status === 'AUTHORIZED'): ?><form method="POST" enctype="multipart/form-data" action="<?php echo e(route('finance.refund.execute', $refund)); ?>" data-decision="finance.refund.execute" data-decision-title="Record refund execution?" data-decision-message="Verify the recipient, amount and proof of payment before recording this refund." data-decision-label="Record refund" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?>
                <?php echo $__env->make('components.finance-evidence-fields', ['prefix' => 'refund-'.$refund->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><button class="dash-btn-sm" type="submit">Record external refund</button>
            </form><?php endif; ?>
        </article><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No refund records.</p><?php endif; ?><?php echo e($refunds->links()); ?>

    </section>
    <section class="dash-panel"><h2>Seller payouts</h2>
        <?php $__empty_1 = true; $__currentLoopData = $payouts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payout): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><article><h3><?php echo e($payout->order->order_number); ?> — ₱<?php echo e(number_format($payout->amount_cents / 100, 2)); ?></h3><p><?php echo e($payout->status); ?> <?php echo e($payout->reference); ?></p>
            <?php if($payout->evidence_path): ?><a href="<?php echo e(route('finance.evidence', ['payout', $payout->id])); ?>">Settlement proof</a><?php endif; ?>
            <?php if($actor->isCommerceAdmin() && $payout->status === 'REQUESTED'): ?><form method="POST" action="<?php echo e(route('finance.payout.approve', $payout)); ?>" data-decision="finance.payout.approve" data-decision-title="Approve seller payout?" data-decision-message="Review the payout eligibility and amount before approving." data-decision-label="Approve payout" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?><label>Approval reason<textarea name="reason" required minlength="5" maxlength="500"></textarea></label><button class="dash-btn-sm" type="submit">Approve payout</button></form><?php endif; ?>
            <?php if($actor->isCommerceAdmin() && $payout->status === 'APPROVED'): ?><form method="POST" enctype="multipart/form-data" action="<?php echo e(route('finance.payout.settle', $payout)); ?>" data-decision="finance.payout.settle" data-decision-title="Record payout settlement?" data-decision-message="Verify the seller payment and supporting evidence before recording settlement." data-decision-label="Record settlement" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?>
                <?php echo $__env->make('components.finance-evidence-fields', ['prefix' => 'payout-'.$payout->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><button class="dash-btn-sm" type="submit">Record external payout settlement</button>
            </form><?php endif; ?>
        </article><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No payout records.</p><?php endif; ?><?php echo e($payouts->links()); ?>

    </section>
</main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/finance/index.blade.php ENDPATH**/ ?>