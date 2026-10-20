@extends('layouts.app')
@section('title', 'Chat | EZiCart')
@section('content')
@include('components.feature-feedback')
<div class="feature-page"><header><div><h1>Chat</h1><p>Contact the {{ auth()->user()->role === 'buyer' ? 'seller' : 'buyer' }} for an order. Conversations stay attached to their parcel.</p></div></header>
@forelse ($orders as $order)<article class="feature-panel"><h2>{{ $order->order_number }}</h2><p>{{ auth()->user()->role === 'buyer' ? $order->seller?->business_name : $order->buyer?->first_name }} · {{ str_replace('_', ' ', $order->status) }}</p><a class="feature-button" href="{{ route(auth()->user()->role.'.orders.messages.show', $order) }}">Open conversation</a></article>@empty<p>Your order conversations will appear here after checkout.</p>@endforelse{{ $orders->links() }}</div>
@endsection
