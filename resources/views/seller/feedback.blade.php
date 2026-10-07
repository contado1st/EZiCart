@extends('layouts.seller')

@section('title', 'Customer Feedback - EZiCart Seller')

@section('content')
<div class="dash-header">
    <div>
        <h1 class="dash-title">Customer Feedback & Reviews</h1>
        <p class="dash-subtitle">Monitor customer satisfaction, product ratings, and reviews from verified purchases.</p>
    </div>
</div>

<!-- Ratings Summary Card -->
<div class="dash-panel" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
        <div style="text-align: center; padding: 1rem 2rem; border-right: 1px solid var(--slate-200);">
            <div style="font-size: 3rem; font-weight: 800; color: var(--slate-800); line-height: 1;">
                {{ number_format($avgRating, 1) }}
            </div>
            <div style="color: #f59e0b; font-size: 1.25rem; margin: 0.25rem 0;">
                @for($i = 1; $i <= 5; $i++)
                    {{ $i <= round($avgRating) ? '★' : '☆' }}
                @endfor
            </div>
            <div style="font-size: 0.8rem; color: var(--slate-500);">
                Overall Store Rating
            </div>
        </div>

        <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--slate-800); margin: 0 0 0.25rem 0;">
                Customer Reviews ({{ $totalReviews }})
            </h3>
            <p style="font-size: 0.85rem; color: var(--slate-500); margin: 0;">
                Ratings are provided exclusively by buyers who completed their orders.
            </p>
        </div>
    </div>
</div>

<!-- Reviews List -->
<div class="dash-panel">
    @forelse($reviews as $review)
        <div style="padding: 1.25rem 0; border-bottom: 1px solid var(--slate-100); display: flex; gap: 1rem; align-items: flex-start;">
            @if($review->product && $review->product->image_path)
                <img src="{{ asset('storage/' . $review->product->image_path) }}" alt="{{ $review->product->name }}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid var(--slate-200); flex-shrink: 0;">
            @else
                <div style="width: 50px; height: 50px; background: var(--slate-100); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">📦</div>
            @endif

            <div style="flex: 1;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                    <div>
                        <strong style="color: var(--slate-800); font-size: 0.95rem;">
                            {{ $review->buyer->first_name ?? 'Buyer' }} {{ substr($review->buyer->last_name ?? '', 0, 1) }}.
                        </strong>
                        <span style="font-size: 0.75rem; color: var(--slate-400); margin-left: 0.5rem;">
                            {{ $review->created_at->format('M d, Y') }}
                        </span>
                    </div>
                    <div style="color: #f59e0b; font-size: 0.95rem;">
                        @for($i = 1; $i <= 5; $i++)
                            {{ $i <= $review->rating ? '★' : '☆' }}
                        @endfor
                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--slate-700); margin-left: 4px;">{{ $review->rating }}.0</span>
                    </div>
                </div>

                <div style="font-size: 0.85rem; font-weight: 600; color: var(--ez-primary); margin-bottom: 0.35rem;">
                    Product: {{ $review->product->name ?? 'Product' }}
                </div>

                <div style="font-size: 0.9rem; color: var(--slate-700); line-height: 1.5; background: var(--slate-50); padding: 0.6rem 0.85rem; border-radius: 6px;">
                    "{{ $review->comment ?? 'Customer gave a rating without written feedback.' }}"
                </div>
            </div>
        </div>
    @empty
        <div style="text-align: center; padding: 3rem 1rem; color: var(--slate-400);">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">⭐</div>
            <h3 style="color: var(--slate-700); margin-bottom: 0.25rem;">No reviews yet</h3>
            <p style="font-size: 0.85rem;">Reviews submitted by buyers after parcel completion will be listed here.</p>
        </div>
    @endforelse

    <div style="margin-top: 1.5rem;">
        {{ $reviews->links() }}
    </div>
</div>
@endsection

