@extends('layouts.app')
@section('title', 'For you | EZiCart')
@section('content')
@include('components.feature-feedback')
<div class="feature-page">
    <header><div><h1>For you</h1><p>Products from categories you browse and purchase. New shoppers see the latest available listings.</p></div><a class="feature-button" href="{{ route('search.index') }}">Search all products</a></header>
    <form method="POST" action="{{ route('buyer.for-you.reset') }}">@csrf @method('DELETE')<button class="feature-button" type="submit">Clear browsing history</button></form>
    <div class="feature-grid">@forelse ($products as $product)
        <a class="feature-product" href="{{ route('product.show', $product) }}">@if ($product->image_path)<img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" loading="lazy" width="300" height="300">@endif<strong>{{ $product->name }}</strong><small>{{ $product->seller?->business_name }}</small><span class="feature-product-price">₱{{ number_format($product->price, 2) }}</span><small>{{ $product->catalog_review_count ? number_format($product->catalog_rating, 1).' / 5' : 'No ratings yet' }}</small></a>
    @empty<p>No products are currently available.</p>@endforelse</div>
    {{ $products->links() }}
</div>
@endsection
