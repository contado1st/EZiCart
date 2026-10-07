@extends('layouts.admin')

@section('title', 'Review ' . $product->name . ' - Admin Portal')

@section('content')
<div class="dash-header">
    <div>
        <a href="{{ route('admin.products.index') }}" style="color: var(--slate-500); text-decoration: none; font-size: 0.875rem; font-weight: 600;">← Back to Product Queue</a>
        <h1 class="dash-title" style="margin-top: 0.5rem;">Review Product: {{ $product->name }}</h1>
        <p class="dash-subtitle">Listed by <strong>{{ $product->seller->business_name ?? $product->seller->first_name . ' ' . $product->seller->last_name }}</strong> &bull; Category: <strong>{{ $product->category }}</strong></p>
    </div>
    <div>
        @if($product->status === 'approved')
            <span class="dash-badge dash-badge-active" style="background: #dcfce7; color: #15803d; font-size: 0.875rem; padding: 0.5rem 1rem;">✅ Approved & Live</span>
        @elseif($product->status === 'rejected')
            <span class="dash-badge dash-badge-danger" style="background: #fee2e2; color: #b91c1c; font-size: 0.875rem; padding: 0.5rem 1rem;">❌ Rejected</span>
        @else
            <span class="dash-badge dash-badge-pending" style="background: #fef3c7; color: #b45309; font-size: 0.875rem; padding: 0.5rem 1rem;">⏳ Pending Review</span>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="dash-alert-success" style="margin-bottom: 1.5rem;">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="dash-alert-danger" style="margin-bottom: 1.5rem; background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px;">❌ {{ session('error') }}</div>
@endif

@if($product->status === 'rejected' && $product->rejection_reason)
    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
        <div style="font-weight: 700; color: #991b1b; margin-bottom: 0.25rem;">⚠️ Rejection Reason:</div>
        <div style="color: #7f1d1d;">{{ $product->rejection_reason }}</div>
    </div>
@endif

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
    <!-- Left Column: Details, Images, Variations, Discounts, Voucher -->
    <div>
        <!-- 1. Product Images Gallery -->
        <div style="background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid var(--slate-200); margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-900); margin-bottom: 1rem;">
                📸 Uploaded Product Images ({{ $product->images->count() }})
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 1rem;">
                @forelse($product->images as $img)
                    <div style="position: relative; border-radius: 8px; overflow: hidden; border: 2px solid {{ $img->is_primary ? 'var(--ez-primary)' : 'var(--slate-200)' }};">
                        <img src="{{ asset('storage/' . $img->image_path) }}" alt="{{ $product->name }}" style="width: 100%; height: 130px; object-fit: cover;">
                        @if($img->is_primary)
                            <span style="position: absolute; bottom: 0; left: 0; right: 0; background: var(--ez-primary); color: white; font-size: 0.65rem; font-weight: 700; text-align: center; padding: 2px 0;">
                                ★ PRIMARY
                            </span>
                        @endif
                    </div>
                @empty
                    @if($product->image_path)
                        <div style="border-radius: 8px; overflow: hidden; border: 1px solid var(--slate-200);">
                            <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}" style="width: 130px; height: 130px; object-fit: cover;">
                        </div>
                    @else
                        <div style="color: var(--slate-500); font-style: italic;">No images uploaded for this product.</div>
                    @endif
                @endforelse
            </div>
        </div>

        <!-- 2. Product Specs & Pricing -->
        <div style="background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid var(--slate-200); margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-900); margin-bottom: 1rem;">
                🏷️ Pricing, Inventory & Information
            </h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: var(--slate-50); padding: 1rem; border-radius: 8px;">
                    <span style="font-size: 0.75rem; color: var(--slate-500); display: block;">Base Price</span>
                    <span style="font-size: 1.25rem; font-weight: 800; color: var(--slate-900);">₱{{ number_format($product->price, 2) }}</span>
                </div>
                <div style="background: var(--slate-50); padding: 1rem; border-radius: 8px;">
                    <span style="font-size: 0.75rem; color: var(--slate-500); display: block;">Discounted Buyer Price</span>
                    <span style="font-size: 1.25rem; font-weight: 800; color: var(--ez-primary);">₱{{ number_format($product->discounted_price, 2) }}</span>
                    @if($product->has_discount)
                        <span style="font-size: 0.75rem; color: #dc2626; font-weight: 700;">({{ $product->discount_percent }}% OFF)</span>
                    @endif
                </div>
                <div style="background: var(--slate-50); padding: 1rem; border-radius: 8px;">
                    <span style="font-size: 0.75rem; color: var(--slate-500); display: block;">Total Base Stock</span>
                    <span style="font-size: 1.1rem; font-weight: 700; color: var(--slate-800);">{{ $product->stock }} units</span>
                </div>
                <div style="background: var(--slate-50); padding: 1rem; border-radius: 8px;">
                    <span style="font-size: 0.75rem; color: var(--slate-500); display: block;">Category</span>
                    <span style="font-size: 1.1rem; font-weight: 700; color: var(--slate-800);">{{ $product->category }}</span>
                </div>
            </div>

            <div>
                <h4 style="font-size: 0.9rem; font-weight: 700; color: var(--slate-700); margin-bottom: 0.5rem;">Description</h4>
                <div style="background: var(--slate-50); padding: 1rem; border-radius: 8px; color: var(--slate-800); font-size: 0.9rem; line-height: 1.6; white-space: pre-wrap;">
                    {{ $product->description ?: 'No detailed description provided.' }}
                </div>
            </div>
        </div>

        <!-- 3. Variations -->
        <div style="background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid var(--slate-200); margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-900); margin-bottom: 1rem;">
                🧩 Product Variations ({{ $product->variations->count() }})
            </h3>
            @if($product->variations->count() > 0)
                <table class="dash-table" style="font-size: 0.85rem;">
                    <thead>
                        <tr>
                            <th>Option</th>
                            <th>Image</th>
                            <th>Price / Adjustment</th>
                            <th>Stock</th>
                            <th>SKU</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($product->variations as $var)
                            <tr>
                                <td>
                                    <strong>{{ $var->type }}:</strong> {{ $var->value }}
                                </td>
                                <td>
                                    @if($var->image_path)
                                        <img src="{{ asset('storage/' . $var->image_path) }}" alt="{{ $var->value }}" style="width: 36px; height: 36px; object-fit: cover; border-radius: 4px;">
                                    @else
                                        <span style="color: var(--slate-400); font-style: italic;">No image</span>
                                    @endif
                                </td>
                                <td>
                                    @if($var->price !== null && $var->price > 0)
                                        ₱{{ number_format($var->price, 2) }}
                                    @else
                                        {{ $var->price_adjustment >= 0 ? '+' : '' }}₱{{ number_format($var->price_adjustment, 2) }}
                                    @endif
                                </td>
                                <td>{{ $var->stock }}</td>
                                <td>{{ $var->sku ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p style="color: var(--slate-500); font-style: italic; margin: 0;">This product has no variations configured.</p>
            @endif
        </div>

        <!-- 4. Product Voucher (if any) -->
        @php
            $productVoucher = $product->vouchers()->latest()->first();
        @endphp
        <div style="background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid var(--slate-200);">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-900); margin-bottom: 1rem;">
                🎟️ Product Voucher Promotion
            </h3>
            @if($productVoucher && $productVoucher->is_active)
                <div style="background: #fdf2f8; border: 1px dashed var(--ez-primary); border-radius: 8px; padding: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-family: monospace; font-size: 1.25rem; font-weight: 800; color: var(--ez-primary);">
                            {{ $productVoucher->code }}
                        </span>
                        <span style="background: #dcfce7; color: #15803d; font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 999px;">
                            Active
                        </span>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--slate-700); line-height: 1.5;">
                        <div><strong>Discount:</strong> {{ $productVoucher->type === 'percent' ? $productVoucher->value . '%' : '₱' . number_format($productVoucher->value, 2) }} OFF</div>
                        <div><strong>Minimum Purchase:</strong> ₱{{ number_format($productVoucher->min_spend, 2) }}</div>
                        @if($productVoucher->max_discount)
                            <div><strong>Maximum Discount Cap:</strong> ₱{{ number_format($productVoucher->max_discount, 2) }}</div>
                        @endif
                        @if($productVoucher->start_date)
                            <div><strong>Start Date:</strong> {{ $productVoucher->start_date->format('M d, Y') }}</div>
                        @endif
                        @if($productVoucher->expires_at)
                            <div><strong>Expiration:</strong> {{ $productVoucher->expires_at->format('M d, Y') }}</div>
                        @endif
                    </div>
                </div>
            @else
                <p style="color: var(--slate-500); font-style: italic; margin: 0;">No active product promotional voucher configured.</p>
            @endif
        </div>
    </div>

    <!-- Right Column: Approval / Rejection Action Panel -->
    <div>
        <!-- Action Box -->
        <div style="background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid var(--slate-200); position: sticky; top: 1rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-900); margin-bottom: 1rem;">
                ⚖️ Moderation Decision
            </h3>

            <!-- Approve Action -->
            <form action="{{ route('admin.products.approve', $product->id) }}" method="POST" style="margin-bottom: 1.5rem;">
                @csrf
                <div style="font-size: 0.85rem; color: var(--slate-600); margin-bottom: 0.75rem;">
                    Approving this product makes it instantly available and purchasable by buyers in the marketplace.
                </div>
                <button type="submit" class="dash-btn-primary" style="width: 100%; background: #16a34a; border-color: #16a34a; font-weight: 700; padding: 0.75rem; justify-content: center;">
                    ✅ Approve Product
                </button>
            </form>

            <hr style="border: 0; border-top: 1px solid var(--slate-200); margin: 1.5rem 0;">

            <!-- Reject Action -->
            <form action="{{ route('admin.products.reject', $product->id) }}" method="POST">
                @csrf
                <div style="font-size: 0.85rem; color: var(--slate-600); margin-bottom: 0.75rem;">
                    Rejecting this product removes it from the catalog and notifies the seller with your feedback reason.
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 700; display: block; margin-bottom: 0.35rem;">Rejection Reason *</label>
                    <textarea name="rejection_reason" class="controls-textarea" rows="3" placeholder="e.g., Product images do not meet requirements, pricing issue, or incorrect category." required>{{ old('rejection_reason', $product->rejection_reason) }}</textarea>
                </div>
                <button type="submit" class="dash-btn-sm dash-btn-danger" style="width: 100%; padding: 0.75rem; font-weight: 700; border-radius: 8px;">
                    ❌ Reject Product
                </button>
            </form>

            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--slate-100); font-size: 0.75rem; color: var(--slate-400);">
                Submitted on {{ $product->created_at->format('M d, Y h:i A') }}<br>
                Last updated {{ $product->updated_at->format('M d, Y h:i A') }}
            </div>
        </div>
    </div>
</div>
@endsection
