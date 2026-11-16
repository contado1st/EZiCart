<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\ReviewPhoto;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    public function store(Request $request, Order $order, ReviewService $reviews)
    {
        abort_if($order->buyer_id !== $this->authenticatedUser()->id, 403);

        if ($order->status !== 'COMPLETED') {
            return back()->with('error', 'You can only review products from completed orders.');
        }

        $validated = $request->validate(['product_id' => 'required|exists:products,id', ...$reviews->rules()]);

        $itemPurchased = $order->items()->where('product_id', $validated['product_id'])->exists();
        if (! $itemPurchased) {
            return back()->with('error', 'This product is not part of this order.');
        }

        $alreadyReviewed = Review::where('order_id', $order->id)
            ->where('product_id', $validated['product_id'])
            ->exists();

        if ($alreadyReviewed) {
            return back()->with('error', 'You have already reviewed this product for this order.');
        }

        $reviews->save($request->user(), $order, $validated, $request->file('photos', []));

        return back()->with('success', 'Thank you! Your review and rating have been posted.');
    }

    public function edit(Request $request, Review $review, ReviewService $reviews)
    {
        $reviews->authorizeOwner($review, $request->user());
        $review->load(['photos', 'order.items.product']);
        $history = $review->revisions()->paginate(25)->withQueryString();
        $historyPhotoIds = $history->getCollection()->flatMap(fn ($revision) => $revision->snapshot['photo_ids'] ?? []);
        $historyPhotos = ReviewPhoto::where('review_id', $review->id)->whereIn('id', $historyPhotoIds)->get()->keyBy('id');

        return response()->view('buyer.reviews.edit', compact('review', 'history', 'historyPhotos'))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, Review $review, ReviewService $reviews)
    {
        $reviews->authorizeOwner($review, $request->user());
        $data = $request->validate(['product_id' => ['prohibited'], ...$reviews->rules($review)]);
        $reviews->save($request->user(), $review->order, $data, $request->file('photos', []), $review);

        return redirect()->route('buyer.reviews.edit', $review)->with('success', 'Review updated. Previous versions remain in your private revision history.');
    }

    public function photo(Request $request, Review $review, ReviewPhoto $photo)
    {
        abort_unless((int) $photo->review_id === $review->id, 404);
        $actor = $request->user();
        $approved = $actor?->status === 'approved';
        $owner = $approved && $actor->role === 'buyer' && $actor->id === (int) $review->buyer_id && $actor->id === (int) $review->order?->buyer_id;
        $admin = $approved && $actor->isCommerceAdmin();
        $seller = $approved && $actor->role === 'seller' && $actor->id === (int) $review->order?->seller_id;
        $public = $review->product?->isListingVisible();
        abort_unless($owner || $admin || ($photo->removed_at === null && ($seller || $public)), 404);
        abort_unless(Storage::disk('private')->exists($photo->path), 404);
        abort_unless(in_array($photo->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return Storage::disk('private')->response($photo->path, 'review-photo', ['Content-Type' => $photo->mime_type,
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox"], 'inline');
    }
}
