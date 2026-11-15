@props(['staff', 'qrSvg'])
<article class="staff-id-card" aria-label="Staff identification" data-staff-id-card>
    <div class="staff-id-identity">
        @if($staff->profile_avatar_path)
            <img class="staff-id-photo" src="{{ route('staff.avatar', $staff) }}" alt="{{ $staff->first_name }} {{ $staff->last_name }}" width="112" height="112">
        @else
            <span class="staff-id-photo staff-id-initials" aria-hidden="true">{{ mb_substr($staff->first_name, 0, 1) }}{{ mb_substr($staff->last_name, 0, 1) }}</span>
        @endif
        <div>
            <p class="staff-id-brand">EZiCart · Staff identification</p>
            <h2>{{ $staff->first_name }} {{ $staff->last_name }}</h2>
            <p>{{ str_replace('_', ' ', ucfirst($staff->role)) }}</p>
            <p class="staff-id-reference">EZI-{{ str_pad((string) $staff->id, 8, '0', STR_PAD_LEFT) }}</p>
            <p>Status: {{ ucfirst($staff->status) }}</p>
        </div>
    </div>
    <dl class="staff-id-details">
        <div><dt>Contact</dt><dd>{{ $staff->contact_no ?: 'Not recorded' }}</dd></div>
        <div><dt>Operating area</dt><dd>{{ collect([$staff->municipality, $staff->province])->filter()->join(', ') ?: 'Not recorded' }}</dd></div>
        <div><dt>Assigned hub</dt><dd>{{ $staff->logisticsOwner?->business_name ?: $staff->logisticsOwner?->first_name ?: ($staff->role === 'sorting_center' ? ($staff->business_name ?: 'This sorting center') : 'Not assigned') }}</dd></div>
        @if($staff->role === 'courier')
            <div><dt>Vehicle / plate</dt><dd>{{ $staff->vehicle_type ?: 'Not recorded' }} · {{ $staff->plate_number ?: 'Not recorded' }}</dd></div>
            <div><dt>License expires</dt><dd>{{ $staff->license_expires_at?->format('d M Y') ?: 'Not recorded' }}</dd></div>
        @elseif($staff->business_name)
            <div><dt>Business</dt><dd>{{ $staff->business_name }}</dd></div>
        @endif
    </dl>
    <figure class="staff-id-qr">
        {!! $qrSvg !!}
        <figcaption>Profile QR · authorized viewing only</figcaption>
        <p>This identifies the profile. Rider handovers use the existing rider badge.</p>
    </figure>
</article>
