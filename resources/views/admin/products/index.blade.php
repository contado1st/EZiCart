@extends('layouts.admin')

@section('title', 'Product Moderation - Admin Portal')

@section('content')
<div class="dash-header">
    <div>
        <h1 class="dash-title">Product Catalog Moderation</h1>
        <p class="dash-subtitle">Review seller product submissions, inspect multiple images and variations, and approve or reject listings.</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="{{ route('admin.dashboard') }}" class="dash-btn-sm dash-btn-outline" style="text-decoration: none;">← Back to Dashboard</a>
    </div>
</div>

@if(session('success'))
    <div class="dash-alert-success" style="margin-bottom: 1.5rem;">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="dash-alert-danger" style="margin-bottom: 1.5rem; background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px;">❌ {{ session('error') }}</div>
@endif

<!-- Filter Tabs -->
<div style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--slate-200); padding-bottom: 0.75rem; flex-wrap: wrap;">
    <a href="{{ route('admin.products.index', ['status' => 'pending']) }}" 
       style="text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; font-size: 0.875rem; {{ $status === 'pending' ? 'background: var(--ez-primary); color: white;' : 'background: var(--slate-100); color: var(--slate-600);' }}">
        ⏳ Pending Review ({{ $pendingCount }})
    </a>
    <a href="{{ route('admin.products.index', ['status' => 'approved']) }}" 
       style="text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; font-size: 0.875rem; {{ $status === 'approved' ? 'background: var(--ez-primary); color: white;' : 'background: var(--slate-100); color: var(--slate-600);' }}">
        ✅ Approved ({{ $approvedCount }})
    </a>
    <a href="{{ route('admin.products.index', ['status' => 'rejected']) }}" 
       style="text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; font-size: 0.875rem; {{ $status === 'rejected' ? 'background: var(--ez-primary); color: white;' : 'background: var(--slate-100); color: var(--slate-600);' }}">
        ❌ Rejected ({{ $rejectedCount }})
    </a>
    <a href="{{ route('admin.products.index', ['status' => 'all']) }}" 
       style="text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; font-size: 0.875rem; {{ $status === 'all' ? 'background: var(--ez-primary); color: white;' : 'background: var(--slate-100); color: var(--slate-600);' }}">
        📑 All Products
    </a>
</div>

<div class="dash-table-wrapper">
    <table class="dash-table">
        <thead>
            <tr>
                <th>Product Details</th>
                <th>Seller / Merchant</th>
                <th>Pricing & Discount</th>
                <th>Stock & Specs</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            @if($product->image_path)
                                <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}" style="width: 48px; height: 48px; object-fit: cover; border-radius: 8px; border: 1px solid var(--slate-200);">
                            @else
                                <div style="width: 48px; height: 48px; background: var(--slate-100); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">📦</div>
                            @endif
                            <div>
                                <div style="font-weight: 700; color: var(--slate-900);">{{ $product->name }}</div>
                                <div style="font-size: 0.75rem; color: var(--slate-500);">Category: {{ $product->category }}</div>
                                <div style="font-size: 0.7rem; color: var(--slate-400);">{{ $product->images->count() }} gallery image(s)</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: var(--slate-800);">{{ $product->seller->business_name ?? $product->seller->first_name . ' ' . $product->seller->last_name }}</div>
                        <div style="font-size: 0.75rem; color: var(--slate-500);">{{ $product->seller->email }}</div>
                    </td>
                    <td>
                        @if($product->has_discount)
                            <div>
                                <span style="font-weight: 800; color: var(--ez-primary);">₱{{ number_format($product->discounted_price, 2) }}</span>
                                <span style="text-decoration: line-through; font-size: 0.75rem; color: var(--slate-400); margin-left: 0.25rem;">₱{{ number_format($product->price, 2) }}</span>
                            </div>
                            <span style="background: #fef2f2; color: #dc2626; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">
                                {{ $product->discount_type === 'percent' ? $product->discount_value . '% OFF' : '₱' . number_format($product->discount_value, 2) . ' OFF' }}
                            </span>
                        @else
                            <span style="font-weight: 800; color: var(--slate-900);">₱{{ number_format($product->price, 2) }}</span>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: 600; font-size: 0.85rem;">{{ $product->stock }} units</div>
                        <div style="font-size: 0.75rem; color: var(--slate-500);">
                            {{ $product->variations->count() > 0 ? $product->variations->count() . ' Variation(s)' : 'No variations' }}
                        </div>
                    </td>
                    <td>
                        @if($product->status === 'approved')
                            <span class="dash-badge dash-badge-active" style="background: #dcfce7; color: #15803d;">✅ Approved</span>
                        @elseif($product->status === 'rejected')
                            <span class="dash-badge dash-badge-danger" style="background: #fee2e2; color: #b91c1c;">❌ Rejected</span>
                            @if($product->rejection_reason)
                                <div style="font-size: 0.7rem; color: #b91c1c; margin-top: 0.25rem; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $product->rejection_reason }}">
                                    Reason: {{ $product->rejection_reason }}
                                </div>
                            @endif
                        @else
                            <span class="dash-badge dash-badge-pending" style="background: #fef3c7; color: #b45309;">⏳ Pending Review</span>
                        @endif
                    </td>
                    <td style="text-align: right;">
                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; align-items: center;">
                            <a href="{{ route('admin.products.show', $product->id) }}" class="dash-btn-sm dash-btn-outline" style="text-decoration: none;">
                                Inspect
                            </a>
                            @if($product->status !== 'approved')
                                <form action="{{ route('admin.products.approve', $product->id) }}" method="POST" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="dash-btn-sm" style="background: #16a34a; color: white; border: none; border-radius: 6px; padding: 0.4rem 0.75rem; cursor: pointer; font-weight: 600;">
                                        Approve
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="padding: 3rem; text-align: center; color: var(--slate-500);">
                        No products found in this status category.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top: 1.5rem;">
    {{ $products->links() }}
</div>
@endsection
