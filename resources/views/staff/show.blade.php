@extends('layouts.app')
@section('content')
<main class="web-operations"><header><h1>{{ $staff->first_name }} {{ $staff->last_name }}</h1><p>{{ str_replace('_', ' ', $staff->role) }} · {{ ucfirst($staff->status) }}</p></header>
@include('components.feature-feedback')
<section class="dash-panel"><h2>Verified profile information</h2>
@if($staff->role === 'courier')
    <x-vehicle-capacity :rider="$staff" />
    @if(auth()->user()->isLogisticsAdmin())
        <form class="web-form" method="POST" action="{{ route('logistics-admin.riders.capacity', $staff) }}" data-decision="vehicle-capacity" data-decision-title="Verify this cargo capacity?" data-decision-message="Confirm the maximum cargo payload for this specific current vehicle. Unknown or overweight loads will be blocked." data-decision-label="Verify capacity" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf @method('PATCH')
            <label for="vehicle-capacity-{{ $staff->id }}">Verified maximum cargo capacity (kg)<input id="vehicle-capacity-{{ $staff->id }}" name="vehicle_capacity_kg" type="number" min="0.001" max="100000" step="0.001" value="{{ $staff->vehicle_capacity_grams ? $staff->vehicle_capacity_grams / 1000 : '' }}" required></label>
            <label for="capacity-verification-reason">Verification source / reason<input id="capacity-verification-reason" name="reason" minlength="5" maxlength="500" required placeholder="Current vehicle cargo payload documentation"></label><button class="dash-btn-sm dash-btn-primary" type="submit">Verify vehicle capacity</button>
        </form>
    @endif
@endif
@if($staff->profile_avatar_path)<img class="web-staff-photo" src="{{ route('staff.avatar', $staff) }}" alt="{{ $staff->first_name }} profile photo">@endif
<x-user-activity :user="$staff" />
<dl class="web-form"><div><dt>Contact</dt><dd>{{ $staff->contact_no }}</dd></div><div><dt>Operating location</dt><dd>{{ $staff->municipality }}, {{ $staff->province }}</dd></div><div><dt>Service areas</dt><dd>{{ $staff->serviceAreas->pluck('name')->join(', ') ?: 'Not assigned' }}</dd></div>
@if($staff->role === 'courier')<div><dt>Vehicle and plate</dt><dd>{{ $staff->vehicle_type }} · {{ $staff->vehicle_description }} · {{ $staff->plate_number }}</dd></div>@if($canViewHistory)<div><dt>License expiry</dt><dd>{{ $staff->license_expires_at?->format('d M Y') ?: 'Not recorded' }}</dd></div>@endif @endif</dl><p>{{ $staff->profile_bio }}</p></section>
@if($canViewHistory)<section class="dash-panel"><h2>Update history</h2>@forelse($revisions as $revision)<article class="web-section"><strong>{{ ucfirst($revision->status) }}</strong> · {{ $revision->created_at->format('d M Y H:i') }}<p>{{ $revision->reason }}</p><p class="web-muted">Reviewer: {{ $revision->reviewer?->first_name ?: 'Awaiting review' }} @if($revision->reviewed_at) · {{ $revision->reviewed_at->format('d M Y H:i') }}@endif</p><dl>@foreach($revision->proposed_values as $field => $value)@if($field !== 'profile_avatar_path')<div><dt>{{ \Illuminate\Support\Str::headline($field) }}</dt><dd>{{ $revision->previous_values[$field] ?? 'Not recorded' }} → {{ $value ?: 'Not specified' }}</dd></div>@else<div>Profile photo change</div>@endif @endforeach</dl></article>@empty<p>No profile updates submitted.</p>@endforelse{{ $revisions->links() }}</section>@endif
</main>@endsection
