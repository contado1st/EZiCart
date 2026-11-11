<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/app.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="admin-container u-extracted-6c0f8d518b">
        <div class="admin-header-row">
            <div>
                <h1 class="admin-page-title">Edit Product</h1>
                <p class="admin-page-desc">Modify product details, pricing, and stock status.</p>
            </div>
            <a href="<?php echo e(route('seller.products.index')); ?>" class="u-extracted-4839300e95">← Back to Inventory</a>
        </div>

        <div class="admin-table-wrapper u-extracted-a4812596aa">
            <form action="<?php echo e(route('seller.products.update', $product->id)); ?>" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>
                <?php if($errors->any()): ?><div class="product-media-validation" role="alert"><strong>Check your product details and media.</strong><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul><p>Please select new upload files again after correcting errors.</p></div><?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="name" class="form-control" value="<?php echo e(old('name', $product->name)); ?>"
                        required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-control" required>
                            <?php $__currentLoopData = array_unique([...config('web-commerce.categories'), $product->category_label]); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($cat); ?>" <?php if(app(\App\Services\ProductCategoryService::class)->label(old('category', $product->category)) === $cat): echo 'selected'; endif; ?>>
                                    <?php echo e($cat); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cover-image">Replace cover image</label>
                        <input id="cover-image" type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <?php if($product->image_path): ?>
                            <span class="text-muted-small u-extracted-c97152cfd7">Current:
                                <?php echo e(basename($product->image_path)); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php echo $__env->make('seller.products.media-fields', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php echo $__env->make('seller.products.weight-field', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Price (₱) *</label>
                        <input type="number" step="0.01" name="price" class="form-control"
                            value="<?php echo e(old('price', $product->price)); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Current Stock Units *</label>
                        <input type="number" name="stock" class="form-control"
                            value="<?php echo e(old('stock', $product->stock)); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?php echo e(old('description', $product->description)); ?></textarea>
                </div>

                <div class="form-group u-extracted-0dc8cc6234">
                    <label class="u-extracted-23a9b0e3fb">
                        <input type="checkbox" name="is_archived" value="1"
                            <?php echo e($product->is_archived ? 'checked' : ''); ?>>
                        <span>Archive Product (hide from active customer catalog)</span>
                    </label>
                </div>

                <?php echo $__env->make('seller.products.variation-fields', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <button type="submit" class="btn-primary u-extracted-3b6349e4b7">Update Product</button>
            </form>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/seller/products/edit.blade.php ENDPATH**/ ?>