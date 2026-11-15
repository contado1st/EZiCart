<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ProductPurchaseLimitService
{
    /** Placement holds the buyer and product locks before checking all purchased/reserved units. */
    public function assertDemand(User $buyer, array $demand): void
    {
        foreach (Product::whereIn('id', array_keys($demand))->whereNotNull('purchase_limit')->get() as $product) {
            $purchased = (int) OrderItem::where('product_id', $product->id)->whereHas('order', fn ($q) => $q->where('buyer_id', $buyer->id)->where('status', '!=', 'CANCELLED'))->sum('quantity');
            if ($purchased + $demand[$product->id] > $product->purchase_limit) {
                throw ValidationException::withMessages(['quantity' => 'Purchase limit reached for '.$product->name.'. Limit: '.$product->purchase_limit.' units per buyer across all options. Cancelled or expired reservations release their units after cancellation is recorded.']);
            }
        }
    }
}
