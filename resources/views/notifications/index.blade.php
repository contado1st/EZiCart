@extends('layouts.app')

@section('content')
    <main class="container" style="max-width: 900px; padding-block: 2rem;">
        <h1>Notifications</h1>
        <p>Order and account updates for your EZiCart account.</p>

        @forelse ($notifications as $notification)
            <article class="dash-panel" style="margin-block: 1rem;">
                <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start;">
                    <div>
                        <strong>{{ $notification->data['order_number'] ?? $notification->data['product_name'] ?? 'EZiCart update' }}</strong>
                        <p>{{ $notification->data['message'] ?? 'You have a new update.' }}</p>
                        <small>{{ $notification->created_at->format('M d, Y h:i A') }}</small>
                    </div>
                    <div style="display:flex; gap:.5rem; align-items:center;">
                        @if (! empty($notification->data['url']))
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
