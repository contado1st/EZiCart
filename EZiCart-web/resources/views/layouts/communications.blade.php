@extends(request()->boolean('panel') && !request()->routeIs('notifications.*') ? 'layouts.message-panel' : 'layouts.app')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/communications.css') }}?v={{ filemtime(public_path('css/communications.css')) }}">
    <script src="{{ asset('js/communications.js') }}?v={{ filemtime(public_path('js/communications.js')) }}" defer></script>
@endpush
@section('content')
<section class="communications" aria-labelledby="communications-title">
    <header class="communications-heading"><div><h1 id="communications-title">@yield('communications-title', 'Messages')</h1><p>@yield('communications-description', 'Keep your order conversations together.')</p></div><span class="communications-brand"><x-shop-icon name="chat" /> EZiCart Connect</span></header>
    <nav class="communications-tabs" aria-label="Communication center">
        <a href="{{ route('chat.index') }}" @class(['is-active' => !request()->routeIs('notifications.*')]) @if(!request()->routeIs('notifications.*')) aria-current="page" @endif><x-shop-icon name="chat" /> Messages</a>
        <a href="{{ route('notifications.index') }}" @class(['is-active' => request()->routeIs('notifications.*')]) @if(request()->routeIs('notifications.*')) aria-current="page" @endif><x-shop-icon name="bell" /> Notifications</a>
    </nav>
    @if(session('success'))<p class="communications-notice" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="communications-notice communications-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('communications-content')
</section>
@endsection
