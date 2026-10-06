@extends('layouts.app')

@section('content')
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
@endsection
