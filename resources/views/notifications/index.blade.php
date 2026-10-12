@extends('layouts.app')

@if (auth()->user()?->role === 'buyer')
    @push('styles')
        @vite('resources/css/shared/dashboard.css')
        <link rel="stylesheet" href="{{ asset('css/buyer-account.css') }}?v={{ filemtime(public_path('css/buyer-account.css')) }}">
    @endpush
@endif

@section('content')
    @if (auth()->user()?->role === 'buyer')
        <main class="buyer-account-page">
            @include('components.buyer-account-sidebar', ['activeSection' => 'notifications'])

            <section class="buyer-account-content" aria-labelledby="buyer-notifications-title">
                <header class="buyer-account-header">
                    <div>
                        <h1 id="buyer-notifications-title">Notifications</h1>
                        <p>Order and account updates for your EZiCart account.</p>
                    </div>
                </header>

                @forelse ($notifications as $notification)
                    <article class="buyer-account-panel buyer-notification {{ $notification->read_at === null ? 'is-unread' : '' }}">
                        <div class="buyer-notification-copy">
                            <strong>{{ $notification->data['order_number'] ?? ($notification->data['product_name'] ?? 'EZiCart update') }}</strong>
                            <p>{{ $notification->data['message'] ?? 'You have a new update.' }}</p>
                            <small>{{ $notification->created_at->format('M d, Y h:i A') }}</small>
                        </div>
                        <div class="buyer-notification-actions">
                            @if (!empty($notification->data['url']))
                                <a class="dash-btn-sm dash-btn-outline" href="{{ $notification->data['url'] }}">View update</a>
                            @endif
                            @if ($notification->read_at === null)
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="dash-btn-sm dash-btn-primary">Mark read</button>
                                </form>
                            @else
                                <span class="buyer-notification-read">Read</span>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="buyer-account-panel buyer-notifications-empty">
                        <h2>You’re all caught up</h2>
                        <p>New order and account updates will appear here.</p>
                    </div>
                @endforelse

                <nav class="buyer-notifications-pagination" aria-label="Notification pages">
                    {{ $notifications->links() }}
                </nav>
            </section>
        </main>
    @else
        <main class="container u-extracted-d7ea292b2e">
            <h1>Notifications</h1>
            <p>Order and account updates for your EZiCart account.</p>

            @forelse ($notifications as $notification)
                <article class="dash-panel u-extracted-9655da144d">
                    <div class="u-extracted-e74ab7275b">
                        <div>
                            <strong>{{ $notification->data['order_number'] ?? ($notification->data['product_name'] ?? 'EZiCart update') }}</strong>
                            <p>{{ $notification->data['message'] ?? 'You have a new update.' }}</p>
                            <small>{{ $notification->created_at->format('M d, Y h:i A') }}</small>
                        </div>
                        <div class="u-extracted-f228196b24">
                            @if (!empty($notification->data['url']))
                                <a class="dash-btn-sm dash-btn-outline" href="{{ $notification->data['url'] }}">View update</a>
                            @endif
                            @if ($notification->read_at === null)
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="dash-btn-sm dash-btn-primary">Mark read</button>
                                </form>
                            @else
                                <span>Read</span>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <p>You have no notifications yet.</p>
            @endforelse

            {{ $notifications->links() }}
        </main>
    @endif
@endsection
