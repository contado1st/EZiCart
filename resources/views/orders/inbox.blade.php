@extends('layouts.communications')
@section('title', 'Messages | EZiCart')
@section('communications-title', 'Messages')
@section('communications-description', 'Coordinate purchases, preparation and deliveries in one place.')
@section('communications-content')
<div class="messaging-frame messaging-frame--inbox">
    @include('components.conversation-list')
    <section class="conversation-empty" aria-label="Select a conversation"><span class="communications-empty-icon"><x-shop-icon name="chat" /></span><h2>Your conversations, all together</h2><p>Select an order on the left to message its buyer, seller, assigned riders and involved logistics staff.</p><small>Only participants assigned to an order can join its conversation. Authorized administrators can assist.</small></section>
</div>
@endsection
