<?php $__env->startSection('title', 'Application review | EZiCart'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $name = collect([$applicant->first_name, $applicant->middle_name ?: $applicant->middle_initial, $applicant->last_name, $applicant->suffix])->filter()->join(' ');
    $groups = [
        'Personal details' => [
            'Full name' => $name, 'Email address' => $applicant->email,
            'Contact number' => $applicant->contact_no, 'Date of birth' => $applicant->birthday?->format('d M Y'),
            'Age' => $applicant->age, 'Sex' => $applicant->sex,
        ],
        'Registered address' => [
            'House / building number' => $applicant->house_number, 'Street address' => $applicant->street_address,
            'Barangay' => $applicant->barangay, 'City / municipality' => $applicant->municipality,
            'Province' => $applicant->province, 'Region' => $applicant->region, 'Postal code' => $applicant->postal_code,
        ],
    ];
    if (in_array($applicant->role, ['seller', 'sorting_center'], true)) {
        $groups['Business details'] = [
            'Business / company name' => $applicant->business_name, 'Shop name' => $applicant->shop_name,
            'Business category' => $applicant->line_of_business, 'Seller type' => $applicant->seller_type,
            'TIN' => $applicant->tin,
        ];
    }
    if ($applicant->role === 'sorting_center') {
        $groups['Business details'] += ['SEC registration number' => $applicant->sec_registration_no, 'Contact person’s position' => $applicant->contact_position, 'Requested service areas' => implode(', ', $applicant->service_areas ?? [])];
    }
    if ($applicant->role === 'courier') {
        $groups['Rider and vehicle details'] = [
            'License number' => $applicant->license_number, 'License expiry' => $applicant->license_expires_at?->format('d M Y'),
            'Vehicle type' => $applicant->vehicle_type, 'Vehicle category' => $applicant->vehicle_category,
            'Plate number' => $applicant->plate_number,
        ];
    }
    $groups['Application record'] = [
        'Account role' => str_replace('_', ' ', $applicant->role), 'Status' => ucfirst($applicant->status),
        'Registered on' => $applicant->created_at?->format('d M Y, h:i A'), 'Last updated' => $applicant->updated_at?->format('d M Y, h:i A'),
        'Email verified' => $applicant->email_verified_at?->format('d M Y, h:i A'),
        'Terms accepted' => $applicant->terms_accepted_at?->format('d M Y, h:i A'),
        'Privacy policy accepted' => $applicant->privacy_accepted_at?->format('d M Y, h:i A'),
        'Approved on' => $applicant->approved_at?->format('d M Y, h:i A'), 'Rejection reason' => $applicant->rejection_reason,
    ];
?>
<main class="web-operations registration-review">
    <header>
        <a class="dash-action-link" href="<?php echo e($backUrl); ?>">Back to applications</a>
        <h1>Application review</h1>
        <p><?php echo e($name); ?> · <?php echo e(str_replace('_', ' ', $applicant->role)); ?> · <?php echo e(ucfirst($applicant->status)); ?></p>
    </header>
    <?php if(session('success')): ?><p class="alert-success" role="status"><?php echo e(session('success')); ?></p><?php endif; ?>
    <?php if($errors->any()): ?><div class="alert-error" role="alert"><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div><?php endif; ?>
    <div class="registration-review-details">
        <?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $heading => $fields): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <section class="dash-panel" aria-labelledby="review-section-<?php echo e($loop->index); ?>">
                <h2 id="review-section-<?php echo e($loop->index); ?>"><?php echo e($heading); ?></h2>
                <dl class="registration-review-fields">
                    <?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div><dt><?php echo e($label); ?></dt><dd><?php echo e(filled($value) ? $value : 'Not provided'); ?></dd></div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </dl>
            </section>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <section class="dash-panel" aria-labelledby="review-documents">
        <h2 id="review-documents">Submitted documents</h2>
        <p class="web-muted">Open images and PDFs to inspect them, or download a copy for review. Missing files are listed below.</p>
        <ul class="registration-review-documents">
            <?php $__currentLoopData = $documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li>
                    <div><strong><?php echo e($document['label']); ?></strong><span class="web-muted"><?php echo e($document['available'] ? 'Available for review' : ($document['submitted'] ? 'File unavailable — request a new copy' : 'Not submitted')); ?></span></div>
                    <?php if($document['available']): ?>
                        <div class="web-actions">
                            <a class="dash-btn-sm dash-btn-outline" href="<?php echo e(route($documentRoute, [$applicant, $document['type'], 'preview' => 1])); ?>" target="_blank" rel="noopener" aria-label="View <?php echo e($document['label']); ?> in a new tab">View document</a>
                            <a class="dash-btn-sm dash-btn-outline" href="<?php echo e(route($documentRoute, [$applicant, $document['type']])); ?>" download aria-label="Download <?php echo e($document['label']); ?>">Download</a>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </section>
    <?php if($applicant->status === 'pending'): ?>
        <section class="dash-panel" aria-labelledby="review-decision">
            <h2 id="review-decision">Review decision</h2>
            <p>Review the applicant’s details and supporting documents before approving or rejecting this application.</p>
            <div class="web-actions">
                <form action="<?php echo e(route($approveRoute, $applicant)); ?>" method="POST" data-decision="application-approve" data-decision-title="Approve this account?" data-decision-message="Confirm that you reviewed this application and its supporting documents." data-decision-label="Approve account" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    <?php echo csrf_field(); ?><button type="submit" class="dash-btn-primary">Approve application</button>
                </form>
                <form action="<?php echo e(route($rejectRoute, $applicant)); ?>" method="POST" class="web-form" data-decision="application-reject" data-decision-title="Reject this application?" data-decision-message="Check the reason before recording your decision." data-decision-label="Reject application" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    <?php echo csrf_field(); ?><label for="review-rejection-reason">Rejection reason<input id="review-rejection-reason" name="reason" value="<?php echo e(old('reason')); ?>" required minlength="5" maxlength="1000"></label>
                    <button type="submit" class="dash-btn-sm dash-btn-outline">Reject application</button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/admin/registration-review.blade.php ENDPATH**/ ?>