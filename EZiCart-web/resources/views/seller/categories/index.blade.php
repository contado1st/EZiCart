@extends('layouts.app')
@section('title', 'Store categories | EZiCart')
@section('content')
<main class="feature-page">
    <header><div><h1>Store categories</h1><p>Organize your shop independently of marketplace product categories. Up to two levels and three categories per product.</p></div><a class="feature-button" href="{{ route('seller.products.index') }}">Back to inventory</a></header>
    @include('components.feature-feedback')
    <nav class="web-actions" aria-label="Category workspace"><a class="feature-button" href="#category-list">Manage categories</a><a class="feature-button" href="#new-category">New category</a>@if($selectedProduct)<a class="feature-button" href="#product-categories">Product membership</a>@endif</nav>
    @if($selectedProduct)
    <section class="feature-panel" id="product-categories"><h2>Categories for {{ $selectedProduct->name }}</h2><p>Platform category: {{ $selectedProduct->category_label }}. Clear all selections to use Uncategorized.</p>
        <form method="POST" action="{{ route('seller.categories.assign', $selectedProduct) }}" class="feature-form">@csrf @method('PATCH')
            <label>Store categories (choose up to three)<select name="categories[]" multiple size="6">@foreach($options as $option)<option value="{{ $option->id }}" @selected(in_array($option->id, old('categories', $selectedProduct->storeCategories->modelKeys())))>{{ $options->firstWhere('id', $option->parent_id)?->name ? $options->firstWhere('id', $option->parent_id)->name.' / ' : '' }}{{ $option->name }}{{ $option->is_visible ? '' : ' (hidden)' }}</option>@endforeach</select></label>
            <button class="feature-button" type="submit">Save product categories</button>
        </form>
    </section>
    @endif
    <section class="feature-panel" id="category-list"><h2>Your categories</h2><p>Position applies among siblings. Hiding a parent also hides its subcategories from shop navigation. To organize a product, choose Categories beside it in Inventory.</p>
        <div class="feature-table-scroll"><table class="feature-table"><thead><tr><th>Category / products</th><th>Edit category</th><th>Actions</th></tr></thead><tbody>
        @forelse($categories as $category)<tr><td>{{ $category->parent ? $category->parent->name.' / ' : '' }}{{ $category->name }}<br>{{ $category->products_count }} products · {{ $category->is_visible ? 'Visible' : 'Hidden' }}</td><td>
            <form method="POST" action="{{ route('seller.categories.update', $category) }}" class="feature-form">@csrf @method('PATCH')
                <label>Name<input name="name" value="{{ $category->name }}" maxlength="50" required></label>
                <label>Parent<select name="parent_id"><option value="">Top level</option>@foreach($options->whereNull('parent_id')->where('id', '!=', $category->id) as $parent)<option value="{{ $parent->id }}" @selected($category->parent_id === $parent->id)>{{ $parent->name }}</option>@endforeach</select></label>
                <label>Position<input type="number" name="position" min="1" max="200" value="{{ $category->sort_order }}" required></label>
                <label>Visibility<select name="is_visible"><option value="1" @selected($category->is_visible)>Visible</option><option value="0" @selected(!$category->is_visible)>Hidden</option></select></label>
                <button type="submit" class="feature-button">Save changes</button>
            </form>
        </td><td><a class="feature-button" href="{{ route('seller.categories.preview', $category) }}">Review deletion</a></td></tr>@empty<tr><td colspan="3">No store categories yet. Your products remain available under Uncategorized.</td></tr>@endforelse
        </tbody></table></div>{{ $categories->links() }}
    </section>
    <section class="feature-panel" id="new-category"><h2>Create category</h2><p>Names must be unique within your shop. This workspace supports up to 200 categories.</p>
        <form method="POST" action="{{ route('seller.categories.store') }}" class="feature-form">@csrf
            <label>Name<input name="name" maxlength="50" value="{{ old('name') }}" required></label>
            <label>Parent<select name="parent_id"><option value="">Top level</option>@foreach($options->whereNull('parent_id') as $parent)<option value="{{ $parent->id }}">{{ $parent->name }}</option>@endforeach</select></label>
            <label>Position<input type="number" name="position" min="1" max="200" value="1" required></label>
            <label>Visibility<select name="is_visible"><option value="1">Visible</option><option value="0">Hidden</option></select></label><button class="feature-button" type="submit">Create category</button>
        </form>
    </section>
</main>
@endsection
