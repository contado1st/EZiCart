<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/seller/seller-vouchers.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="dash-wrapper">
        <!-- Sidebar -->
<!-- Main Workspace -->
        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Promotional Vouchers & Discounts</h1>
                    <p class="dash-subtitle">Create discount vouchers to promote items and drive customer orders.</p>
                </div>
            </div>

            <?php if(session('success')): ?>
                <div class="dash-alert-success"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> <?php echo e(session('success')); ?></div>
            <?php endif; ?>

            <?php if(session('error')): ?>
                <div class="dash-alert-success u-extracted-ee8231be38">
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
<?php endif; ?> <?php echo e(session('error')); ?>

                </div>
            <?php endif; ?>

            <div class="vouchers-grid">
                <!-- 1. Create Voucher Form -->
                <div class="voucher-form-card">
                    <h2 class="voucher-form-title">Create New Voucher</h2>

                    <form action="<?php echo e(route('seller.vouchers.store')); ?>" method="POST">
                        <?php echo csrf_field(); ?>

                        <div class="voucher-form-group">
                            <label class="voucher-form-label">Voucher Code *</label>
                            <input type="text" name="code" value="<?php echo e(old('code')); ?>" placeholder="e.g. SUMMER20"
                                class="voucher-input" required>
                        </div>

                        <div class="voucher-form-group">
                            <label class="voucher-form-label">Discount Type *</label>
                            <select name="type" class="voucher-select" required>
                                <option value="fixed" <?php echo e(old('type') === 'fixed' ? 'selected' : ''); ?>>Fixed Amount (₱ Off)
                                </option>
                                <option value="percent" <?php echo e(old('type') === 'percent' ? 'selected' : ''); ?>>Percentage (%
                                    Off)</option>
                            </select>
                        </div>

                        <div class="voucher-form-group">
                            <label class="voucher-form-label">Discount Value *</label>
                            <input type="number" step="0.01" name="value" value="<?php echo e(old('value')); ?>"
                                placeholder="e.g. 50.00 or 15" class="voucher-input" required>
                        </div>

                        <div class="voucher-form-group">
                            <label class="voucher-form-label">Minimum Spend (₱)</label>
                            <input type="number" step="0.01" name="min_spend" value="<?php echo e(old('min_spend', '0.00')); ?>"
                                class="voucher-input">
                        </div>

                        <div class="voucher-form-group">
                            <label class="voucher-form-label">Total Usage Limit</label>
                            <input type="number" name="usage_limit" value="<?php echo e(old('usage_limit')); ?>"
                                placeholder="Leave blank for unlimited" class="voucher-input">
                        </div>

                        <div class="voucher-form-group">
                            <label class="voucher-form-label">Expiration Date</label>
                            <input type="date" name="expires_at" value="<?php echo e(old('expires_at')); ?>" class="voucher-input">
                        </div>
                        <div class="voucher-form-group"><label class="voucher-form-label" for="voucher-recipients">Maximum unique buyers</label><input id="voucher-recipients" class="voucher-input" type="number" name="recipient_limit" min="1" max="1000000" value="<?php echo e(old('recipient_limit')); ?>" placeholder="Blank for unlimited recipients"></div>
                        <div class="voucher-form-group"><label class="voucher-form-label" for="voucher-per-buyer">Uses per buyer</label><input id="voucher-per-buyer" class="voucher-input" type="number" name="per_buyer_limit" min="1" max="1000" value="<?php echo e(old('per_buyer_limit', 1)); ?>"></div>
                        <?php if($errors->any()): ?><p role="alert"><?php echo e($errors->first()); ?></p><?php endif; ?>

                        <button type="submit" class="dash-btn-primary u-extracted-92762f464d">
                            <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> Create Voucher
                        </button>
                    </form>
                </div>

                <!-- 2. Active Vouchers Table -->
                <div class="dash-panel">
                    <h2 class="courier-section-title">My Store Vouchers (<?php echo e($vouchers->total()); ?>)</h2>

                    <div class="dash-table-wrapper u-extracted-dab43fb936">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Discount</th>
                                    <th>Min. Spend</th>
                                    <th>Redeemed</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__empty_1 = true; $__currentLoopData = $vouchers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $voucher): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr>
                                        <td><span class="voucher-code-tag"><?php echo e($voucher->code); ?></span></td>
                                        <td>
                                            <strong>
                                                <?php echo e($voucher->type === 'percent' ? $voucher->value . '%' : '₱' . number_format($voucher->value, 2)); ?>

                                            </strong>
                                        </td>
                                        <td>₱<?php echo e(number_format($voucher->min_spend, 2)); ?></td>
                                        <td>
                                            <?php echo e($voucher->used_count); ?> / <?php echo e($voucher->usage_limit ?? '∞'); ?>

                                            <p class="web-muted">Unique buyers: <?php echo e($voucher->claimed_count); ?> / <?php echo e($voucher->recipient_limit ?? 'Unlimited'); ?><br>Remaining: <?php echo e($voucher->recipient_limit !== null ? max(0, $voucher->recipient_limit - $voucher->claimed_count) : 'Unlimited'); ?></p>
                                        </td>
                                        <td>
                                            <span
                                                class="voucher-status-pill <?php echo e($voucher->is_active ? 'voucher-active' : 'voucher-inactive'); ?>">
                                                <?php echo e($voucher->is_active ? 'Active' : 'Disabled'); ?>

                                            </span>
                                        </td>
                                        <td>
                                            <div class="voucher-actions">
                                                <form action="<?php echo e(route('seller.vouchers.toggle', $voucher->id)); ?>"
                                                    method="POST" data-decision="seller.vouchers.toggle" data-decision-title="Change voucher availability?" data-decision-message="This changes whether buyers can use the selected voucher." data-decision-label="Change availability" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                    <?php echo csrf_field(); ?>
                                                    <?php echo method_field('PATCH'); ?>
                                                    <button type="submit" class="dash-btn-sm u-extracted-e013d740bb">
                                                        <?php echo e($voucher->is_active ? 'Disable' : 'Enable'); ?>

                                                    </button>
                                                </form>
                                                <form action="<?php echo e(route('seller.vouchers.destroy', $voucher->id)); ?>"
                                                    method="POST" data-decision="seller.vouchers.destroy" data-decision-title="Delete this voucher?" data-decision-message="This permanently deletes the selected voucher." data-decision-label="Delete voucher" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                    <?php echo csrf_field(); ?>
                                                    <?php echo method_field('DELETE'); ?>
                                                    <button type="submit"
                                                        class="dash-btn-sm dash-btn-danger">Delete</button>
                                                </form>
                                            </div>
                                            <details class="web-section"><summary>Edit recipient limits</summary><form class="web-form" method="POST" action="<?php echo e(route('seller.vouchers.limits', $voucher)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><label for="limit-<?php echo e($voucher->id); ?>">Maximum buyers<input id="limit-<?php echo e($voucher->id); ?>" type="number" name="recipient_limit" min="<?php echo e(max(1, $voucher->claimed_count)); ?>" max="1000000" value="<?php echo e($voucher->recipient_limit); ?>"></label><label for="buyer-limit-<?php echo e($voucher->id); ?>">Uses per buyer<input id="buyer-limit-<?php echo e($voucher->id); ?>" type="number" name="per_buyer_limit" min="1" max="1000" value="<?php echo e($voucher->per_buyer_limit); ?>"></label><label for="expiry-<?php echo e($voucher->id); ?>">Expires<input id="expiry-<?php echo e($voucher->id); ?>" type="datetime-local" name="expires_at" value="<?php echo e($voucher->expires_at?->format('Y-m-d\TH:i')); ?>"></label><button class="dash-btn-sm" type="submit">Save limits</button></form></details>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="6" class="dash-table-empty">No promotional vouchers created yet.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="u-extracted-dab43fb936">
                        <?php echo e($vouchers->links()); ?>

                    </div>
                </div>
            </div>
        </main>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/seller/vouchers/index.blade.php ENDPATH**/ ?>