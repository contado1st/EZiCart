@extends('layouts.app')
@section('title', ($searchTerm !== '' ? 'Search: '.$searchTerm : 'Browse products').' — EZiCart')
@section('meta-description', 'Find products and shops on EZiCart. Filter by category, price, customer rating and seller location.')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/catalog-search.css') }}?v={{ filemtime(public_path('css/catalog-search.css')) }}">
    <script src="{{ asset('js/catalog-search.js') }}?v={{ filemtime(public_path('js/catalog-search.js')) }}" defer></script>
@endpush
@section('content')
<div class="catalog-search">
    <nav class="catalog-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><span>Search results</span></nav>
    <header class="catalog-heading">
        <div><h1>@if ($searchTerm !== '')Results for <span>“{{ $searchTerm }}”</span>@else Discover products @endif</h1><p>{{ number_format($products->total()) }} {{ $products->total() === 1 ? 'product' : 'products' }} found{{ $searchTerm !== '' ? ' across the marketplace' : ' from EZiCart sellers' }}.</p></div>
        @if (!auth()->check() || auth()->user()->role === 'buyer')
            <a href="{{ route('buyer.dashboard') }}" class="catalog-orders-link"><x-shop-icon name="package1" /> My purchases</a>
        @endif
    </header>
    @if ($errors->any())<div class="catalog-error" role="alert"><strong>Check your search filters.</strong><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    <div class="catalog-layout">
        <aside class="catalog-filter-sidebar" aria-label="Product filters">
            <details class="catalog-filters" data-catalog-filters open>
                <summary><span><x-shop-icon name="checklist" /> Search filters</span>@if (count($activeFilters))<span class="catalog-filter-count">{{ count($activeFilters) }}</span>@endif</summary>
                <form action="{{ route('search.index') }}" method="GET" class="catalog-filter-form">
                    <input type="hidden" name="search" value="{{ $searchTerm }}">
                    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                    <fieldset><legend>Category</legend><div class="catalog-category-options">
                        <label><input type="radio" name="category" value="" @checked(empty($filters['category']))><span>All categories</span></label>
                        @foreach ($categories as $category)
                            <label><input type="radio" name="category" value="{{ $category }}" @checked(($filters['category'] ?? '') === $category)><span>{{ $category }}</span></label>
                        @endforeach
                    </div></fieldset>
                    <fieldset><legend>Price range</legend><div class="catalog-price-inputs">
                        <div><label for="catalog-min-price">Min (₱)</label><input id="catalog-min-price" type="number" name="min_price" value="{{ $filters['min_price'] ?? '' }}" min="0" max="9999999999.99" step="0.01" placeholder="0"></div>
                        <span aria-hidden="true">–</span>
                        <div><label for="catalog-max-price">Max (₱)</label><input id="catalog-max-price" type="number" name="max_price" value="{{ $filters['max_price'] ?? '' }}" min="0" max="9999999999.99" step="0.01" placeholder="Any"></div>
                    </div></fieldset>
                    <div class="catalog-filter-field"><label for="catalog-rating">Customer rating</label><select id="catalog-rating" name="rating"><option value="">All ratings</option>@foreach ([5, 4, 3, 2, 1] as $rating)<option value="{{ $rating }}" @selected((int) ($filters['rating'] ?? 0) === $rating)>{{ $rating }} stars{{ $rating < 5 ? ' & up' : '' }}</option>@endforeach</select></div>
                    <div class="catalog-filter-field"><label for="catalog-province">Ships from</label><select id="catalog-province" name="province"><option value="">All locations</option>@foreach ($provinces as $province)<option value="{{ $province }}" @selected(($filters['province'] ?? '') === $province)>{{ $province }}</option>@endforeach</select></div>
                    <button class="catalog-apply-button" type="submit">Apply filters</button>
                    <a class="catalog-clear-link" href="{{ route('search.index', ['search' => $searchTerm, 'sort' => $filters['sort']]) }}">Clear all filters</a>
                </form>
            </details>
        </aside>
        <section class="catalog-results" aria-label="Search results">
            <div class="catalog-sortbar"><span>Sort by</span><nav aria-label="Sort products">@foreach ($sortOptions as $sortValue => $sortLabel)<a href="{{ route('search.index', array_merge(\Illuminate\Support\Arr::except($filters, 'page'), ['sort' => $sortValue])) }}" @class(['catalog-sort-option', 'is-active' => $filters['sort'] === $sortValue]) @if ($filters['sort'] === $sortValue) aria-current="true" @endif>{{ $sortLabel }}</a>@endforeach</nav><span class="catalog-page-number">{{ $products->currentPage() }} / {{ max(1, $products->lastPage()) }}</span></div>
            @if ($activeFilters)
                <div class="catalog-active-filters" aria-label="Applied filters"><span>Selected:</span>@foreach ($activeFilters as $filter)<a href="{{ route('search.index', $filter['query']) }}" aria-label="Remove {{ $filter['label'] }}">{{ $filter['label'] }}<x-shop-icon name="close" /></a>@endforeach</div>
            @endif
            @if ($products->isNotEmpty())
                <p class="catalog-result-range">Showing {{ $products->firstItem() }}–{{ $products->lastItem() }} of {{ number_format($products->total()) }} products</p>
                <div class="catalog-product-grid">
                    @foreach ($products as $product)
                        <article class="catalog-product"><a class="catalog-product-main" href="{{ route('product.show', $product) }}">
                            <div class="catalog-product-image">@if ($product->image_path)<img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" width="400" height="400" loading="lazy" decoding="async">@else<span class="catalog-image-placeholder"><x-shop-icon name="package1" /><span>Product image unavailable</span></span>@endif</div>
                            <div class="catalog-product-content">
                                <span class="catalog-product-category">{{ $product->category }}</span>
                                <h2>{{ $product->name }}</h2>
                                <strong class="catalog-product-price">₱{{ number_format($product->price, 2) }}</strong>
                                <div class="catalog-product-rating">@if ($product->catalog_review_count > 0)<x-shop-icon name="star" /><span>{{ number_format($product->catalog_rating, 1) }}</span><small>({{ number_format($product->catalog_review_count) }})</small>@else<span>No ratings yet</span>@endif</div>
                                <div class="catalog-product-store">{{ $product->seller?->business_name ?: $product->seller?->first_name ?: 'EZiCart seller' }}</div>
                                @if ($product->seller?->province)<div class="catalog-product-location"><x-shop-icon name="location" /><span>{{ $product->seller->province }}</span></div>@endif
                            </div>
                        </a>@if($product->seller?->role === 'seller' && $product->seller?->status === 'approved')<a class="catalog-shop-link" href="{{ route('shops.show', $product->seller) }}">Visit shop <x-shop-icon name="arrowback" /></a>@endif</article>
                    @endforeach
                </div>
                <div class="catalog-pagination">{{ $products->links() }}</div>
            @else
                <section class="catalog-empty"><span class="catalog-empty-icon"><x-shop-icon name="search" /></span><h2>No matching products</h2><p>@if ($activeFilters)Try a wider price range or remove a filter to see more results.@elseif ($searchTerm !== '')Try a different keyword, a shorter product name, or a shop name.@else Available products will appear here when sellers add listings.@endif</p><a href="{{ route('search.index', $activeFilters ? ['search' => $searchTerm] : []) }}">{{ $activeFilters ? 'Clear filters' : 'Browse all products' }}</a></section>
            @endif
        </section>
    </div>
</div>
@endsection
