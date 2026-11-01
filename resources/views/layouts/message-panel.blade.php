<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Messages | EZiCart')</title>
    @vite('resources/css/shared/app.css')
    @vite('resources/js/app.js')
    <script src="{{ asset('js/tab-sessions.js') }}?v={{ filemtime(public_path('js/tab-sessions.js')) }}" defer></script>
    <link rel="stylesheet" href="{{ asset('css/loading-skeleton.css') }}?v={{ filemtime(public_path('css/loading-skeleton.css')) }}">
    <script src="{{ asset('js/page-loading.js') }}?v={{ filemtime(public_path('js/page-loading.js')) }}" defer></script>
    @stack('styles')
</head>
<body @class(['communications-embedded', 'communications-embedded--logistics' => in_array(auth()->user()?->role, ['courier', 'sorting_center', 'logistics_admin'], true)])>
    <div data-page-content>@yield('content')</div>
    <x-page-loading />
</body>
</html>
