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
            ParcelIncidentService::assertClear($locked);
            if ($locked->inventory_restored_at !== null) {
                return;
            }
            abort_if($locked->status === 'RETURNED_TO_SELLER' && ($locked->restock_inspected_at === null || $locked->restock_approved !== true), 422, 'The return must be inspected and approved for restocking.');
            $items = $locked->items()->get();
            $inspection = $locked->status === 'RETURNED_TO_SELLER' ? ReturnInspectionItem::where('order_id', $locked->id)->get()->keyBy('order_item_id') : collect();
            abort_if($inspection->isNotEmpty() && $inspection->count() !== $items->count(), 422, 'The recorded inspection is incomplete. Ask administration to review.');
            Product::query()->whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();
            $variations = ProductVariation::query()->whereIn('id', $items->pluck('variation_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $item) {
                $line = $inspection->get($item->id);
                $quantity = $line ? $line->restock_quantity : $item->quantity;
                abort_if($inspection->isNotEmpty() && (! $line || $line->expected_quantity !== (int) $item->quantity || $quantity < 0 || $quantity > $item->quantity || $quantity + $line->excluded_quantity !== $line->expected_quantity), 422, 'Item quantities changed after inspection. Review the historical records.');
                if ($quantity === 0) {
                    continue;
                }
                $variation = $item->variation_id ? $variations->get($item->variation_id) : null;
                $retired = $variation && (int) $variation->product_id === (int) $item->product_id && ! $variation->is_active;
                if ($item->product_id !== null && ! $retired) {
                    Product::query()->whereKey($item->product_id)->increment('stock', $quantity);
                }
                if ($item->variation_id !== null) {
                    ProductVariation::query()->whereKey($item->variation_id)->where('product_id', $item->product_id)->increment('stock', $quantity);
                }
                if ($retired) {
                    app(AdministrativeAuditService::class)->record(null, $variation, 'retired_variation_stock_restored', [], ['order_id' => $locked->id, 'order_item_id' => $item->id, 'quantity' => $quantity], 'Units restored to a retired option only; review and explicitly reactivate/reconcile them before resale. Sellable product stock unchanged.');
                }
            }
            app(FlashSaleService::class)->releaseCancelled($locked);
            $locked->forceFill(['inventory_restored_at' => now()])->save();
        }, 3);
    }
}
