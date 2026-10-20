@extends('layouts.app')
@section('title', 'Saved addresses — EZiCart')
@push('styles')
    @vite('resources/css/shared/dashboard.css')
    <link rel="stylesheet" href="{{ asset('css/buyer-addresses.css') }}?v={{ filemtime(public_path('css/buyer-addresses.css')) }}">
@endpush
@section('content')
<main class="buyer-address-page">
    <header class="dash-header">
        <div><h1 class="dash-title">Saved addresses</h1><p class="dash-subtitle">Keep home, work and other delivery locations ready for checkout.</p></div>
        <div class="buyer-address-actions">
            @if (session('cart'))<a href="{{ route('checkout.index') }}" class="dash-btn-sm dash-btn-outline">Back to checkout</a>@endif
            <a href="{{ route('buyer.addresses.create') }}" class="dash-btn-primary"><x-shop-icon name="location" /> Add an address</a>
        </div>
    </header>
    @if (session('success'))<p class="dash-alert-success" role="status">{{ session('success') }}</p>@endif
    @if ($errors->any())<div class="buyer-address-error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="buyer-address-list">
        @forelse ($addresses as $address)
            <article class="dash-panel buyer-address-card">
                <div class="buyer-address-card-heading"><h2>{{ $address->label }}</h2>@if ($address->is_default)<span class="buyer-address-default">Default</span>@endif</div>
                <x-buyer-address-summary :address="$address" />
                <div class="buyer-address-actions">
                    <a href="{{ route('buyer.addresses.edit', $address) }}" class="dash-btn-sm dash-btn-outline">Edit address</a>
                    @unless ($address->is_default)
                        <form action="{{ route('buyer.addresses.default', $address) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="dash-btn-sm dash-btn-outline">Set as default</button></form>
                    @endunless
                    <form action="{{ route('buyer.addresses.destroy', $address) }}" method="POST" onsubmit="return confirm('Remove this saved address? Existing orders will keep their original delivery details.')">@csrf @method('DELETE')<button type="submit" class="buyer-address-delete">Remove</button></form>
                </div>
            </article>
        @empty
            <div class="dash-panel buyer-address-empty"><x-shop-icon name="location" /><h2>No saved addresses yet</h2><p>Add a delivery address to use it again at checkout. Your first saved address becomes the default.</p><a href="{{ route('buyer.addresses.create') }}" class="dash-btn-primary">Add your first address</a></div>
        @endforelse
    </div>
    <div class="workspace-pagination">{{ $addresses->links() }}</div>
    <p class="buyer-address-help">The default address is selected automatically at checkout. You can choose a different saved address for each purchase.</p>
</main>
@endsection
