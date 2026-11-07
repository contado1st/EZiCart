<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/app.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="admin-container u-extracted-6c0f8d518b">
        <div class="admin-header-row">
            <div>
                <h1 class="admin-page-title">Add New Product</h1>
                <p class="admin-page-desc">New listings remain hidden from buyers until Product Compliance approves them.</p>
            </div>
            <a href="<?php echo e(route('seller.products.index')); ?>" class="u-extracted-4839300e95">← Back to Inventory</a>
        </div>

        <div class="admin-table-wrapper u-extracted-a4812596aa">
            <form action="<?php echo e(route('seller.products.store')); ?>" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <?php if($errors->any()): ?><div class="product-media-validation" role="alert"><strong>Check your product details and media.</strong><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul><p>Please select new upload files again after correcting errors.</p></div><?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="product-name">Product Name *</label>
                    <input id="product-name" type="text" name="name" class="form-control" value="<?php echo e(old('name')); ?>" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="product-category">Category *</label>
                        <select id="product-category" name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php $__currentLoopData = config('web-commerce.categories'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($category); ?>" <?php if(old('category') === $category): echo 'selected'; endif; ?>><?php echo e($category); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cover-image">Cover image (optional)</label>
                        <input id="cover-image" type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>

                <?php echo $__env->make('seller.products.media-fields', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php echo $__env->make('seller.products.weight-field', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Price (₱) *</label>
                        <input type="number" step="0.01" min="0.01" name="price" class="form-control" value="<?php echo e(old('price')); ?>" required aria-label="Product base price in PHP">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Initial Stock *</label>
                        <input type="number" min="0" name="stock" class="form-control" value="<?php echo e(old('stock')); ?>" required aria-label="Product stock">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4" aria-label="Product description"><?php echo e(old('description')); ?></textarea>
                </div>

                <?php echo $__env->make('seller.products.variation-fields', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <button type="submit" class="btn-primary u-extracted-1c5da340eb">Save Product</button>
            </form>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/seller/products/create.blade.php ENDPATH**/ ?>