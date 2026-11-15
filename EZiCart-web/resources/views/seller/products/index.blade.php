@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/dashboard.css')
@endpush

@section('content')
    <div class="dash-wrapper">
        <!-- Sidebar Navigation -->
<!-- Main Workspace -->
        <main class="dash-main">
            <div class="dash-header">
                <div>
                    <h1 class="dash-title">Inventory Management</h1>
                    <p class="dash-subtitle">Manage products, update stock levels, and monitor prices.</p>
                </div>
                <div class="web-actions"><a href="{{ route('seller.product-drafts.index') }}" class="dash-btn-outline">Private drafts</a><a href="{{ route('seller.products.create') }}" class="dash-btn-primary">+ Add New Product</a></div>
            </div>

            @include('components.feature-feedback')
            <div class="web-actions"><a class="dash-btn-outline" href="{{ route('seller.categories.index') }}">Store categories</a><a class="dash-btn-outline" href="{{ route('seller.sales.index') }}">Sales and purchase limits</a><a class="dash-btn-outline" href="{{ route('seller.reviews.index') }}">Customer reviews</a></div>
            @if(session('catalog_results'))<section class="feature-panel" role="status"><h2>Bulk action results</h2><ul>@foreach(session('catalog_results') as $result)<li>{{ $result['name'] }} (#{{ $result['product_id'] }}): {{ $result['outcome'] }}</li>@endforeach</ul></section>@endif
            <form id="catalog-bulk" method="POST" action="{{ route('seller.products.bulk') }}" class="feature-panel feature-form" data-decision="catalog-bulk" data-decision-title="Apply this inventory action?" data-decision-message="Selected products will be paused or removed. Products with unfinished orders are skipped when deleting; historical records are preserved." onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf
                <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <label>Selected products<select name="action"><option value="deactivate">Pause sales</option><option value="delete">Remove eligible products</option></select></label><button type="submit" class="dash-btn-outline">Apply to selected</button><span>Select products using their checkboxes below.</span>
            </form>
            <div class="dash-table-wrapper">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Select / Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th class="u-extracted-13cbe03b9a">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td>
                                    <label><input type="checkbox" name="products[]" form="catalog-bulk" value="{{ $product->id }}" aria-label="Select {{ $product->name }}"></label>
                                    <div class="u-extracted-2f29807bdf">
                                        @if ($product->image_path)
                                            <img src="{{ asset('storage/' . $product->image_path) }}"
                                                alt="{{ $product->name }}" class="u-extracted-aac99b4b41">
                                        @else
                                            <div class="u-extracted-c70ac6556c">
                                                <x-shop-icon name="package1" /></div>
                                        @endif
                                        <div>
                                            <div class="u-extracted-8fa6b099d3">
                                                {{ $product->name }}</div>
                                            <div class="u-extracted-cf0c302441">
                                                SKU-{{ str_pad($product->id, 5, '0', STR_PAD_LEFT) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="dash-badge dash-badge-category">{{ $product->category_label }}</span></td>
                                <td class="u-extracted-6b1af052d3">₱{{ number_format($product->price, 2) }}</td>
                                <td>
                                    <span
                                        class="seller-stock {{ $product->stock < 10 ? 'seller-stock--low' : 'seller-stock--ok' }}">
                                        {{ $product->stock }} units
                                    </span>
                                </td>
                                <td>
                                    @if (! $product->is_seller_active)
                                        <span class="dash-badge dash-badge-archived">Seller inactive</span>
                                    @endif
                                    @if ($product->is_archived)
                                        <span class="dash-badge dash-badge-archived">Archived</span>
                                    @elseif($product->compliance_status !== 'approved')
                                        <span
                                            class="dash-badge dash-badge-archived">{{ str_replace('_', ' ', ucfirst($product->compliance_status)) }}</span>
                                        @if ($product->compliance_note)
                                            <div class="text-muted-small">{{ $product->compliance_note }}</div>
                                        @endif
                                    @else
                                        <span class="dash-badge dash-badge-active">Active</span>
                                    @endif
                                </td>
                                <td class="u-extracted-13cbe03b9a">
                                    <div class="u-extracted-af0aae5706">
                                        <a href="{{ route('seller.products.edit', $product->id) }}"
                                            class="dash-btn-sm dash-btn-outline">Edit</a>
                                        <a class="dash-btn-sm dash-btn-outline" href="{{ route('seller.categories.index', ['product_id' => $product->id]) }}#product-categories">Categories</a>
                                        <form method="POST" action="{{ route('seller.products.duplicate', $product) }}">@csrf<input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><button class="dash-btn-sm dash-btn-outline" type="submit">Duplicate to draft</button></form>
                                        <form action="{{ route('seller.products.availability', $product) }}" method="POST">@csrf @method('PATCH')<input type="hidden" name="is_seller_active" value="{{ $product->is_seller_active ? '0' : '1' }}"><button class="dash-btn-sm dash-btn-outline" type="submit">{{ $product->is_seller_active ? 'Pause sales' : 'Resume sales' }}</button></form>
                                        <form action="{{ route('seller.products.destroy', $product->id) }}" method="POST" data-decision="seller.products.destroy" data-decision-title="Remove this product?" data-decision-message="This removes the product from your inventory. Review the selected product before continuing." data-decision-label="Remove product" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dash-btn-sm dash-btn-danger">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="u-extracted-a3521f1d48">
                                    No products found. Start adding inventory to your store!
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $products->links() }}
        </main>
    </div>
@endsection
