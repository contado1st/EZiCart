<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'EZiCart - A Better Marketplace')</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Base Application Stylesheet -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <!-- Platform Bulletins & Banners Stylesheet -->
    <link rel="stylesheet" href="{{ asset('css/platform-controls.css') }}">
    
    <!-- Dynamic Page Stylesheets -->
    @stack('styles')
</head>
<body>

    <!-- Header Navigation Bar -->
    @unless(View::hasSection('hide_header'))
        @include('partials.header')
    @endunless

    <!-- Global Platform Announcements -->
    @php
        $activeAnnouncements = \App\Models\Announcement::activeForUser(auth()->user())->latest()->take(3)->get();
    @endphp

    @if($activeAnnouncements->isNotEmpty())
        <div class="ez-container-fluid" style="margin-top: 1rem;">
            @foreach($activeAnnouncements as $announcement)
                <div class="announcement-banner banner-type-{{ $announcement->type }}">
                    <span style="font-size: 1.15rem; line-height: 1;">
                        @if($announcement->type === 'urgent') 🚨
                        @elseif($announcement->type === 'warning') ⚠️
                        @elseif($announcement->type === 'maintenance') 🛠️
                        @else 📢
                        @endif
                    </span>
                    <div>
                        <strong>{{ $announcement->title }}:</strong> {{ $announcement->content }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Main Dynamic Content Container -->
    <main class="ez-main-wrapper">
        @yield('content')
    </main>

    <!-- Footer -->
    @unless(View::hasSection('hide_footer'))
        @include('partials.footer')
    @endunless

</body>
</html>