<?php $__env->startSection('title', ($searchTerm !== '' ? 'Search: '.$searchTerm : 'Browse products').' — EZiCart'); ?>
<?php $__env->startSection('meta-description', 'Find products and shops on EZiCart. Filter by category, price, customer rating and seller location.'); ?>
<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/catalog-search.css')); ?>?v=<?php echo e(filemtime(public_path('css/catalog-search.css'))); ?>">
    <script src="<?php echo e(asset('js/catalog-search.js')); ?>?v=<?php echo e(filemtime(public_path('js/catalog-search.js'))); ?>" defer></script>
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<div class="catalog-search">
    <nav class="catalog-breadcrumb" aria-label="Breadcrumb"><a href="<?php echo e(route('home')); ?>">Home</a><span aria-hidden="true">/</span><span>Search results</span></nav>
    <header class="catalog-heading">
        <div><h1><?php if($searchTerm !== ''): ?>Results for <span>“<?php echo e($searchTerm); ?>”</span><?php else: ?> Discover products <?php endif; ?></h1><p><?php echo e(number_format($products->total())); ?> <?php echo e($products->total() === 1 ? 'product' : 'products'); ?> found<?php echo e($searchTerm !== '' ? ' across the marketplace' : ' from EZiCart sellers'); ?>.</p></div>
        <?php if(!auth()->check() || auth()->user()->role === 'buyer'): ?>
            <a href="<?php echo e(route('buyer.dashboard')); ?>" class="catalog-orders-link"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> My purchases</a>
        <?php endif; ?>
    </header>
    <?php if($errors->any()): ?><div class="catalog-error" role="alert"><strong>Check your search filters.</strong><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($message); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div><?php endif; ?>
    <div class="catalog-layout">
        <aside class="catalog-filter-sidebar" aria-label="Product filters">
            <details class="catalog-filters" data-catalog-filters open>
                <summary><span><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?> Search filters</span><?php if(count($activeFilters)): ?><span class="catalog-filter-count"><?php echo e(count($activeFilters)); ?></span><?php endif; ?></summary>
                <form action="<?php echo e(route('search.index')); ?>" method="GET" class="catalog-filter-form">
                    <input type="hidden" name="search" value="<?php echo e($searchTerm); ?>">
                    <input type="hidden" name="sort" value="<?php echo e($filters['sort']); ?>">
                    <fieldset><legend>Category</legend><div class="catalog-category-options">
                        <label><input type="radio" name="category" value="" <?php if(empty($filters['category'])): echo 'checked'; endif; ?>><span>All categories</span></label>
                        <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <label><input type="radio" name="category" value="<?php echo e($category); ?>" <?php if(($filters['category'] ?? '') === $category): echo 'checked'; endif; ?>><span><?php echo e($category); ?></span></label>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div></fieldset>
                    <fieldset><legend>Price range</legend><div class="catalog-price-inputs">
                        <div><label for="catalog-min-price">Min (₱)</label><input id="catalog-min-price" type="number" name="min_price" value="<?php echo e($filters['min_price'] ?? ''); ?>" min="0" max="9999999999.99" step="0.01" placeholder="0"></div>
                        <span aria-hidden="true">–</span>
                        <div><label for="catalog-max-price">Max (₱)</label><input id="catalog-max-price" type="number" name="max_price" value="<?php echo e($filters['max_price'] ?? ''); ?>" min="0" max="9999999999.99" step="0.01" placeholder="Any"></div>
                    </div></fieldset>
                    <div class="catalog-filter-field"><label for="catalog-rating">Customer rating</label><select id="catalog-rating" name="rating"><option value="">All ratings</option><?php $__currentLoopData = [5, 4, 3, 2, 1]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rating): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($rating); ?>" <?php if((int) ($filters['rating'] ?? 0) === $rating): echo 'selected'; endif; ?>><?php echo e($rating); ?> stars<?php echo e($rating < 5 ? ' & up' : ''); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
                    <div class="catalog-filter-field"><label for="catalog-province">Ships from</label><select id="catalog-province" name="province"><option value="">All locations</option><?php $__currentLoopData = $provinces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $province): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($province); ?>" <?php if(($filters['province'] ?? '') === $province): echo 'selected'; endif; ?>><?php echo e($province); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
                    <button class="catalog-apply-button" type="submit">Apply filters</button>
                    <a class="catalog-clear-link" href="<?php echo e(route('search.index', ['search' => $searchTerm, 'sort' => $filters['sort']])); ?>">Clear all filters</a>
                </form>
            </details>
        </aside>
        <section class="catalog-results" aria-label="Search results">
            <div class="catalog-sortbar"><span>Sort by</span><nav aria-label="Sort products"><?php $__currentLoopData = $sortOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sortValue => $sortLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('search.index', array_merge(\Illuminate\Support\Arr::except($filters, 'page'), ['sort' => $sortValue]))); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['catalog-sort-option', 'is-active' => $filters['sort'] === $sortValue]); ?>" <?php if($filters['sort'] === $sortValue): ?> aria-current="true" <?php endif; ?>><?php echo e($sortLabel); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></nav><span class="catalog-page-number"><?php echo e($products->currentPage()); ?> / <?php echo e(max(1, $products->lastPage())); ?></span></div>
            <?php if($activeFilters): ?>
                <div class="catalog-active-filters" aria-label="Applied filters"><span>Selected:</span><?php $__currentLoopData = $activeFilters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('search.index', $filter['query'])); ?>" aria-label="Remove <?php echo e($filter['label']); ?>"><?php echo e($filter['label']); ?><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'close']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'close']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
            <?php endif; ?>
            <?php if($products->isNotEmpty()): ?>
                <p class="catalog-result-range">Showing <?php echo e($products->firstItem()); ?>–<?php echo e($products->lastItem()); ?> of <?php echo e(number_format($products->total())); ?> products</p>
                <div class="catalog-product-grid">
                    <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <article class="catalog-product"><a class="catalog-product-main" href="<?php echo e(route('product.show', $product)); ?>">
                            <div class="catalog-product-image"><?php if($product->image_path): ?><img src="<?php echo e(asset('storage/'.$product->image_path)); ?>" alt="<?php echo e($product->name); ?>" width="400" height="400" loading="lazy" decoding="async"><?php else: ?><span class="catalog-image-placeholder"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
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
<?php endif; ?><span>Product image unavailable</span></span><?php endif; ?></div>
                            <div class="catalog-product-content">
                                <span class="catalog-product-category"><?php echo e($product->category_label); ?></span>
                                <h2><?php echo e($product->name); ?></h2>
                                <strong class="catalog-product-price">₱<?php echo e(number_format($product->price, 2)); ?></strong>
                                <div class="catalog-product-rating"><?php if($product->catalog_review_count > 0): ?><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'star']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'star']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><span><?php echo e(number_format($product->catalog_rating, 1)); ?></span><small>(<?php echo e(number_format($product->catalog_review_count)); ?>)</small><?php else: ?><span>No ratings yet</span><?php endif; ?></div>
                                <div class="catalog-product-store"><?php echo e($product->seller?->business_name ?: $product->seller?->first_name ?: 'EZiCart seller'); ?></div>
                                <?php if($product->seller?->province): ?><div class="catalog-product-location"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'location']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'location']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?><span><?php echo e($product->seller->province); ?></span></div><?php endif; ?>
                            </div>
                        </a><?php if($product->seller?->role === 'seller' && $product->seller?->status === 'approved'): ?><a class="catalog-shop-link" href="<?php echo e(route('shops.show', $product->seller)); ?>">Visit shop <?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'arrowback']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'arrowback']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></a><?php endif; ?></article>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <div class="catalog-pagination"><?php echo e($products->links()); ?></div>
            <?php else: ?>
                <section class="catalog-empty"><span class="catalog-empty-icon"><?php if (isset($component)) { $__componentOriginal1bb289350be1bbfe945d5817dffb1017 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1bb289350be1bbfe945d5817dffb1017 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shop-icon','data' => ['name' => 'search']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shop-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $attributes = $__attributesOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__attributesOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1bb289350be1bbfe945d5817dffb1017)): ?>
<?php $component = $__componentOriginal1bb289350be1bbfe945d5817dffb1017; ?>
<?php unset($__componentOriginal1bb289350be1bbfe945d5817dffb1017); ?>
<?php endif; ?></span><h2>No matching products</h2><p><?php if($activeFilters): ?>Try a wider price range or remove a filter to see more results.<?php elseif($searchTerm !== ''): ?>Try a different keyword, a shorter product name, or a shop name.<?php else: ?> Available products will appear here when sellers add listings.<?php endif; ?></p><a href="<?php echo e(route('search.index', $activeFilters ? ['search' => $searchTerm] : [])); ?>"><?php echo e($activeFilters ? 'Clear filters' : 'Browse all products'); ?></a></section>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/search/index.blade.php ENDPATH**/ ?>