@props(['address'])
<div class="buyer-address-summary">
    <strong>{{ $address->recipient_name }}</strong>
    <span>{{ $address->recipient_contact }}</span>
    <address>
        {{ trim(($address->house_number ? $address->house_number.' ' : '').($address->street_address ?? '')) }}<br>
        {{ implode(', ', array_filter([$address->barangay, $address->municipality, $address->province, $address->region])) }}
        @if ($address->postal_code)<span>{{ $address->postal_code }}</span>@endif
    </address>
</div>
