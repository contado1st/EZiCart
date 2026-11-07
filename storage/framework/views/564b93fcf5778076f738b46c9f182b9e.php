<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="dash-wrapper">
        <!-- Sidebar Navigation -->
<!-- Main Workspace -->
        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Inventory Management</h1>
                    <p class="dash-subtitle">Manage products, update stock levels, and monitor prices.</p>
                </div>
                <a href="<?php echo e(route('seller.products.create')); ?>" class="dash-btn-primary">+ Add New Product</a>
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

            <div class="dash-table-wrapper">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th class="u-extracted-13cbe03b9a">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td>
                                    <div class="u-extracted-2f29807bdf">
                                        <?php if($product->image_path): ?>
                                            <img src="<?php echo e(asset('storage/' . $product->image_path)); ?>"
                                                alt="<?php echo e($product->name); ?>" class="u-extracted-aac99b4b41">
                                        <?php else: ?>
                                            <div class="u-extracted-c70ac6556c">
                                                <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="u-extracted-8fa6b099d3">
                                                <?php echo e($product->name); ?></div>
                                            <div class="u-extracted-cf0c302441">
                                                SKU-<?php echo e(str_pad($product->id, 5, '0', STR_PAD_LEFT)); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="dash-badge dash-badge-category"><?php echo e($product->category_label); ?></span></td>
                                <td class="u-extracted-6b1af052d3">₱<?php echo e(number_format($product->price, 2)); ?></td>
                                <td>
                                    <span
                                        class="seller-stock <?php echo e($product->stock < 10 ? 'seller-stock--low' : 'seller-stock--ok'); ?>">
                                        <?php echo e($product->stock); ?> units
                                    </span>
                                </td>
                                <td>
                                    <?php if($product->is_archived): ?>
                                        <span class="dash-badge dash-badge-archived">Archived</span>
                                    <?php elseif($product->compliance_status !== 'approved'): ?>
                                        <span
                                            class="dash-badge dash-badge-archived"><?php echo e(str_replace('_', ' ', ucfirst($product->compliance_status))); ?></span>
                                        <?php if($product->compliance_note): ?>
                                            <div class="text-muted-small"><?php echo e($product->compliance_note); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="dash-badge dash-badge-active">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td class="u-extracted-13cbe03b9a">
                                    <div class="u-extracted-af0aae5706">
                                        <a href="<?php echo e(route('seller.products.edit', $product->id)); ?>"
                                            class="dash-btn-sm dash-btn-outline">Edit</a>
                                        <form action="<?php echo e(route('seller.products.destroy', $product->id)); ?>" method="POST" data-decision="seller.products.destroy" data-decision-title="Remove this product?" data-decision-message="This removes the product from your inventory. Review the selected product before continuing." data-decision-label="Remove product" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button type="submit" class="dash-btn-sm dash-btn-danger">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="6" class="u-extracted-a3521f1d48">
                                    No products found. Start adding inventory to your store!
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/seller/products/index.blade.php ENDPATH**/ ?>