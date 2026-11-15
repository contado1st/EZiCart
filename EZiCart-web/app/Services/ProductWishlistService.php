<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductWishlistService
{
    public function set(User $buyer, Product $product, bool $saved): void
    {
        DB::transaction(function () use ($buyer, $product, $saved): void {
            $actor = User::query()->whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->role === 'buyer' && $actor->status === 'approved', 403);
            if ($saved) {
                $listing = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                abort_unless($listing->isListingVisible()
                    && $listing->seller?->role === 'seller' && $listing->seller?->status === 'approved', 404);
                $actor->wishlistedProducts()->syncWithoutDetaching([$listing->id]);
            } else {
                $actor->wishlistedProducts()->detach($product->id);
            }
        }, 3);
    }
}
