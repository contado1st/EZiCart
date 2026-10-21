<aside class="conversation-list" aria-label="Private conversations">
    <header class="conversation-list-heading"><h2>All conversations</h2><a href="{{ route('chat.contacts', ['panel' => request()->boolean('panel') ? 1 : null]) }}">New chat</a></header>
    <form action="{{ route('chat.index') }}" method="GET" class="conversation-search" role="search">
        @if(request()->boolean('panel'))<input type="hidden" name="panel" value="1">@endif
        <label class="communications-sr-only" for="conversation-search">Search conversations by name or shop</label><input id="conversation-search" name="q" type="search" value="{{ request('q') }}" maxlength="100" placeholder="Search name or shop"><button type="submit" aria-label="Search conversations"><x-shop-icon name="search" /></button>
    </form>
    <div class="conversation-list-items">
    @forelse($threads as $thread)
        @php
            $person = $thread->otherUser(auth()->user());
            $partnerName = $person->business_name ?: trim($person->first_name.' '.$person->last_name);
            $latest = $thread->latestMessage;
            $isSelected = isset($conversation) && $conversation->id === $thread->id;
        @endphp
        <a href="{{ route('chat.show', [$thread, 'panel' => request()->boolean('panel') ? 1 : null]) }}" @class(['conversation-item', 'is-selected' => $isSelected]) @if($isSelected) aria-current="page" @endif>
            <span class="conversation-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($partnerName, 0, 1)) }}</span>
            <span class="conversation-copy"><span class="conversation-name">{{ $partnerName }}</span><span class="conversation-order">{{ str_replace('_', ' ', $person->role) }}</span><span class="conversation-preview">{{ $latest ? (auth()->id() === $latest->sender_id ? 'You: ' : '').$latest->body : 'Start a private conversation' }}</span></span>
            @if($thread->unread_count)<span class="conversation-unread" aria-label="{{ $thread->unread_count }} unread messages">{{ $thread->unread_count > 99 ? '99+' : $thread->unread_count }}</span>@elseif($latest)<time datetime="{{ $latest->created_at->toIso8601String() }}">{{ $latest->created_at->format('M d') }}</time>@endif
        </a>
    @empty<div class="conversation-list-empty"><x-shop-icon name="chat" /><p>{{ request('q') ? 'No conversations match your search.' : 'Your private conversations will appear here.' }}</p></div>@endforelse
    </div>
    @if($threads->hasPages())<nav class="conversation-list-pagination" aria-label="Conversation pages">{{ $threads->onEachSide(0)->links('pagination::simple-tailwind') }}</nav>@endif
</aside>
