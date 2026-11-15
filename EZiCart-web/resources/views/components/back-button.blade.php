@props([
    'fallback' => null,
    'label' => 'Back',
])

@php
    $previousUrl = url()->previous();
    $currentUrl = url()->current();
    $targetUrl = ($previousUrl && $previousUrl !== $currentUrl)
        ? $previousUrl
        : ($fallback ? (\Illuminate\Support\Facades\Route::has($fallback) ? route($fallback) : $fallback) : route('home'));
@endphp

<a href="{{ $targetUrl }}" {{ $attributes->class(['workspace-back-button', 'btn-back']) }} aria-label="{{ $label }}" title="{{ $label }}">
    <x-shop-icon name="arrowback" />
    <span>{{ $label }}</span>
</a>
