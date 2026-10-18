@props(['name'])

@php
    $availableShopicons = [
        'account', 'arrowback', 'bell', 'cart1', 'cart2', 'checklist', 'close', 'delivery1',
        'diagram', 'exclamationtriangle', 'heart', 'info', 'invoice', 'lightbulb', 'location',
        'lock', 'megaphone', 'money', 'note', 'package1', 'phone', 'print', 'search', 'settings',
        'shield', 'smileypositive', 'star', 'voucher', 'warning',
    ];

    abort_unless(in_array($name, $availableShopicons, true), 404);
@endphp

<shopicon-regular-{{ $name }} {{ $attributes->class(['shop-icon']) }} aria-hidden="true"></shopicon-regular-{{ $name }}>
