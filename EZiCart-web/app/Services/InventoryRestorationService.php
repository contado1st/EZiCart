<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\ReturnInspectionItem;
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
            $inspection = $locked->status === 'RETURNED_TO_SELLER' ? ReturnInspectionItem::where('order_id', $locked->id)->get()->keyBy('order_item_id') : collect();
            abort_if($inspection->isNotEmpty() && $inspection->count() !== $items->count(), 422, 'The recorded inspection is incomplete. Ask administration to review.');
            Product::query()->whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();
            ProductVariation::query()->whereIn('id', $items->pluck('variation_id'))->orderBy('id')->lockForUpdate()->get();
            foreach ($items as $item) {
                $line = $inspection->get($item->id);
                $quantity = $line ? $line->restock_quantity : $item->quantity;
                abort_if($inspection->isNotEmpty() && (! $line || $line->expected_quantity !== (int) $item->quantity || $quantity < 0 || $quantity > $item->quantity || $quantity + $line->excluded_quantity !== $line->expected_quantity), 422, 'Item quantities changed after inspection. Review the historical records.');
                if ($quantity === 0) {
                    continue;
                }
                if ($item->product_id !== null) {
                    Product::query()->whereKey($item->product_id)->increment('stock', $quantity);
                }
                if ($item->variation_id !== null) {
                    ProductVariation::query()->whereKey($item->variation_id)->where('product_id', $item->product_id)->increment('stock', $quantity);
                }
            }
            $locked->forceFill(['inventory_restored_at' => now()])->save();
        }, 3);
    }
}
