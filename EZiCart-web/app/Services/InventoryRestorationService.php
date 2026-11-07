<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\DB;

class InventoryRestorationService
{
    public function restore(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->inventory_restored_at !== null) {
                return;
            }
            abort_if($locked->status === 'RETURNED_TO_SELLER' && ($locked->restock_inspected_at === null || $locked->restock_approved !== true), 422, 'The return must be inspected and approved for restocking.');
            $items = $locked->items()->get();
            Product::query()->whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();
            ProductVariation::query()->whereIn('id', $items->pluck('variation_id'))->orderBy('id')->lockForUpdate()->get();
            foreach ($items as $item) {
                if ($item->product_id !== null) {
                    Product::query()->whereKey($item->product_id)->increment('stock', $item->quantity);
                }
                if ($item->variation_id !== null) {
                    ProductVariation::query()->whereKey($item->variation_id)->where('product_id', $item->product_id)->increment('stock', $item->quantity);
                }
            }
            $locked->forceFill(['inventory_restored_at' => now()])->save();
        }, 3);
    }
}
