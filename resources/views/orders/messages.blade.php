@extends('layouts.communications')
@section('title', 'Order messages | EZiCart')
@section('communications-title', 'Messages')
@section('communications-description', 'Coordinate purchases, preparation and deliveries in one place.')
@section('communications-content')
<div class="messaging-frame messaging-frame--thread">
    @include('components.conversation-list')
    <section class="conversation-thread" aria-labelledby="conversation-title">
        <header class="conversation-thread-heading"><div><a class="conversation-mobile-back" href="{{ route('chat.index') }}"><x-shop-icon name="arrowback" /> All conversations</a><h2 id="conversation-title">{{ $order->seller?->business_name ?: 'Order conversation' }}</h2><p>{{ $order->order_number }} · {{ $order->buyer?->first_name }} · Shared order conversation</p></div><a class="communications-text-link" href="{{ url()->full() }}">Refresh</a></header>
        <div class="conversation-order-context"><span class="conversation-order-icon"><x-shop-icon name="package1" /></span><div><strong>{{ $order->order_number }}</strong><span>{{ str_replace('_', ' ', $order->status) }}</span></div><a href="{{ $orderUrl }}">View order <x-shop-icon name="arrowback" /></a></div>
        <div class="conversation-history" data-conversation-history tabindex="0" aria-label="Message history">
            @forelse($messages ?? [] as $message)
                @php($ownMessage = $message->sender_id === auth()->id())
                <article @class(['conversation-message', 'is-own' => $ownMessage])>
                    <div class="conversation-message-meta"><strong>{{ $ownMessage ? 'You' : trim(($message->sender?->first_name ?? 'Former').' '.($message->sender?->last_name ?? 'participant')) }}</strong>@if(!$ownMessage && $message->sender)<span>{{ str_replace('_', ' ', $message->sender->role) }}</span>@endif</div>
                    <p class="conversation-bubble">{{ $message->body }}</p><time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M d, Y · h:i A') }}</time>
                </article>
            @empty<div class="conversation-first-message"><x-shop-icon name="chat" /><h3>No messages yet</h3><p>Start with an order question or delivery update. Messages are visible to this order’s authorized participants.</p></div>@endforelse
            @if($messages?->hasPages())<nav class="conversation-history-pages" aria-label="Message history pages">{{ $messages->links('pagination::simple-tailwind') }}</nav>@endif
        </div>
        <form class="conversation-composer" method="POST" action="{{ route($messagePrefix.'.orders.messages.store', $order) }}" data-message-composer>
            @csrf<label for="message-body">Message</label><textarea id="message-body" name="body" maxlength="2000" rows="3" required placeholder="Write a message about this order…" data-message-input>{{ old('body') }}</textarea>
            <div class="conversation-composer-footer"><span><span data-message-count>0</span>/2,000 <span class="composer-shortcut">· Ctrl+Enter to send</span></span><button type="submit" data-message-send>Send message <x-shop-icon name="arrowback" /></button></div>
        </form>
    </section>
</div>
@endsection
