@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/app.css')
@endpush

@section('content')
    <div class="admin-container u-extracted-6c0f8d518b">
        <div class="admin-header-row">
            <div>
                <h1 class="admin-page-title">Edit Product</h1>
                <p class="admin-page-desc">Modify product details, pricing, and stock status.</p>
            </div>
            <a href="{{ route('seller.products.index') }}" class="u-extracted-4839300e95">← Back to Inventory</a>
        </div>

        <div class="admin-table-wrapper u-extracted-a4812596aa">
            <form action="{{ route('seller.products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}"
                        required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-control" required>
                            @foreach (['Electronics', 'Fashion', 'Home & Living', 'Beauty', 'Groceries'] as $cat)
                                <option value="{{ $cat }}" {{ $product->category === $cat ? 'selected' : '' }}>
                                    {{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Update Product Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        @if ($product->image_path)
                            <span class="text-muted-small u-extracted-c97152cfd7">Current:
                                {{ basename($product->image_path) }}</span>
                        @endif
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Price (₱) *</label>
                        <input type="number" step="0.01" name="price" class="form-control"
                            value="{{ old('price', $product->price) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Current Stock Units *</label>
                        <input type="number" name="stock" class="form-control"
                            value="{{ old('stock', $product->stock) }}" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4">{{ old('description', $product->description) }}</textarea>
                </div>

                <div class="form-group u-extracted-0dc8cc6234">
                    <label class="u-extracted-23a9b0e3fb">
                        <input type="checkbox" name="is_archived" value="1"
                            {{ $product->is_archived ? 'checked' : '' }}>
                        <span>Archive Product (hide from active customer catalog)</span>
                    </label>
                </div>

                <button type="submit" class="btn-primary u-extracted-3b6349e4b7">Update Product</button>
            </form>
        </div>
    </div>
@endsection
