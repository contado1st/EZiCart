@props(['rider'])
@if($rider->license_expires_at && $rider->license_expires_at->startOfDay()->lt(today()))
    <span class="parcel-weight-label is-missing">License expired: assignment and dispatch blocked. Request a reviewed renewal.</span>
@elseif($rider->license_expires_at && $rider->license_expires_at->lte(today()->addDays(30)))
    <span class="parcel-weight-label">License expires {{ $rider->license_expires_at->format('d M Y') }}. Request renewal before expiry.</span>
@elseif(! $rider->license_expires_at)
    <span class="parcel-weight-label">Legacy license expiry not recorded. Submit verified renewal details.</span>
@endif
<span @class(['parcel-weight-label', 'is-missing' => !$rider->vehicle_capacity_verified_at || !$rider->vehicle_capacity_grams])>{{ $rider->vehicle_type ?: 'Vehicle' }} · Cargo limit: {{ $rider->vehicle_capacity_verified_at ? \App\Services\ParcelLoadService::kg($rider->vehicle_capacity_grams) : 'Not verified — dispatch blocked' }}</span>
