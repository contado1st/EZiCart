@props(['name'])

@php
    $icon = config('icons.'.$name);
    abort_unless(is_string($icon) && preg_match('/\A[a-z0-9-]+\z/', $icon), 404);
@endphp

<span {{ $attributes->class(['shop-icon', 'tabler-icon']) }} data-tabler-icon="{{ $icon }}" aria-hidden="true">{!! file_get_contents(resource_path('icons/tabler/'.$icon.'.svg')) !!}</span>
