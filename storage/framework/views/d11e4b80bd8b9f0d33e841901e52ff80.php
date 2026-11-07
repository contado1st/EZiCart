<?php $__env->startSection('title', 'EZiCart — Shop products from marketplace sellers'); ?>
<?php $__env->startSection('meta-description',
    'Browse approved, in-stock products from EZiCart sellers. Compare listing details and
    prices, shop by category, and follow your orders.'); ?>

    <?php $__env->startPush('styles'); ?>
        <?php echo app('Illuminate\Foundation\Vite')('resources/css/storefront/landing.css'); ?>
    <?php $__env->stopPush(); ?>

    <?php
        $featuredProducts = $products->take(4);
        $featuredProduct = $products->first();
        $hasFilters = request()->filled('category') || request()->filled('search');
    ?>

<?php $__env->startSection('content'); ?>
    <div class="ez-editorial" id="top">
        <nav class="editorial-nav" aria-label="Marketplace sections">
            <a href="#discover" data-rev>The marketplace</a>
            <a href="#how-it-works" data-rev>How shopping works</a>
            <a href="#browse-products" data-rev>Explore products</a>
            <a href="<?php echo e(route('register.seller')); ?>" data-rev>Apply to sell</a>
        </nav>

        <main>
            <section class="editorial-stage editorial-hero" data-scroll-stage="hero" aria-labelledby="hero-title">
                <div class="editorial-pin hero-pin">
                    <img class="hero-photograph" src="<?php echo e(asset('images/hero_asset.jpg')); ?>" alt=""
                        fetchpriority="high" width="1600" height="900">
                    <div class="hero-paper-wash" aria-hidden="true"></div>
                    <div class="hero-copy">
                        <h1 id="hero-title" class="hero-wordmark" data-word-reveal>EZiCart</h1>
                        <p class="hero-description" data-word-reveal>
                            Shop approved, in-stock products from EZiCart sellers. Compare details and prices, then add your
                            picks to the cart.
                        </p>
                        <a class="editorial-button hero-cta" href="#browse-products" data-rev>Browse the marketplace</a>
                    </div>
                    <div class="hero-straps" aria-label="Marketplace details">
                        <span data-rev>Seller details</span>
                        <span data-rev>Prices shown</span>
                        <span data-rev>Order updates</span>
                    </div>
                    <span class="stage-index" aria-hidden="true">01 / 03</span>
                </div>
            </section>

            <section class="statement-band" id="discover" aria-labelledby="statement-title">
                <div class="editorial-width statement-inner">
                    <h2 id="statement-title" data-word-reveal>Find products with the details in view.</h2>
                    <div class="statement-columns">
                        <p data-rev>Browse approved listings that currently have stock available.</p>
                        <p data-rev>Check product information, prices, and seller details before you choose.</p>
                        <p data-rev>Shop across categories, add products to your cart, and follow order updates from your
                            account.</p>
                    </div>
                </div>
            </section>

            <section class="editorial-stage editorial-reveal" data-scroll-stage="reveal" aria-labelledby="reveal-title">
                <div class="editorial-pin reveal-pin">
                    <img class="reveal-photograph" src="<?php echo e(asset('images/Banner_3.jpg')); ?>" alt="" loading="lazy"
                        width="1408" height="960">
                    <div class="reveal-paper" aria-hidden="true"></div>
                    <div class="reveal-copy">
                        <h2 id="reveal-title" data-word-reveal>Shop across the categories you use.</h2>
                        <p class="reveal-caption" data-rev>Browse fashion, home and living, beauty, electronics, and
                            groceries.</p>
                        <a href="#browse-products" class="reveal-link" data-rev>Browse available products <span
                                aria-hidden="true">↗</span></a>
                    </div>
                    <span class="stage-index" aria-hidden="true">02 / 03</span>
                </div>
            </section>

            <section class="market-edits" aria-labelledby="edits-title">
                <div class="editorial-width">
                    <div class="edits-heading">
                        <h2 id="edits-title" data-word-reveal>Recently added products</h2>
                        <p data-rev>See current listings and prices. Select a product to view its details.</p>
                    </div>

                    <?php if($featuredProducts->isNotEmpty()): ?>
                        <div class="edits-list" aria-label="Featured available products">
                            <?php $__currentLoopData = $featuredProducts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <a href="<?php echo e(route('product.show', $product->id)); ?>" class="edit-row" data-rev>
                                    <span class="edit-index"
                                        aria-hidden="true"><?php echo e(str_pad($index + 1, 2, '0', STR_PAD_LEFT)); ?></span>
                                    <span class="edit-product">
                                        <span class="edit-name"><?php echo e($product->name); ?></span>
                                        <span
                                            class="edit-description"><?php echo e(\Illuminate\Support\Str::limit($product->description ?: 'View product details and seller information.', 105)); ?></span>
                                    </span>
                                    <span
                                        class="edit-seller"><?php echo e($product->seller->business_name ?? 'EZiCart seller'); ?></span>
                                    <span class="edit-price">₱<?php echo e(number_format($product->price, 2)); ?></span>
                                    <span class="edit-arrow" aria-hidden="true">↗</span>
                                </a>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    <?php else: ?>
                        <p class="edits-empty">
                            <?php if($hasFilters): ?>
                                No products match these filters yet. Browse all available listings to start fresh.
                            <?php else: ?>
                                No approved products are available right now. Please check back soon.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <a class="text-link" href="#browse-products" data-rev>Browse all products <span
                            aria-hidden="true">↓</span></a>
                </div>
            </section>

            <section class="editorial-stage editorial-composition" data-scroll-stage="composition" id="how-it-works"
                aria-labelledby="composition-title">
                <div class="editorial-pin composition-pin">
                    <div class="composition-copy">
                        <h2 id="composition-title" data-word-reveal>Browse, choose, and track your order.</h2>
                        <p class="composition-intro" data-rev>Shopping on EZiCart takes a few simple steps.</p>
                        <ol class="journey-list">
                            <li data-journey-row><span class="journey-index">01</span><span
                                    class="journey-name">Browse</span><span class="journey-detail">Search or choose a
                                    category.</span></li>
                            <li data-journey-row><span class="journey-index">02</span><span
                                    class="journey-name">Explore</span><span class="journey-detail">Open a listing for
                                    seller and product details.</span></li>
                            <li data-journey-row><span class="journey-index">03</span><span
                                    class="journey-name">Choose</span><span class="journey-detail">Add the products you want
                                    to your cart.</span></li>
                            <li data-journey-row><span class="journey-index">04</span><span
                                    class="journey-name">Track</span><span class="journey-detail">Check order updates from
                                    your account.</span></li>
                        </ol>
                    </div>
                    <a class="composition-image-link"
                        href="<?php echo e($featuredProduct ? route('product.show', $featuredProduct->id) : '#browse-products'); ?>"
                        aria-label="<?php echo e($featuredProduct ? 'View ' . $featuredProduct->name : 'Browse available products'); ?>">
                        <?php if($featuredProduct?->image_path): ?>
                            <img class="composition-image" src="<?php echo e(asset('storage/' . $featuredProduct->image_path)); ?>"
                                alt="<?php echo e($featuredProduct->name); ?>" loading="lazy">
                        <?php else: ?>
                            <img class="composition-image composition-image-placeholder"
                                src="<?php echo e(asset('images/floating_element_1.png')); ?>" alt="" loading="lazy"
                                width="970" height="1455">
                        <?php endif; ?>
                        <?php if($featuredProduct): ?>
                            <span
                                class="composition-label"><span><?php echo e($featuredProduct->category); ?></span><strong><?php echo e($featuredProduct->name); ?></strong><em>₱<?php echo e(number_format($featuredProduct->price, 2)); ?></em></span>
                        <?php else: ?>
                            <span class="composition-label"><span>EZiCart</span><strong>Browse the
                                    marketplace</strong><em>Explore available finds</em></span>
                        <?php endif; ?>
                    </a>
                    <span class="stage-index" aria-hidden="true">03 / 03</span>
                </div>
            </section>

            <section class="marketplace-section" id="browse-products" aria-labelledby="market-title">
                <div class="editorial-width">
                    <header class="market-heading">
                        <div>
                            <h2 id="market-title" data-word-reveal>
                                <?php echo e(request('category') ? 'Products in ' . request('category') : (request('search') ? 'Search results' : 'Browse products')); ?>

                            </h2>
                            <p data-rev><?php echo e(number_format($products->total())); ?>

                                <?php echo e(\Illuminate\Support\Str::plural('product', $products->total())); ?><?php echo e($hasFilters ? ($products->total() === 1 ? ' matches your filters.' : ' match your filters.') : ' currently available.'); ?>

                            </p>
                        </div>
                        <?php if($hasFilters): ?>
                            <a class="text-link clear-filters" href="<?php echo e(route('home')); ?>#browse-products">Clear filters
                                <span aria-hidden="true">↗</span></a>
                        <?php endif; ?>
                    </header>

                    <nav class="category-index" aria-label="Browse product categories">
                        <a href="<?php echo e(route('home')); ?>#browse-products"
                            class="<?php echo e(request('category') ? '' : 'is-current'); ?>">All</a>
                        <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <a href="<?php echo e(route('home', ['category' => $category['name']])); ?>#browse-products"
                                class="<?php echo e(request('category') === $category['name'] ? 'is-current' : ''); ?>">
                                <span><?php echo e($category['name']); ?></span>
                                <span class="category-count"
                                    aria-hidden="true"><?php echo e(str_pad($loop->iteration, 2, '0', STR_PAD_LEFT)); ?></span>
                            </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </nav>

                    <?php if($products->isEmpty()): ?>
                        <div class="market-empty" role="status">
                            <h3>No products found.</h3>
                            <?php if($hasFilters): ?>
                                <p>Try a different search or category, or clear your filters to browse all products.</p>
                                <a class="editorial-button" href="<?php echo e(route('home')); ?>#browse-products">Clear filters and
                                    browse all</a>
                            <?php else: ?>
                                <p>No approved, in-stock products are available right now. Please check back soon.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="product-grid">
                            <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <a href="<?php echo e(route('product.show', $product->id)); ?>" class="product-entry" data-rev>
                                    <span class="product-image-frame">
                                        <?php if($product->image_path): ?>
                                            <img src="<?php echo e(asset('storage/' . $product->image_path)); ?>"
                                                alt="<?php echo e($product->name); ?>" class="product-image" loading="lazy">
                                        <?php else: ?>
                                            <span class="product-image-empty" aria-hidden="true">EZ</span>
                                        <?php endif; ?>
                                        <span class="product-category"><?php echo e($product->category_label); ?></span>
                                    </span>
                                    <span class="product-meta">
                                        <span class="product-name"><?php echo e($product->name); ?></span>
                                        <span
                                            class="product-seller"><?php echo e($product->seller->business_name ?? 'EZiCart seller'); ?></span>
                                        <span class="product-bottom"><span
                                                class="product-price">₱<?php echo e(number_format($product->price, 2)); ?></span><span
                                                class="product-rating"><?php echo e($product->average_rating > 0 ? number_format($product->average_rating, 1) . ' / 5' : 'New listing'); ?></span></span>
                                    </span>
                                </a>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>

                        <nav class="product-pagination" aria-label="Product pages">
                            <?php echo e($products->appends(request()->query())->fragment('browse-products')->links()); ?>

                        </nav>
                    <?php endif; ?>
                </div>
            </section>

            <section class="editorial-faq" aria-labelledby="faq-title">
                <div class="editorial-width faq-layout">
                    <div class="faq-heading">
                        <h2 id="faq-title" data-word-reveal>Shopping on EZiCart</h2>
                        <p data-rev>Listings, product details, and selling on the marketplace.</p>
                    </div>
                    <div class="faq-list">
                        <details data-rev>
                            <summary>What appears in the marketplace?</summary>
                            <p>Only approved products that are in stock appear in the marketplace.</p>
                        </details>
                        <details data-rev>
                            <summary>How can I learn more about an item?</summary>
                            <p>Open a product listing to see its details, price, and seller information.</p>
                        </details>
                        <details data-rev>
                            <summary>How do I apply to sell?</summary>
                            <p>Select “Apply to sell” and submit the seller registration form for review.</p>
                        </details>
                    </div>
                </div>
            </section>
        </main>

        <footer class="editorial-footer">
            <div class="footer-topline">
                <a class="footer-wordmark" href="#top">EZiCart</a>
                <p>Everyday products from sellers on EZiCart.</p>
                <a class="footer-top-link" href="#top">Back to the beginning ↑</a>
            </div>
            <div class="footer-bottomline">
                <span>© <?php echo e(date('Y')); ?> EZiCart Marketplace</span>
                <a href="<?php echo e(route('register.seller')); ?>">Apply to sell</a>
                <a href="<?php echo e(route('login')); ?>">Sign in</a>
            </div>
        </footer>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/home-guest.blade.php ENDPATH**/ ?>