@extends('layouts.app')
@section('title', 'Stock alerts | EZiCart')
@section('content')
@include('components.feature-feedback')
<div class="feature-page"><header><div><h1>Stock alerts</h1><p>Watch a product from its detail page. Changes are checked every minute and sent to your notifications and email.</p></div></header>
<div class="feature-grid">@forelse ($products as $product)<article class="feature-product"><strong>{{ $product->name }}</strong><p>{{ ucfirst($states[$product->id]) }}</p>@if ($states[$product->id] !== 'unavailable')<a href="{{ route('product.show', $product) }}">View product</a>@endif<form action="{{ route('buyer.stock.unwatch', $product) }}" method="POST">@csrf @method('DELETE')<button type="submit" class="feature-button">Stop alerts</button></form></article>@empty<p>You have no watched products yet.</p>@endforelse</div>{{ $products->links() }}</div>
@endsection
