@extends('layouts.communications')
@section('title', 'Notifications | EZiCart')
@section('communications-title', 'Notifications')
@section('communications-description', 'Order updates, messages and account activity, organized for you.')
@section('communications-content')
<div class="notification-frame">
    <aside class="notification-sidebar" aria-label="Notification categories"><h2>Your updates</h2><a href="{{ route('notifications.index') }}" @class(['is-active' => !request('category') || request('category') === 'all'])><x-shop-icon name="bell" /> All updates <span>{{ $totalCount }}</span></a>
        @foreach(['orders' => ['package1', 'Order updates'], 'messages' => ['chat', 'Messages'], 'stock' => ['bell', 'Stock alerts'], 'account' => ['account', 'Account activity']] as $category => [$icon, $label])<a href="{{ route('notifications.index', ['category' => $category, 'filter' => request('filter')]) }}" @class(['is-active' => request('category') === $category]) @if(request('category') === $category) aria-current="page" @endif><x-shop-icon name="{{ $icon }}" /> {{ $label }}</a>@endforeach
        <p>Private updates for your account. Open an update to view its details and mark it read.</p>
    </aside>
    <section class="notification-feed" aria-labelledby="notification-feed-title">
        <header class="notification-feed-heading"><div><h2 id="notification-feed-title">{{ request('category') && request('category') !== 'all' ? ucfirst(request('category')).' updates' : 'All updates' }}</h2><p>{{ $unreadCount }} unread {{ \Illuminate\Support\Str::plural('notification', $unreadCount) }}</p></div><form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="communications-text-link" type="submit" @disabled(!$unreadCount)>Mark all as read</button></form></header>
        <nav class="notification-filters" aria-label="Notification read status"><a href="{{ route('notifications.index', ['category' => request('category'), 'filter' => 'all']) }}" @class(['is-active' => request('filter') !== 'unread']) @if(request('filter') !== 'unread') aria-current="page" @endif>All</a><a href="{{ route('notifications.index', ['category' => request('category'), 'filter' => 'unread']) }}" @class(['is-active' => request('filter') === 'unread']) @if(request('filter') === 'unread') aria-current="page" @endif>Unread</a></nav>
        <div class="notification-items">
        @forelse($notifications as $notification)
            @php
                $notificationIcon = match($notification->type) {
                    \App\Notifications\OrderMessageNotification::class, \App\Notifications\DirectMessageNotification::class => 'chat',
                    \App\Notifications\StockAvailabilityNotification::class => 'bell',
                    \App\Notifications\OrderStatusNotification::class, \App\Notifications\OrderWorkflowNotification::class => 'package1',
                    default => 'account',
                };
            @endphp
            <article @class(['notification-item', 'is-unread' => !$notification->read_at])><span class="notification-item-icon"><x-shop-icon name="{{ $notificationIcon }}" /></span><div class="notification-item-copy"><div class="notification-item-title"><h3>{{ $notification->data['order_number'] ?? $notification->data['product_name'] ?? 'EZiCart update' }}</h3>@if(!$notification->read_at)<span class="notification-unread-dot" aria-label="Unread"></span>@endif</div><p>{{ $notification->data['message'] ?? 'You have a new update.' }}</p><time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('M d, Y · h:i A') }}</time></div><div class="notification-item-actions">@if(!empty($notification->data['url']))<form method="POST" action="{{ route('notifications.open', $notification->id) }}">@csrf<button class="notification-open" type="submit">View update</button></form>@endif @if(!$notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf<button class="communications-text-link" type="submit">Mark read</button></form>@else<span class="notification-read-label">Read</span>@endif</div></article>
        @empty<div class="conversation-empty notification-empty"><span class="communications-empty-icon"><x-shop-icon name="bell" /></span><h2>{{ request('filter') === 'unread' ? 'You’re all caught up' : 'No updates here yet' }}</h2><p>New order, message and account updates will appear here.</p></div>@endforelse
        </div>
        @if($notifications->hasPages())<nav class="notification-pagination" aria-label="Notification pages">{{ $notifications->links() }}</nav>@endif
    </section>
</div>
@endsection
