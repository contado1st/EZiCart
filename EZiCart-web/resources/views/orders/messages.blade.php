@extends('layouts.communications')
@section('title', 'Private messages | EZiCart')
@section('communications-title', 'Messages')
@section('communications-description', 'Private conversations with your contacts.')
@section('communications-content')
<div class="messaging-frame messaging-frame--thread" data-message-retractions="{{ route('chat.retractions', $conversation) }}" data-message-updates="{{ route('chat.updates', [$conversation, 'panel' => request()->boolean('panel') ? 1 : null]) }}">
    @include('components.conversation-list')
    <section class="conversation-thread" aria-labelledby="conversation-title">
        <header class="conversation-thread-heading"><div><a class="conversation-mobile-back" href="{{ route('chat.index', ['panel' => request()->boolean('panel') ? 1 : null]) }}">All conversations</a><h2 id="conversation-title">{{ $partner->business_name ?: trim($partner->first_name.' '.$partner->last_name) }}</h2><p>Private conversation &middot; {{ str_replace('_', ' ', $partner->role) }}</p><x-user-activity :user="$partner" /></div><a class="communications-text-link" href="{{ url()->full() }}">Refresh</a></header>
        @if($order)<div class="conversation-order-context"><div><strong>{{ $order->order_number }}</strong><span>{{ str_replace('_', ' ', $order->operational_status) }}</span></div><a href="{{ $orderUrl }}" @if(request()->boolean('panel')) target="_top" @endif>View order</a></div>@endif
        @if($productContext ?? null)<div class="conversation-order-context"><div><strong>Enquiry about {{ $productContext->name }}</strong><span>{{ $productContext->display_price_label }}</span></div><p>The product card is shared when you send your question, not when you open this conversation.</p></div>@endif
        <div class="conversation-history" data-conversation-history tabindex="0" aria-label="Message history">
            @forelse($messages as $message)
                @php($ownMessage = $message->sender_id === auth()->id())
                <article @class(['conversation-message', 'is-own' => $ownMessage]) data-direct-message="{{ $message->id }}" data-own-message="{{ $ownMessage ? '1' : '0' }}">
                    <div class="conversation-message-meta"><strong>{{ $ownMessage ? 'You' : trim($partner->first_name.' '.$partner->last_name) }}</strong></div>
                    <div class="conversation-message-content" data-message-content>
                        @include('orders.partials.message-content')
                    </div>
                    <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M d, Y h:i A') }}</time>
                </article>
            @empty<div class="conversation-first-message"><x-shop-icon name="chat" /><h3>No messages yet</h3><p>Only you and {{ $partner->first_name }} can access this conversation.</p></div>@endforelse
            @if($messages->hasPages())<nav class="conversation-history-pages" aria-label="Message history pages">{{ $messages->links('pagination::simple-tailwind') }}</nav>@endif
        </div>
        @if($canSend)
        <form class="conversation-composer" method="POST" action="{{ route('chat.store', $conversation) }}" enctype="multipart/form-data" data-message-composer>
            @csrf @if(request()->boolean('panel'))<input type="hidden" name="panel" value="1">@endif
            @if($order)<input type="hidden" name="order_id" value="{{ $order->id }}">@endif
            @if(($productContext ?? null) && auth()->user()->role === 'buyer')<input type="hidden" name="product_id" value="{{ $productContext->id }}">@endif
            <label for="message-body">Message</label><textarea id="message-body" name="body" maxlength="2000" rows="3" placeholder="Write a private message..." data-message-input>{{ old('body') }}</textarea>
            <label for="message-files">Attach files (up to 3; {{ number_format(\App\Services\ChatAttachmentService::maxKilobytes() / 1024, 1) }} MB each)</label><input id="message-files" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt" data-message-files data-max-bytes="{{ \App\Services\ChatAttachmentService::maxKilobytes() * 1024 }}"><div class="web-attachment-list" data-message-file-preview></div><p class="web-upload-feedback" data-message-upload-feedback role="status">Images and business documents are shared only with this conversation.</p>
            <div class="conversation-composer-footer"><span><span data-message-count>0</span>/2,000 <span class="composer-shortcut">&middot; Ctrl+Enter to send</span></span><button type="submit" data-message-send>Send message <x-shop-icon name="arrowback" /></button></div>
        </form>
        @else<p class="conversation-list-empty">Messaging is unavailable because this contact is no longer active or related to your account. Your history remains available.</p>@endif
    </section>
</div>
@endsection
@push('styles')
    <script src="{{ asset('js/message-retractions.js') }}?v={{ filemtime(public_path('js/message-retractions.js')) }}" defer></script>
@endpush
