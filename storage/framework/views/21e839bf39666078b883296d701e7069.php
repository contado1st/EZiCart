<?php $__env->startSection('workspace'); ?>
    <header class="ops-heading">
        <div>
            <h1>Dispatch parcels</h1>
            <p>Assign approved couriers to sorted parcels and release physical manifests from the hub.</p>
        </div>
        <div class="courier-actions">
            <span class="ops-status ops-status--pink"><?php echo e($parcels->total()); ?> dispatch queue</span>
        </div>
    </header>

    <section class="ops-panel ops-filter-panel" aria-label="Find a rider">
        <form class="ops-form" method="GET" action="<?php echo e(route('logistics.dispatch')); ?>">
            <div class="ops-field">
                <label for="dispatch-rider-search">Find an approved rider serving these parcels</label>
                <input id="dispatch-rider-search"
                    name="rider_search"
                    value="<?php echo e(request('rider_search')); ?>"
                    maxlength="100"
                    placeholder="Search by first or last name…">
            </div>
            <button class="ops-btn" type="submit">Search riders</button>
            <?php if(request()->filled('rider_search')): ?>
                <a class="ops-btn" href="<?php echo e(route('logistics.dispatch')); ?>">Clear search</a>
            <?php endif; ?>
        </form>
        <p class="ops-muted">Showing up to 50 eligible riders, ranked by active workload and failed-delivery count.</p>
    </section>

    <section class="ops-panel ops-batch-release" aria-labelledby="batch-release-title">
        <div class="ops-heading">
            <div>
                <h2 id="batch-release-title">Release a rider manifest</h2>
                <p class="ops-muted">Select assigned parcels for one rider, scan that rider’s badge, then scan every selected parcel label. The selected and scanned lists must match exactly.</p>
            </div>
        </div>

        <form id="batch-release-form" class="ops-form" method="POST" action="<?php echo e(route('logistics.dispatch.releaseBatch')); ?>" data-decision="logistics.dispatch.releaseBatch" data-decision-title="Release selected parcels?" data-decision-message="Verify every selected parcel and the assigned rider before confirming the physical batch handover." data-decision-label="Release selected parcels" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
            <?php echo csrf_field(); ?>
            <div class="ops-field">
                <label for="batch-rider-badge">Assigned rider badge QR/code</label>
                <input id="batch-rider-badge"
                    name="rider_badge"
                    data-qr-input
                    required
                    maxlength="100"
                    placeholder="EZR:…"
                    autocomplete="off">
            </div>

            <button class="ops-btn" type="button" data-qr-start>Scan rider badge</button>
            <button class="ops-btn ops-btn--danger" type="button" data-qr-stop hidden>Stop badge camera</button>
            <video data-qr-video playsinline hidden></video>
            <p class="ops-scan-status" data-qr-status role="status" aria-live="polite">Scan the assigned rider’s badge or enter it with a handheld scanner.</p>
            <input type="hidden" name="method" value="manual">

            <div class="ops-field">
                <label for="batch-parcel-references">Parcel label QR/codes (one per line)</label>
                <textarea id="batch-parcel-references"
                    name="parcel_references_text"
                    data-qr-batch
                    required
                    rows="4"
                    placeholder="EZP:…"></textarea>
            </div>

            <button class="ops-btn" type="button" data-qr-batch-start>Scan parcel labels</button>
            <button class="ops-btn ops-btn--danger" type="button" data-qr-batch-stop hidden>Stop camera</button>
            <video data-qr-batch-video playsinline hidden></video>
            <p class="ops-scan-status" data-qr-batch-status role="status" aria-live="polite">Scan each selected parcel once, or enter/paste codes one per line.</p>

            <button class="ops-btn ops-btn--primary" type="submit">Validate manifest and release selected parcels</button>
        </form>
    </section>

    <section class="ops-panel">
        <div class="ops-heading">
            <div>
                <h2>Sorted and assigned parcels</h2>
                <p class="ops-muted"><?php echo e($parcels->total()); ?> parcels staged or assigned to couriers.</p>
            </div>
        </div>

        <?php if($parcels->count() > 0 && $riders->isEmpty()): ?>
            <div class="ops-empty">
                <p>No eligible riders match the service areas and capacity of these parcels right now.</p>
            </div>
        <?php endif; ?>

        <div class="ops-table-wrap">
            <table class="ops-table">
                <thead>
                    <tr>
                        <th>Select batch</th>
                        <th>Order</th>
                        <th>Destination</th>
                        <th>Assigned rider</th>
                        <th>Assign / reassign</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $parcels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td>
                                <?php if(
                                    $order->status === 'ASSIGNED_TO_RIDER' &&
                                    !$order->hub_released_at &&
                                    $order->deliveryCourier?->status === 'approved' &&
                                    $order->payment_method === 'COD'): ?>
                                    <input type="checkbox"
                                        form="batch-release-form"
                                        name="order_ids[]"
                                        value="<?php echo e($order->id); ?>"
                                        aria-label="Select <?php echo e($order->order_number); ?> for batch release">
                                <?php endif; ?>
                            </td>
                            <td class="ops-mono">
                                <strong><?php echo e($order->order_number); ?></strong><?php if (isset($component)) { $__componentOriginal2969209a6e703f0dcf1ea87165624c74 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2969209a6e703f0dcf1ea87165624c74 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.parcel-weight','data' => ['order' => $order,'compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('parcel-weight'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['order' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order),'compact' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2969209a6e703f0dcf1ea87165624c74)): ?>
<?php $attributes = $__attributesOriginal2969209a6e703f0dcf1ea87165624c74; ?>
<?php unset($__attributesOriginal2969209a6e703f0dcf1ea87165624c74); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2969209a6e703f0dcf1ea87165624c74)): ?>
<?php $component = $__componentOriginal2969209a6e703f0dcf1ea87165624c74; ?>
<?php unset($__componentOriginal2969209a6e703f0dcf1ea87165624c74); ?>
<?php endif; ?>
                                <div>
                                    <span class="ops-status"><?php echo e(str_replace('_', ' ', $order->status)); ?></span>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo e($order->destinationArea?->name ?? $order->delivery_area); ?></strong>
                                <div class="ops-muted"><?php echo e($order->barangay); ?>, <?php echo e($order->municipality); ?></div>
                                <div class="ops-muted"><?php echo e($order->destinationArea?->name ?? 'Area unresolved'); ?></div>
                            </td>
                            <td>
                                <?php if($order->deliveryCourier): ?>
                                    <strong><?php echo e($order->deliveryCourier->first_name); ?> <?php echo e($order->deliveryCourier->last_name); ?></strong>
                                    <div class="ops-muted"><?php echo e(ucfirst($order->deliveryCourier->status)); ?></div>
                                <?php else: ?>
                                    <span class="ops-muted">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($order->status === 'RETURN_IN_TRANSIT'): ?>
                                    <span class="ops-muted">
                                        <?php echo e($order->return_handed_to_seller_at ? 'Seller receipt confirmation pending' : 'Awaiting courier to record seller handoff'); ?>

                                    </span>
                                <?php elseif(
                                    $order->status === 'DELIVERY_FAILED' ||
                                    ($order->status === 'OUT_FOR_DELIVERY' && $order->deliveryCourier?->status === 'suspended')): ?>
                                    <div class="ops-muted">
                                        <?php echo e($order->deliveryAttempts->count()); ?> attempts recorded. Scan the parcel into a Returns or Exception location before retry or seller return.
                                    </div>
                                    <form class="ops-form" method="POST" action="<?php echo e(route('logistics.scan')); ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="reference" value="EZP:<?php echo e($order->parcel_code); ?>">
                                        <input type="hidden" name="method" value="manual">
                                        <div class="ops-field">
                                            <label for="return-location-<?php echo e($order->id); ?>">Returns / Exception location QR or code</label>
                                            <input id="return-location-<?php echo e($order->id); ?>"
                                                name="location_reference"
                                                placeholder="EZL:…"
                                                required>
                                        </div>
                                        <button class="ops-btn ops-btn--primary" type="submit">Confirm physical return to hub</button>
                                    </form>
                                <?php else: ?>
                                    <?php if($order->status === 'ASSIGNED_TO_RIDER' && $order->hub_released_at): ?>
                                        <div class="ops-muted">
                                            Hub handoff is complete. Confirm physical recovery before dispatching this parcel to another rider.
                                        </div>
                                        <form method="POST"
                                            action="<?php echo e(route('logistics.orders.recoverReleasedParcel', $order)); ?>" data-decision="logistics.orders.recoverReleasedParcel" data-decision-title="Confirm parcel recovery?" data-decision-message="Continue only after logistics physically received the parcel back from the assigned rider." data-decision-label="Confirm recovery" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                            <?php echo csrf_field(); ?>
                                            <button class="ops-btn ops-btn--primary" type="submit">Record parcel recovered at hub</button>
                                        </form>
                                    <?php else: ?>
                                        <?php ($suggestedRider = $suggestedRiders[$order->id] ?? null); ?>
                                        <?php if(
                                            $order->status === 'SORTED' &&
                                            $order->deliveryAttempts->where('outcome', 'failed')->count() >=
                                                max(1, (int) config('logistics.maximum_delivery_attempts', 3))): ?>
                                            <div class="ops-muted">
                                                Maximum delivery attempts reached. Assign a courier, confirm hub handoff, then start a seller return.
                                            </div>
                                        <?php endif; ?>
                                        <?php if($suggestedRider): ?>
                                            <div class="ops-muted">
                                                Suggested: <?php echo e($suggestedRider->first_name); ?> <?php echo e($suggestedRider->last_name); ?>

                                                (<?php echo e($suggestedRider->capacity_load_count); ?>/<?php echo e($maxActiveDeliveries); ?> parcels in progress)
                                            </div>
                                        <?php else: ?>
                                            <div class="ops-muted">No rider matches this area and capacity right now.</div>
                                        <?php endif; ?>
                                        <form class="ops-form" method="POST" action="<?php echo e(route('logistics.orders.assignRider', $order)); ?>" data-decision="logistics.orders.assignRider" data-decision-title="Assign delivery rider?" data-decision-message="Check the selected rider and destination before recording this assignment." data-decision-label="Assign rider" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                            <?php echo csrf_field(); ?>
                                            <div class="ops-field">
                                                <label for="rider-<?php echo e($order->id); ?>">Approved rider · workload</label>
                                                <select id="rider-<?php echo e($order->id); ?>" name="delivery_courier_id" required>
                                                    <option value="">Select rider</option>
                                                    <?php $__currentLoopData = $riders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <?php if(
                                                            $rider->serviceAreas->contains('id', $order->destination_area_id) &&
                                                            ($rider->capacity_load_count < $maxActiveDeliveries || $order->delivery_courier_id === $rider->id)): ?>
                                                            <option value="<?php echo e($rider->id); ?>" <?php if($order->delivery_courier_id === $rider->id): echo 'selected'; endif; ?>>
                                                                <?php echo e($rider->first_name); ?> <?php echo e($rider->last_name); ?> (<?php echo e($rider->vehicle_type); ?>; <?php echo e($rider->vehicle_capacity_verified_at ? \App\Services\ParcelLoadService::kg($rider->vehicle_capacity_grams) : 'capacity unverified'); ?>) ·
                                                                <?php echo e($rider->active_deliveries_count); ?> active ·
                                                                <?php echo e($rider->serviceAreas->pluck('name')->join(', ')); ?> area(s)
                                                            </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </select>
                                            </div>
                                            <label class="ops-field">Backup / coverage override reason (when using a non-primary rider)<input name="coverage_override_reason" minlength="5" maxlength="500"></label>
                                            <button class="ops-btn ops-btn--primary" type="submit">
                                                <?php echo e($order->delivery_courier_id ? 'Reassign' : 'Assign'); ?>

                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if($order->status === 'ASSIGNED_TO_RIDER'): ?>
                                        <?php if($order->hub_released_at): ?>
                                            <div class="ops-muted">
                                                Hub release recorded <?php echo e($order->hub_released_at->format('d M Y H:i')); ?>.
                                            </div>
                                            <form method="POST" action="<?php echo e(route('logistics.orders.return', $order)); ?>" data-decision="logistics.orders.return" data-decision-title="Start return handling?" data-decision-message="This moves the selected parcel into the return workflow. Check the reason first." data-decision-label="Start return" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                <?php echo csrf_field(); ?>
                                                <button class="ops-btn ops-btn--danger" type="submit">Start seller return</button>
                                            </form>
                                        <?php else: ?>
                                            <?php ($assignedRider = $riders->firstWhere('id', $order->delivery_courier_id)); ?>
                                            <?php if($assignedRider): ?>
                                                <div class="ops-muted">
                                                    Verify assigned rider: <?php echo e($assignedRider->first_name); ?> <?php echo e($assignedRider->last_name); ?>

                                                </div>
                                                <div class="logistics-badge-qr" aria-label="QR badge for assigned rider">
                                                    <?php echo $assignedRider->badge_qr; ?>

                                                </div>
                                            <?php endif; ?>
                                            <form method="POST" action="<?php echo e(route('logistics.orders.releaseToRider', $order)); ?>" data-decision="logistics.orders.releaseToRider" data-decision-title="Release parcel to rider?" data-decision-message="Continue only after verifying the assigned rider and physically handing over the parcel." data-decision-label="Confirm release" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                                <?php echo csrf_field(); ?>
                                                <div class="ops-field">
                                                    <label for="rider-badge-<?php echo e($order->id); ?>">Scan assigned rider badge QR/code</label>
                                                    <input id="rider-badge-<?php echo e($order->id); ?>"
                                                        name="rider_badge"
                                                        required
                                                        placeholder="EZR:…"
                                                        autocomplete="off">
                                                </div>
                                                <div class="ops-field">
                                                    <label for="parcel-reference-<?php echo e($order->id); ?>">Scan parcel label QR/code</label>
                                                    <input id="parcel-reference-<?php echo e($order->id); ?>"
                                                        name="parcel_reference"
                                                        required
                                                        placeholder="EZP:…"
                                                        autocomplete="off">
                                                </div>
                                                <input type="hidden" name="method" value="manual">
                                                <button class="ops-btn ops-btn--primary" type="submit">Confirm scanned hub handoff</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5">
                                <div class="ops-empty">
                                    <strong>No parcels staged</strong>
                                    <p>No sorted parcels are waiting for dispatch.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="ops-pagination"><?php echo e($parcels->links()); ?></div>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.logistics', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/logistics/dispatch.blade.php ENDPATH**/ ?>