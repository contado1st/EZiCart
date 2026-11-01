@extends('layouts.communications')
@section('title', 'Messages | EZiCart')
@section('communications-title', 'Messages')
@section('communications-description', 'Private conversations with your contacts.')
@section('communications-content')
<div class="messaging-frame messaging-frame--inbox">
    @include('components.conversation-list')
    <section class="conversation-empty" aria-label="Select a conversation"><span class="communications-empty-icon"><x-shop-icon name="chat" /></span><h2>Your private conversations</h2><p>Select a conversation or start a new chat with a related buyer, seller, delivery contact or administrator.</p><a class="communications-text-link" href="{{ route('chat.contacts', ['panel' => request()->boolean('panel') ? 1 : null]) }}">Start a new chat</a><small>Only you and the other person can access your conversation.</small></section>
</div>
@endsection
