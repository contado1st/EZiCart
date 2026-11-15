@extends('layouts.app')
@section('title', 'My wishlist | EZiCart')
@push('styles')
    @vite('resources/css/shared/dashboard.css')
@endpush
@section('content')
<main class="dash-main feature-page">
    <header class="dash-header"><div><h1 class="dash-title">My wishlist</h1><p class="dash-subtitle">Products saved to your account, separate from your cart and followed shops. Saving does not reserve price or stock.</p></div><a class="feature-button" href="{{ route('search.index') }}">Browse products</a></header>
    @include('components.feature-feedback')
    <section class="dash-panel">
        <h2>{{ $products->total() }} saved {{ Str::plural('product', $products->total()) }}</h2>
        <div class="feature-table-scroll"><table class="feature-table"><thead><tr><th>Product</th><th>Shop</th><th>Current price</th><th>Availability</th><th>Action</th></tr></thead><tbody>
            @forelse ($cards as $card)
                @php($product = $card['product'])
                <tr><td>@if($card['available'])<a href="{{ route('product.show', $product) }}">{{ $product->name }}</a>@else Saved product currently unavailable @endif</td>
                    <td>{{ $card['available'] ? ($product->seller->business_name ?: 'Seller shop') : 'Unavailable' }}</td>
                    <td>{{ $card['available'] ? $product->display_price_label : '—' }}</td><td>{{ $card['available'] ? 'Available · select options on product page' : 'Not available for purchase' }}</td>
                    <td><form method="POST" action="{{ route('buyer.wishlist.destroy', $product) }}">@csrf @method('DELETE')<button type="submit" class="feature-button">Remove from wishlist</button></form></td>
                </tr>
            @empty<tr><td colspan="5">Your wishlist is empty. Open a product and select Save to wishlist.</td></tr>@endforelse
        </tbody></table></div>
        {{ $products->links() }}
    </section>
</main>
@endsection
