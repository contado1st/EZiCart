<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Order $order)
    {
        abort_if($order->buyer_id !== auth()->id(), 403);

        if ($order->status !== 'COMPLETED') {
            return back()->with('error', 'You can only review products from completed orders.');
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'nullable|string|max:1000',
        ]);

        $itemPurchased = $order->items()->where('product_id', $validated['product_id'])->exists();
        if (!$itemPurchased) {
            return back()->with('error', 'This product is not part of this order.');
        }

        $alreadyReviewed = Review::where('order_id', $order->id)
            ->where('product_id', $validated['product_id'])
            ->exists();

        if ($alreadyReviewed) {
            return back()->with('error', 'You have already reviewed this product for this order.');
        }

        Review::create([
            'order_id'   => $order->id,
            'product_id' => $validated['product_id'],
            'buyer_id'   => auth()->id(),
            'rating'     => $validated['rating'],
            'comment'    => $validated['comment'] ?? null,
        ]);

        return back()->with('success', 'Thank you! Your review and rating have been posted.');
    }
}