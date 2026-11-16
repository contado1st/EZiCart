@extends('layouts.courier')
@section('workspace')
    <header class="ops-heading">
        <div><h1>My rider profile</h1><p>Your current official details, vehicle and submitted-document availability.</p></div>
        <div class="courier-actions"><a class="ops-btn ops-btn--primary" href="{{ route('account.profile.edit') }}">Request profile changes</a><a class="ops-btn" href="{{ route('courier.work-availability.index') }}">Work availability</a></div>
    </header>
    @if($pendingProfile)
        <section class="ops-panel" role="status"><h2>Profile update awaiting logistics admin review</h2><p>Submitted {{ $pendingProfile->created_at->format('d M Y H:i') }}. The official values below remain in effect until approval.</p><a class="ops-btn" href="{{ route('account.profile.edit') }}#profile-requests">View proposed changes and review history</a></section>
    @endif
    <x-workspace-tabs default-tab="official-profile" :tabs="[['id' => 'official-profile', 'label' => 'Official profile'], ['id' => 'vehicle-status', 'label' => 'Vehicle & capacity'], ['id' => 'private-documents', 'label' => 'Private documents']]" />
    <section class="ops-panel" id="official-profile">
        <h2>Official profile</h2>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="ops-muted">Name</dt><dd>{{ trim($courier->first_name.' '.$courier->middle_initial.' '.$courier->last_name) }}</dd></div>
            <div><dt class="ops-muted">Contact</dt><dd>{{ $courier->contact_no ?: 'Not recorded' }}</dd></div>
            <div><dt class="ops-muted">Email</dt><dd class="break-all">{{ $courier->email }}</dd></div>
            <div><dt class="ops-muted">Account state</dt><dd>{{ ucfirst($courier->status) }}</dd></div>
            <div><dt class="ops-muted">Assigned logistics hub</dt><dd>{{ $courier->logisticsOwner?->business_name ?: ($courier->logisticsOwner?->first_name ?: 'No owning hub recorded') }}</dd></div>
            <div><dt class="ops-muted">Assigned duty</dt><dd>{{ $courier->riderDutyLabel() }}</dd></div>
            <div class="sm:col-span-2"><dt class="ops-muted">Address</dt><dd>{{ collect([$courier->street_address, $courier->barangay, $courier->municipality, $courier->province])->filter()->implode(', ') ?: 'Not recorded' }}</dd></div>
        </dl>
        <h3>Assigned service areas</h3>
        <ul>@forelse($courier->serviceAreas as $area)<li>{{ $area->name }}{{ $area->is_active && $area->pivot->is_active ? '' : ' (inactive)' }}</li>@empty<li>No service areas assigned. Ask logistics before accepting work.</li>@endforelse</ul>
    </section>
    <section class="ops-panel" id="vehicle-status">
        <h2>Vehicle and operational verification</h2>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="ops-muted">Vehicle</dt><dd>{{ $courier->vehicle_type ?: 'Not recorded' }}{{ $courier->vehicle_description ? ' · '.$courier->vehicle_description : '' }}</dd></div>
            <div><dt class="ops-muted">Plate number</dt><dd>{{ $courier->plate_number ?: 'Not recorded' }}</dd></div>
            <div><dt class="ops-muted">Licence number</dt><dd>{{ $courier->license_number ?: 'Not recorded' }}</dd></div>
            <div><dt class="ops-muted">Licence expiry</dt><dd>{{ $courier->license_expires_at?->format('d M Y') ?? 'Not recorded — ask logistics to review' }}@if($courier->license_expires_at?->lt(today())) <strong>Expired</strong>@endif</dd></div>
            <div><dt class="ops-muted">Maximum cargo capacity</dt><dd>{{ $courier->vehicle_capacity_verified_at && $courier->vehicle_capacity_grams ? \App\Services\ParcelLoadService::kg($courier->vehicle_capacity_grams) : 'Not verified — new verification required' }}</dd></div>
            <div><dt class="ops-muted">Capacity verification</dt><dd>{{ $courier->vehicle_capacity_verified_at?->format('d M Y H:i') ?? 'No verification recorded' }}</dd></div>
        </dl>
        <p class="ops-muted">Account approval, documents on file, current licence, manual work availability and verified cargo capacity are separate checks. Vehicle changes require admin review and may require capacity verification again.</p>
    </section>
    <section class="ops-panel" id="private-documents">
        <h2>Private submitted documents</h2><p>“File available” means the document exists in private storage; it does not independently certify that it is approved or current.</p>
        <div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>Document</th><th>File state</th><th>Action</th></tr></thead><tbody>
        @foreach($submittedDocuments as $document)
            <tr><td>{{ $document['label'] }}</td><td>{{ $document['available'] ? 'File available' : ($document['submitted'] ? 'Recorded file is unavailable' : 'Not submitted') }}</td><td>
                @if($document['available'])<div class="courier-actions"><a class="ops-btn" href="{{ route('account.documents.show', ['type' => $document['type'], 'preview' => 1]) }}" target="_blank" rel="noopener">View document</a><a class="ops-btn" href="{{ route('account.documents.show', $document['type']) }}">Download</a></div>@else<span class="ops-muted">Contact logistics administration</span>@endif
            </td></tr>
        @endforeach
        </tbody></table></div>
    </section>
@endsection
