<?php $__env->startSection('title', 'EZiCart — Shop products from marketplace sellers'); ?>
<?php $__env->startSection('meta-description', 'Browse approved, in-stock products from EZiCart sellers. Compare details, prices, and seller information.'); ?>

<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/storefront/landing.css'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/buyer-storefront.css')); ?>?v=<?php echo e(filemtime(public_path('css/buyer-storefront.css'))); ?>">
<?php $__env->stopPush(); ?>

<?php
    $featuredProducts = $products->take(4);
    $featuredProduct = $products->first();
    $hasFilters = request()->filled('category') || request()->filled('search');
?>

<?php $__env->startSection('content'); ?>
    <div class="ez-editorial buyer-storefront" id="top">
        <nav class="editorial-nav buyer-store-nav" aria-label="Marketplace sections">
            <a href="#buyer-categories">Categories</a>
            <a href="#buyer-new-arrivals">New arrivals</a>
            <a href="#browse-products">Discover products</a>
            <?php if(auth()->guard()->check()): ?>
                <?php if(auth()->user()->role === 'buyer'): ?>
                    <a href="<?php echo e(route('buyer.dashboard')); ?>">My orders</a>
                    <a href="<?php echo e(route('account.profile.edit')); ?>">My account</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?php echo e(route('register.seller')); ?>">Apply to sell</a>
            <?php endif; ?>
        </nav>

        <main class="buyer-store-main">
            <section class="buyer-promo" id="discover" aria-labelledby="buyer-promo-title">
                <img class="buyer-promo-image" src="<?php echo e(asset('images/hero_asset.jpg')); ?>" alt="" fetchpriority="high" width="1600" height="900">
                <div class="buyer-promo-copy">
                    <p class="buyer-promo-kicker">The EZiCart marketplace</p>
                    <?php if(auth()->guard()->check()): ?>
                        <?php if(auth()->user()->role === 'buyer'): ?>
                            <h1 id="buyer-promo-title">Good finds, <?php echo e(auth()->user()->first_name); ?>.</h1>
                        <?php else: ?>
                            <h1 id="buyer-promo-title">Good finds start here.</h1>
                        <?php endif; ?>
                    <?php else: ?>
                        <h1 id="buyer-promo-title">Good finds start here.</h1>
                    <?php endif; ?>
                    <p>Browse approved, in-stock products from EZiCart sellers. Compare details and prices before you choose.</p>
                    <a class="buyer-promo-action" href="#browse-products">Explore products <span aria-hidden="true">↘</span></a>
                </div>
                <?php if($featuredProduct): ?>
                    <a class="buyer-promo-feature" href="<?php echo e(route('product.show', $featuredProduct->id)); ?>">
                        <?php if($featuredProduct->image_path): ?>
                            <img src="<?php echo e(asset('storage/' . $featuredProduct->image_path)); ?>" alt="<?php echo e($featuredProduct->name); ?>" loading="lazy">
                        <?php endif; ?>
                        <span><small>Recently listed</small><strong><?php echo e(\Illuminate\Support\Str::limit($featuredProduct->name, 48)); ?></strong><b>₱<?php echo e(number_format($featuredProduct->price, 2)); ?></b></span>
                    </a>
                <?php endif; ?>
            </section>

            <nav class="buyer-shortcuts" aria-label="Quick links">
                <a href="#buyer-categories"><span class="buyer-shortcut-mark">01</span><span>Shop by category</span></a>
                <a href="#buyer-new-arrivals"><span class="buyer-shortcut-mark">02</span><span>Recently added</span></a>
                <?php if(auth()->guard()->check()): ?>
                    <?php if(auth()->user()->role === 'buyer'): ?>
                        <a href="<?php echo e(route('buyer.dashboard')); ?>"><span class="buyer-shortcut-mark">03</span><span>Track an order</span></a>
                        <a href="<?php echo e(route('cart.index')); ?>"><span class="buyer-shortcut-mark">04</span><span>View your cart</span></a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?php echo e(route('register')); ?>"><span class="buyer-shortcut-mark">03</span><span>Create an account</span></a>
                    <a href="<?php echo e(route('cart.index')); ?>"><span class="buyer-shortcut-mark">04</span><span>View your cart</span></a>
                <?php endif; ?>
            </nav>

            <section class="buyer-shelf buyer-category-shelf" id="buyer-categories" aria-labelledby="buyer-category-title">
                <header class="buyer-shelf-heading">
                    <div><p class="buyer-section-label">Browse the marketplace</p><h2 id="buyer-category-title">Shop by category</h2></div>
                    <a class="buyer-see-all" href="#browse-products">See all products <span aria-hidden="true">↘</span></a>
                </header>
                <div class="buyer-category-grid">
                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a class="buyer-category-tile" href="<?php echo e(route('home', ['category' => $category['name']])); ?>#browse-products">
                            <span class="buyer-category-number"><?php echo e(str_pad($loop->iteration, 2, '0', STR_PAD_LEFT)); ?></span>
                            <strong><?php echo e($category['name']); ?></strong>
                            <span class="buyer-category-link">Explore category <span aria-hidden="true">↗</span></span>
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </section>

            <?php if($featuredProducts->isNotEmpty()): ?>
                <section class="buyer-shelf buyer-arrival-shelf" id="buyer-new-arrivals" aria-labelledby="buyer-arrival-title">
                    <header class="buyer-shelf-heading">
                        <div><p class="buyer-section-label">Fresh from EZiCart sellers</p><h2 id="buyer-arrival-title">Recently added</h2></div>
                        <a class="buyer-see-all" href="#browse-products">Browse the full collection <span aria-hidden="true">↘</span></a>
                    </header>
                    <div class="buyer-arrival-grid">
                        <?php $__currentLoopData = $featuredProducts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <a class="buyer-arrival-card" href="<?php echo e(route('product.show', $product->id)); ?>">
                                <span class="buyer-arrival-image">
                                    <?php if($product->image_path): ?>
                                        <img src="<?php echo e(asset('storage/' . $product->image_path)); ?>" alt="<?php echo e($product->name); ?>" loading="lazy">
                                    <?php else: ?>
                                        <span class="buyer-image-placeholder" aria-hidden="true">EZ</span>
                                    <?php endif; ?>
                                    <span class="buyer-arrival-category"><?php echo e($product->category_label); ?></span>
                                </span>
                                <span class="buyer-arrival-meta">
                                    <strong><?php echo e($product->name); ?></strong>
                                    <small><?php echo e($product->seller->business_name ?? 'EZiCart seller'); ?></small>
                                    <b>₱<?php echo e(number_format($product->price, 2)); ?></b>
                                </span>
                            </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="buyer-shelf buyer-discover-shelf" id="browse-products" aria-labelledby="market-title">
                <header class="buyer-shelf-heading buyer-discover-heading">
                    <div>
                        <p class="buyer-section-label">Find something for today</p>
                        <h2 id="market-title"><?php echo e(request('category') ? 'Products in ' . request('category') : (request('search') ? 'Search results' : 'Discover products')); ?></h2>
                        <p class="buyer-result-count"><?php echo e(number_format($products->total())); ?> <?php echo e(\Illuminate\Support\Str::plural('product', $products->total())); ?><?php echo e($hasFilters ? ($products->total() === 1 ? ' matches your filters.' : ' match your filters.') : ' currently available.'); ?></p>
                    </div>
                    <?php if($hasFilters): ?>
                        <a class="buyer-see-all" href="<?php echo e(route('home')); ?>#browse-products">Clear filters <span aria-hidden="true">↗</span></a>
                    <?php endif; ?>
                </header>

                <nav class="buyer-category-filter" aria-label="Filter by category">
                    <a href="<?php echo e(route('home')); ?>#browse-products" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-current' => !request('category')]); ?>">All products</a>
                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route('home', ['category' => $category['name']])); ?>#browse-products" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-current' => request('category') === $category['name']]); ?>"><?php echo e($category['name']); ?></a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </nav>

                <?php if($products->isEmpty()): ?>
                    <div class="buyer-empty-state" role="status">
                        <h3>No products found.</h3>
                        <?php if($hasFilters): ?>
                            <p>Try another search or category, or clear your filters to browse all available products.</p>
                            <a class="buyer-promo-action" href="<?php echo e(route('home')); ?>#browse-products">Clear filters</a>
                        <?php else: ?>
                            <p>No approved products are available right now. Please check back soon.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="buyer-product-grid">
                        <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <a href="<?php echo e(route('product.show', $product->id)); ?>" class="buyer-product-card">
                                <span class="buyer-product-image">
                                    <?php if($product->image_path): ?>
                                        <img src="<?php echo e(asset('storage/' . $product->image_path)); ?>" alt="<?php echo e($product->name); ?>" loading="lazy">
                                    <?php else: ?>
                                        <span class="buyer-image-placeholder" aria-hidden="true">EZ</span>
                                    <?php endif; ?>
                                    <span class="buyer-product-category"><?php echo e($product->category_label); ?></span>
                                </span>
                                <span class="buyer-product-details">
                                    <strong><?php echo e($product->name); ?></strong>
                                    <small><?php echo e($product->seller->business_name ?? 'EZiCart seller'); ?></small>
                                    <span class="buyer-product-price-row"><b>₱<?php echo e(number_format($product->price, 2)); ?></b><span><?php echo e($product->average_rating > 0 ? number_format($product->average_rating, 1) . ' / 5' : 'New listing'); ?></span></span>
                                </span>
                            </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <nav class="buyer-pagination" aria-label="Product pages">
                        <?php echo e($products->appends(request()->query())->fragment('browse-products')->links()); ?>

                    </nav>
                <?php endif; ?>
            </section>

            <section class="buyer-help-shelf" id="how-it-works" aria-labelledby="buyer-help-title">
                <div class="buyer-howto">
                    <p class="buyer-section-label">A simple way to shop</p>
                    <h2 id="buyer-help-title">Browse, choose, and track your order.</h2>
                    <ol>
                        <li><span>01</span><strong>Browse</strong><small>Search or choose a category.</small></li>
                        <li><span>02</span><strong>Compare</strong><small>Review listing and seller details.</small></li>
                        <li><span>03</span><strong>Choose</strong><small>Add the products you want to your cart.</small></li>
                        <li><span>04</span><strong>Track</strong><small>Follow order updates from your account.</small></li>
                    </ol>
                </div>
                <div class="buyer-faq" aria-label="Marketplace information">
                    <h2>Shopping on EZiCart</h2>
                    <details><summary>What appears in the marketplace?</summary><p>Only approved products that are in stock appear in the marketplace.</p></details>
                    <details><summary>Where can I find product details?</summary><p>Open a listing to see its details, price, and seller information.</p></details>
                    <details><summary>How can I apply to sell?</summary><p>Submit the seller registration form for admin review.</p></details>
                </div>
            </section>
        </main>

        <footer class="editorial-footer buyer-store-footer">
            <div class="footer-topline"><a class="footer-wordmark" href="#top">EZiCart</a><p>Everyday products from sellers on EZiCart.</p><a class="footer-top-link" href="#top">Back to the beginning ↑</a></div>
            <div class="footer-bottomline"><span>© <?php echo e(date('Y')); ?> EZiCart Marketplace</span><a href="<?php echo e(route('register.seller')); ?>">Apply to sell</a><a href="<?php echo e(route('login')); ?>">Sign in</a></div>
        </footer>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/home.blade.php ENDPATH**/ ?>