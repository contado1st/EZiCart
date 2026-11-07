@extends('layouts.app')

@push('styles')
    @vite('resources/css/logistics/logistics.css')
@endpush

@section('content')
    <div class="ops-shell" data-ops-shell>
        <main class="ops-main">
            @if (session('success'))
                <div class="ops-alert" role="status">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="ops-alert ops-alert--error" role="alert">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="ops-alert ops-alert--error" role="alert">{{ $errors->first() }}</div>
            @endif
            @yield('workspace')
        </main>
    </div>
@endsection
