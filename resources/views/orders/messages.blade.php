@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
@endpush

@section('content')
    <main class="container u-extracted-d7ea292b2e">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Messages for {{ $order->order_number }}</h1>
                <p class="dash-subtitle">Private coordination thread for the buyer, seller, assigned riders, and Logistics
                    staff involved with this order.</p>
            </div>
            @php
                [$backRoute, $messageRoute] = match (auth()->user()->role) {
                    'buyer' => ['buyer.orders.show', 'buyer.orders.messages.store'],
                    'seller' => ['seller.orders.show', 'seller.orders.messages.store'],
                    'courier' => ['courier.orders.show', 'courier.orders.messages.store'],
                    'sorting_center' => ['logistics.tracking', 'logistics.orders.messages.store'],
                    'admin' => ['admin.dashboard', 'admin.orders.messages.store'],
                    default => ['notifications.index', 'notifications.index'],
                };
            @endphp
            @php
                $backUrl = match (auth()->user()->role) {
                    'sorting_center' => route($backRoute, ['search' => $order->order_number]),
                    'admin' => route($backRoute),
                    default => route($backRoute, $order),
                };
            @endphp
            <a class="dash-btn-sm dash-btn-outline" href="{{ $backUrl }}">Back to order</a>
        </div>

        @if (session('success'))
            <div class="form-notice">{{ session('success') }}</div>
        @endif

        <section class="dash-panel u-extracted-ebdbcd4a73">
            <div aria-live="polite">
                @forelse ($conversation?->messages ?? [] as $message)
                    <article class="u-extracted-a5249f670c">
                        <strong>{{ $message->sender->first_name }} {{ $message->sender->last_name }}</strong>
                        <small>{{ $message->created_at->format('M d, Y h:i A') }}</small>
                        <p class="u-extracted-a94916ebb1">{{ $message->body }}</p>
                    </article>
                @empty
                    <p>No messages yet. Use this conversation to coordinate this order with its assigned participants.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route($messageRoute, $order) }}" class="u-extracted-2a01802927">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="message-body">Message</label>
                    <textarea id="message-body" class="form-control" name="body" maxlength="2000" rows="4" required>{{ old('body') }}</textarea>
                    @error('body')
                        <p>{{ $message }}</p>
                    @enderror
                </div>
                <button class="btn-submit" type="submit">Send message</button>
            </form>
        </section>
    </main>
@endsection
