@extends('layouts.communications')
@section('title', 'New private chat | EZiCart')
@section('communications-title', 'New private chat')
@section('communications-description', 'Choose one person to message.')
@section('communications-content')
<section class="conversation-contacts" aria-labelledby="contacts-title">
    <header class="conversation-thread-heading"><div><h2 id="contacts-title">Choose a contact</h2><p>{{ $order ? 'Order '.$order->order_number.' — choose one recipient.' : 'Related contacts and relevant administrators.' }}</p></div><a href="{{ route('chat.index', ['panel' => request()->boolean('panel') ? 1 : null]) }}">Back to messages</a></header>
    @unless($order)
    <form class="conversation-search" method="GET" action="{{ route('chat.contacts') }}" role="search">
        @if(request()->boolean('panel'))<input type="hidden" name="panel" value="1">@endif
        <label class="communications-sr-only" for="contact-search">Search contacts by name or shop</label><input id="contact-search" name="q" type="search" maxlength="100" value="{{ request('q') }}" placeholder="Search name or shop"><button type="submit" aria-label="Search contacts"><x-shop-icon name="search" /></button>
    </form>
    @endunless
    <div class="conversation-list-items">
    @forelse($contacts as $contact)
        <form class="conversation-contact" action="{{ route('chat.start', $contact) }}" method="POST">
            @csrf
            @if(request()->boolean('panel'))<input type="hidden" name="panel" value="1">@endif
            @if($order)<input type="hidden" name="order_id" value="{{ $order->id }}">@endif
            <div><strong>{{ $contact->business_name ?: trim($contact->first_name.' '.$contact->last_name) }}</strong><small>{{ str_replace('_', ' ', $contact->role) }}</small></div>
            <button type="submit">Message<span class="communications-sr-only"> {{ $contact->first_name }} {{ $contact->last_name }}</span></button>
        </form>
    @empty<div class="conversation-list-empty"><p>No available contacts{{ request('q') ? ' match your search' : ' yet' }}.</p></div>@endforelse
    </div>
    {{ $contacts->links('pagination::simple-tailwind') }}
</section>
@endsection
