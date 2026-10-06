@extends('layouts.app')

@push('styles')
    @vite('resources/css/shared/app.css')
@endpush

@section('content')
    <div class="admin-container u-extracted-6c0f8d518b">
        <div class="admin-header-row">
            <div>
                <h1 class="admin-page-title">Add New Product</h1>
                <p class="admin-page-desc">New listings remain hidden from buyers until Product Compliance approves them.</p>
            </div>
            <a href="{{ route('seller.products.index') }}" class="u-extracted-4839300e95">← Back to Inventory</a>
        </div>

        <div class="admin-table-wrapper u-extracted-a4812596aa">
            <form action="{{ route('seller.products.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Electronics">Electronics</option>
                            <option value="Fashion">Fashion</option>
                            <option value="Home & Living">Home & Living</option>
                            <option value="Beauty">Beauty</option>
                            <option value="Groceries">Groceries</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Product Image (Optional)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Price (₱) *</label>
                        <input type="number" step="0.01" name="price" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Initial Stock *</label>
                        <input type="number" name="stock" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"></textarea>
                </div>

                <button type="submit" class="btn-primary u-extracted-1c5da340eb">Save Product</button>
            </form>
        </div>
    </div>
@endsection
