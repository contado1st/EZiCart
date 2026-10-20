<aside class="conversation-list" aria-label="Order conversations">
    <header class="conversation-list-heading"><h2>All conversations</h2><span>{{ number_format($threads->total()) }}</span></header>
    <form action="{{ route('chat.index') }}" method="GET" class="conversation-search" role="search"><label class="communications-sr-only" for="conversation-search">Search conversations by order, shop or buyer</label><input id="conversation-search" name="q" type="search" value="{{ request('q') }}" maxlength="100" placeholder="Search order or shop"><button type="submit" aria-label="Search conversations"><x-shop-icon name="search" /></button></form>
    <div class="conversation-list-items">
    @forelse($threads as $thread)
        @php
            $partner = auth()->user()->role === 'seller' ? trim(($thread->buyer?->first_name ?? '').' '.($thread->buyer?->last_name ?? '')) : ($thread->seller?->business_name ?: $thread->seller?->first_name);
            $partner = $partner ?: 'Order conversation';
            $latest = $thread->conversation?->latestMessage;
            $isSelected = isset($order) && $order->id === $thread->id;
        @endphp
        <a href="{{ route($messagePrefix.'.orders.messages.show', [$thread, 'q' => request('q')]) }}" @class(['conversation-item', 'is-selected' => $isSelected]) @if($isSelected) aria-current="page" @endif>
            <span class="conversation-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($partner, 0, 1)) }}</span>
            <span class="conversation-copy"><span class="conversation-name">{{ $partner }}</span><span class="conversation-order">{{ $thread->order_number }}</span><span class="conversation-preview">{{ $latest ? (auth()->id() === $latest->sender_id ? 'You: ' : '').$latest->body : 'Start an order conversation' }}</span></span>
            @if($latest)<time datetime="{{ $latest->created_at->toIso8601String() }}">{{ $latest->created_at->format('M d') }}</time>@endif
        </a>
    @empty<div class="conversation-list-empty"><x-shop-icon name="chat" /><p>{{ request('q') ? 'No conversations match your search.' : 'Orders available for messaging will appear here.' }}</p>@if(request('q'))<a href="{{ route('chat.index') }}">Clear search</a>@endif</div>@endforelse
    </div>
    @if($threads->hasPages())<nav class="conversation-list-pagination" aria-label="Conversation pages">{{ $threads->onEachSide(0)->links('pagination::simple-tailwind') }}</nav>@endif
</aside>
