@extends('layouts.app')
@section('title', 'Review category deletion | EZiCart')
@section('content')
<main class="feature-page"><header><h1>Delete {{ $category->name }}?</h1></header>@include('components.feature-feedback')
    <section class="feature-panel"><h2>Review the affected categories</h2><ul>@foreach($categories as $affected)<li>{{ $affected->name }}</li>@endforeach</ul>
        <p>{{ $product_count }} unique products are affected. Products with no remaining category will appear in Uncategorized. Other memberships, platform categories, product availability and order history are preserved.</p>
        <div class="web-actions"><a class="feature-button" href="{{ route('seller.categories.index') }}">Cancel</a><form method="POST" action="{{ route('seller.categories.destroy', $category) }}">@csrf @method('DELETE')<input type="hidden" name="preview_token" value="{{ $token }}"><button type="submit" class="feature-button">Confirm category deletion</button></form></div>
    </section>
</main>
@endsection
