<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')('resources/css/shared/dashboard.css'); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="dash-wrapper">
<main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Product compliance</h1>
                    <p class="dash-subtitle">Review seller-submitted product details and record the reason for each flag.
                    </p>
                </div>
            </div>

            <?php if(session('success')): ?>
                <div class="dash-alert-success"><?php echo e(session('success')); ?></div>
            <?php endif; ?>

            <form method="GET" action="<?php echo e(route('admin.compliance.products.index')); ?>"
                class="dash-panel u-extracted-35c33ad837">
                <div class="form-group"><label class="form-label" for="search">Search product, category, or
                        seller</label><input id="search" class="form-control" name="search"
                        value="<?php echo e(request('search')); ?>" maxlength="100"></div>
                <div class="form-group"><label class="form-label" for="status">Status</label><select id="status"
                        class="form-control" name="status">
                        <option value="">Needs review and flagged</option>
                        <?php $__currentLoopData = ['pending_review' => 'Pending review', 'flagged' => 'Flagged', 'approved' => 'Approved']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($value); ?>" <?php if(request('status') === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <button class="dash-btn-primary" type="submit">Filter</button>
            </form>

            <div class="dash-table-wrapper u-extracted-0cf8f93b0e">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Product and category</th>
                            <th>Seller</th>
                            <th>Review history</th>
                            <th>Review action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td><strong><?php echo e($product->name); ?></strong>
                                    <div><?php echo e($product->category_label); ?></div>
                                    <div class="text-muted-small"><?php echo e($product->description); ?></div><span
                                        class="dash-badge dash-badge-archived"><?php echo e(str_replace('_', ' ', ucfirst($product->compliance_status))); ?></span>
                                    <details class="product-compliance-media"><summary>Review photos &amp; video</summary><?php if (isset($component)) { $__componentOriginal3e02afc58783df8e9f8209bbd7eced29 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3e02afc58783df8e9f8209bbd7eced29 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.product-gallery','data' => ['product' => $product,'compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('product-gallery'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['product' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($product),'compact' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3e02afc58783df8e9f8209bbd7eced29)): ?>
<?php $attributes = $__attributesOriginal3e02afc58783df8e9f8209bbd7eced29; ?>
<?php unset($__attributesOriginal3e02afc58783df8e9f8209bbd7eced29); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3e02afc58783df8e9f8209bbd7eced29)): ?>
<?php $component = $__componentOriginal3e02afc58783df8e9f8209bbd7eced29; ?>
<?php unset($__componentOriginal3e02afc58783df8e9f8209bbd7eced29); ?>
<?php endif; ?></details>
                                </td>
                                <td><?php echo e($product->seller?->business_name ?? $product->seller?->first_name); ?><div
                                        class="text-muted-small">Seller category:
                                        <?php echo e($product->seller?->line_of_business ?: 'Not provided'); ?></div>
                                    <div class="text-muted-small"><?php echo e($product->seller?->email); ?></div>
                                    <div class="text-muted-small"><?php echo e($product->warning_count); ?> recorded warning(s)</div><a
                                        href="<?php echo e(route('admin.moderation.index', ['search' => $product->seller?->email])); ?>">Review
                                        seller account</a>
                                </td>
                                <td>
                                    <?php $__empty_2 = true; $__currentLoopData = $product->complianceEvents->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                                        <div><strong><?php echo e(ucfirst(str_replace('_', ' ', $event->action))); ?></strong> ·
                                            <?php echo e($event->actor?->first_name); ?> <?php echo e($event->actor?->last_name); ?><div
                                                class="text-muted-small">
                                                <?php echo e($event->created_at->format('M d, Y H:i')); ?><?php echo e($event->note ? ' · ' . $event->note : ''); ?>

                                            </div>
                                    </div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?><span class="text-muted-small">No review events.</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST"
                                        action="<?php echo e(route('admin.compliance.products.review', $product)); ?>" data-decision="admin.compliance.products.review" data-decision-title="Save compliance decision?" data-decision-message="Check the selected product decision and explanation. This can affect the listing availability." data-decision-label="Save decision" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('PATCH'); ?>
                                        <div class="form-group"><label class="form-label"
                                                for="decision-<?php echo e($product->id); ?>">Decision</label><select
                                                id="decision-<?php echo e($product->id); ?>" class="form-control"
                                                name="compliance_status">
                                                <option value="approved">Approve listing</option>
                                                <option value="flagged">Flag and warn seller</option>
                                            </select></div>
                                        <div class="form-group"><label class="form-label"
                                                for="note-<?php echo e($product->id); ?>">Review note (required when flagging)</label>
                                            <textarea id="note-<?php echo e($product->id); ?>" class="form-control" name="compliance_note" maxlength="1000"></textarea>
                                        </div>
                                        <button class="dash-btn-sm dash-btn-primary" type="submit">Save review</button>
                                    </form>
                                    <form method="POST" action="<?php echo e(route('admin.compliance.products.warn', $product)); ?>"
                                        class="u-extracted-0cf8f93b0e" data-decision="admin.compliance.products.warn" data-decision-title="Issue seller warning?" data-decision-message="Check your warning message before sending it to the seller." data-decision-label="Issue warning" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                        <?php echo csrf_field(); ?>
                                        <div class="form-group"><label class="form-label"
                                                for="warning-<?php echo e($product->id); ?>">Separate seller warning</label>
                                            <textarea id="warning-<?php echo e($product->id); ?>" class="form-control" name="warning_note" maxlength="1000" required></textarea>
                                        </div>
                                        <button class="dash-btn-sm" type="submit">Issue warning</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="4" class="dash-table-empty">No products match this review queue.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo e($products->links()); ?>

        </main>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/admin/compliance/index.blade.php ENDPATH**/ ?>