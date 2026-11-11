<?php $__env->startSection('title', 'For you | EZiCart'); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('components.feature-feedback', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="feature-page">
    <header><div><h1>For you</h1><p>Products from categories you browse and purchase. New shoppers see the latest available listings.</p></div><a class="feature-button" href="<?php echo e(route('search.index')); ?>">Search all products</a></header>
    <form method="POST" action="<?php echo e(route('buyer.for-you.reset')); ?>" data-decision="buyer.for-you.reset" data-decision-title="Clear browsing history?" data-decision-message="This clears the browsing history used for your recommendations." data-decision-label="Clear history" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="feature-button" type="submit">Clear browsing history</button></form>
    <div class="feature-grid"><?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <a class="feature-product" href="<?php echo e(route('product.show', $product)); ?>"><?php if($product->image_path): ?><img src="<?php echo e(asset('storage/'.$product->image_path)); ?>" alt="<?php echo e($product->name); ?>" loading="lazy" width="300" height="300"><?php endif; ?><strong><?php echo e($product->name); ?></strong><small><?php echo e($product->seller?->business_name); ?></small><span class="feature-product-price">₱<?php echo e(number_format($product->price, 2)); ?></span><small><?php echo e($product->catalog_review_count ? number_format($product->catalog_rating, 1).' / 5' : 'No ratings yet'); ?></small></a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No products are currently available.</p><?php endif; ?></div>
    <?php echo e($products->links()); ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/buyer/for-you.blade.php ENDPATH**/ ?>