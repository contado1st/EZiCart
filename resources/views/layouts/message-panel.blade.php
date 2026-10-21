<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Messages | EZiCart')</title>
    @vite('resources/css/shared/app.css')
    @vite('resources/js/app.js')
    @stack('styles')
</head>
<body @class(['communications-embedded', 'communications-embedded--logistics' => in_array(auth()->user()?->role, ['courier', 'sorting_center', 'logistics_admin'], true)])>
    @yield('content')
</body>
</html>
