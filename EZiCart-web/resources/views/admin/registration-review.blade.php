@extends('layouts.app')

@section('title', 'Application review | EZiCart')

@section('content')
@php
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
@endphp
<main class="web-operations registration-review">
    <header>
        <a class="dash-action-link" href="{{ $backUrl }}">Back to applications</a>
        <h1>Application review</h1>
        <p>{{ $name }} · {{ str_replace('_', ' ', $applicant->role) }} · {{ ucfirst($applicant->status) }}</p>
    </header>
    @if(session('success'))<p class="alert-success" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="alert-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="registration-review-details">
        @foreach($groups as $heading => $fields)
            <section class="dash-panel" aria-labelledby="review-section-{{ $loop->index }}">
                <h2 id="review-section-{{ $loop->index }}">{{ $heading }}</h2>
                <dl class="registration-review-fields">
                    @foreach($fields as $label => $value)
                        <div><dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : 'Not provided' }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>
    <section class="dash-panel" aria-labelledby="review-documents">
        <h2 id="review-documents">Submitted documents</h2>
        <p class="web-muted">Open images and PDFs to inspect them, or download a copy for review. Missing files are listed below.</p>
        <ul class="registration-review-documents">
            @foreach($documents as $document)
                <li>
                    <div><strong>{{ $document['label'] }}</strong><span class="web-muted">{{ $document['available'] ? 'Available for review' : ($document['submitted'] ? 'File unavailable — request a new copy' : 'Not submitted') }}</span></div>
                    @if($document['available'])
                        <div class="web-actions">
                            <a class="dash-btn-sm dash-btn-outline" href="{{ route($documentRoute, [$applicant, $document['type'], 'preview' => 1]) }}" target="_blank" rel="noopener" aria-label="View {{ $document['label'] }} in a new tab">View document</a>
                            <a class="dash-btn-sm dash-btn-outline" href="{{ route($documentRoute, [$applicant, $document['type']]) }}" download aria-label="Download {{ $document['label'] }}">Download</a>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
    @if($applicant->status === 'pending')
        <section class="dash-panel" aria-labelledby="review-decision">
            <h2 id="review-decision">Review decision</h2>
            <p>Review the applicant’s details and supporting documents before approving or rejecting this application.</p>
            <div class="web-actions">
                <form action="{{ route($approveRoute, $applicant) }}" method="POST" data-decision="application-approve" data-decision-title="Approve this account?" data-decision-message="Confirm that you reviewed this application and its supporting documents." data-decision-label="Approve account" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    @csrf<button type="submit" class="dash-btn-primary">Approve application</button>
                </form>
                <form action="{{ route($rejectRoute, $applicant) }}" method="POST" class="web-form" data-decision="application-reject" data-decision-title="Reject this application?" data-decision-message="Check the reason before recording your decision." data-decision-label="Reject application" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                    @csrf<label for="review-rejection-reason">Rejection reason<input id="review-rejection-reason" name="reason" value="{{ old('reason') }}" required minlength="5" maxlength="1000"></label>
                    <button type="submit" class="dash-btn-sm dash-btn-outline">Reject application</button>
                </form>
            </div>
        </section>
    @endif
</main>
@endsection
