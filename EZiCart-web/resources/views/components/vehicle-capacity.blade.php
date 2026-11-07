@props(['rider'])
<span @class(['parcel-weight-label', 'is-missing' => !$rider->vehicle_capacity_verified_at || !$rider->vehicle_capacity_grams])>{{ $rider->vehicle_type ?: 'Vehicle' }} · Cargo limit: {{ $rider->vehicle_capacity_verified_at ? \App\Services\ParcelLoadService::kg($rider->vehicle_capacity_grams) : 'Not verified — dispatch blocked' }}</span>
