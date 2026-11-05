<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopAvailabilityService
{
    public function assertOpen(array $cart): void
    {
        $ids = array_map(fn ($item) => $item['product_id'] ?? $item['id'] ?? 0, $cart);
        $sellerIds = Product::whereKey($ids)->pluck('user_id')->unique()->sort()->values();
        $sellers = User::whereKey($sellerIds)->orderBy('id');
        if (DB::transactionLevel() > 0) {
            $sellers->lockForUpdate();
        }
        foreach ($sellers->get() as $seller) {
            if (! $seller->acceptsWebOrders()) {
                throw ValidationException::withMessages(['cart' => 'The shop '.($seller->business_name ?: $seller->first_name).' is temporarily unavailable for new orders.']);
            }
        }
    }
}
