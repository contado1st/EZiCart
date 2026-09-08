@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
@endpush

@section('content')
<div class="admin-container">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title">Inventory Management</h1>
            <p class="admin-page-desc">Manage your products, monitor stock levels, and set prices.</p>
        </div>
        <a href="{{ route('seller.products.create') }}" class="btn-primary">+ Add New Product</a>
    </div>

    @if(session('success'))
        <div class="alert-success">✅ {{ session('success') }}</div>
    @endif

    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                @if($product->image_path)
                                    <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}" style="width: 48px; height: 48px; object-fit: cover; border-radius: 0.375rem;">
                                @else
                                    <div style="width: 48px; height: 48px; background-color: var(--slate-100); border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">📦</div>
                                @endif
                                <div class="text-dark-bold">{{ $product->name }}</div>
                            </div>
                        </td>
                        <td><span class="role-badge role-courier">{{ $product->category }}</span></td>
                        <td class="text-dark-bold">₱{{ number_format($product->price, 2) }}</td>
                        <td>
                            <span class="{{ $product->stock < 10 ? 'text-danger' : 'text-success' }}" style="font-weight: 700;">
                                {{ $product->stock }} units
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <a href="#" style="color: var(--slate-500); text-decoration: none; margin-right: 0.5rem;">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-row">No products found. Start adding inventory to your store!</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection