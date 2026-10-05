<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;

class InventoryRestorationService
{
    public function restore(Order $order): void
    {
        $order->loadMissing('items');

        if ($order->inventory_restored_at !== null) {
            return;
        }

        foreach ($order->items as $item) {
            if ($item->product_id !== null) {
                Product::query()->whereKey($item->product_id)->increment('stock', $item->quantity);
            }

            if ($item->variation_id !== null) {
                ProductVariation::query()->whereKey($item->variation_id)->increment('stock', $item->quantity);
            }
        }

        $order->forceFill(['inventory_restored_at' => now()])->save();
    }
}
