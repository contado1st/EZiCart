<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ request()->getBasePath() }}/js/tab-sessions.js?v={{ filemtime(public_path('js/tab-sessions.js')) }}" data-tab-login-url="{{ route('login') }}" data-tab-authenticated="{{ auth()->check() ? '1' : '0' }}" defer></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Messages | EZiCart')</title>
    @vite('resources/css/shared/app.css')
    @vite('resources/js/app.js')
    <link rel="stylesheet" href="{{ asset('css/workspace-navigation.css') }}?v={{ filemtime(public_path('css/workspace-navigation.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/loading-skeleton.css') }}?v={{ filemtime(public_path('css/loading-skeleton.css')) }}">
    <script src="{{ asset('js/page-loading.js') }}?v={{ filemtime(public_path('js/page-loading.js')) }}" defer></script>
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/web-operations.css') }}?v={{ filemtime(public_path('css/web-operations.css')) }}">
</head>
<body @class(['communications-embedded', 'communications-embedded--logistics' => in_array(auth()->user()?->role, ['courier', 'sorting_center', 'logistics_admin'], true)])>
    <div data-page-content>@yield('content')</div>
    <x-page-loading />
</body>
</html>
