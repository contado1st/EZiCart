<?php

namespace App\Services;

use App\Models\BundleDeal;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FlashSaleService
{
    public function __construct(private AdministrativeAuditService $audits) {}

    public function create(User $seller, array $data): FlashSale
    {
        return DB::transaction(function () use ($seller, $data) {
            $seller = User::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            abort_unless($seller->role === 'seller' && $seller->status === 'approved', 403);
            $product = Product::whereKey($data['product_id'])->where('user_id', $seller->id)->lockForUpdate()->firstOrFail();
            $start = Carbon::parse($data['starts_at'], 'Asia/Manila')->setTimezone(config('app.timezone'))->startOfSecond();
            $end = Carbon::parse($data['ends_at'], 'Asia/Manila')->setTimezone(config('app.timezone'))->startOfSecond();
            $attributes = ['seller_id' => $seller->id, 'product_id' => $product->id, 'unit_cents' => CheckoutPricingService::cents($data['price']), 'quantity_limit' => (int) $data['quantity_limit'], 'starts_at' => $start, 'ends_at' => $end];
            $hash = hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
            $existing = FlashSale::where('seller_id', $seller->id)->where('operation_key', $data['operation_key'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'This flash-sale action was already submitted with different details.');

                return $existing;
            }
            if ($start->lt(now()) || $end->lte($start)) {
                throw ValidationException::withMessages(['ends_at' => 'Use a future start and an end after the start.']);
            }
            if ($attributes['quantity_limit'] < 1 || $attributes['quantity_limit'] > min(99999, $product->stock)) {
                throw ValidationException::withMessages(['quantity_limit' => 'Flash quantity must be positive and cannot exceed current product stock.']);
            }
            $prices = $product->variations()->where('is_active', true)->pluck('price_adjustment')->map(fn ($adjustment) => CheckoutPricingService::cents($product->price) + CheckoutPricingService::cents($adjustment));
            if ($prices->isEmpty()) {
                $prices->push(CheckoutPricingService::cents($product->price));
            }
            $sales = ProductSale::where('product_id', $product->id)->whereNull('ended_at')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->get();
            foreach ($prices as $original) {
                $minimum = $original;
                foreach ($sales as $sale) {
                    $candidate = $sale->kind === 'percent' ? intdiv($original * (100 - $sale->value) + 50, 100) : $sale->value;
                    $minimum = min($minimum, $candidate);
                }
                if ($attributes['unit_cents'] < 100 || $attributes['unit_cents'] >= $minimum) {
                    throw ValidationException::withMessages(['price' => 'Flash price must be at least PHP 1.00 and lower than every overlapping ordinary/store-sale option.']);
                }
            }
            if (FlashSale::where('product_id', $product->id)->whereNull('ended_at')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'This product already has a flash slot in that period.']);
            }
            if (BundleDeal::where('seller_id', $seller->id)->whereNull('ended_at')->where('starts_at', '<', $end)->where('ends_at', '>', $start)
                ->where(fn ($q) => $q->where('gift_product_id', $product->id)->orWhereHas('products', fn ($p) => $p->whereKey($product->id)))->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'Flash sales and bundles cannot overlap on the same product. End the conflicting offer first.']);
            }
            $slot = FlashSale::create([...$attributes, 'operation_key' => $data['operation_key'], 'request_hash' => $hash]);
            $this->audits->record($seller, $slot, 'flash_sale_created', [], $slot->only(['product_id', 'unit_cents', 'quantity_limit', 'starts_at', 'ends_at']), 'Limited seller-funded flash slot. Physical stock is reserved only by actual orders.');

            return $slot;
        }, 3);
    }

    public function end(User $seller, FlashSale $slot): void
    {
        DB::transaction(function () use ($seller, $slot) {
            abort_unless($seller->role === 'seller' && $seller->status === 'approved', 403);
            Product::whereKey($slot->product_id)->where('user_id', $seller->id)->lockForUpdate()->firstOrFail();
            $locked = FlashSale::whereKey($slot->id)->where('seller_id', $seller->id)->lockForUpdate()->firstOrFail();
            if (! $locked->ended_at) {
                $locked->update(['ended_at' => now()]);
                DB::table('flash_reminders')->where('flash_sale_id', $locked->id)->whereNull('notified_at')->update(['cancelled_at' => now(), 'updated_at' => now()]);
                $this->audits->record($seller, $locked, 'flash_sale_ended', [], ['ended_at' => $locked->ended_at], 'Ended new flash allocations and pending reminders; existing order prices are retained.');
            }
        });
    }

    /** Caller holds product, variation and flash row locks through placement. */
    public function reserve(Order $order, array $lines): void
    {
        $demand = [];
        foreach ($lines as $line) {
            if ($id = $line['pricing_snapshot']['flash_sale_id'] ?? null) {
                $demand[$id] = ($demand[$id] ?? 0) + $line['quantity'];
            }
        }
        ksort($demand);
        foreach ($demand as $id => $quantity) {
            if (DB::table('flash_reservations')->where('flash_sale_id', $id)->where('order_id', $order->id)->exists()) {
                continue;
            }
            $updated = FlashSale::whereKey($id)->whereNull('ended_at')->where('starts_at', '<=', now())->where('ends_at', '>', now())
                ->whereRaw('allocated_quantity + ? <= quantity_limit', [$quantity])->increment('allocated_quantity', $quantity);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['quote' => 'Flash availability changed. Review the current price before ordering.']);
            }
            DB::table('flash_reservations')->insert(['flash_sale_id' => $id, 'order_id' => $order->id, 'quantity' => $quantity, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Called by the existing single inventory-restoration transaction after product locks. */
    public function releaseCancelled(Order $order): void
    {
        if ($order->status !== 'CANCELLED' || $order->delivered_at !== null || $order->picked_up_at !== null) {
            return;
        }
        $reservations = DB::table('flash_reservations')->where('order_id', $order->id)->whereNull('released_at')->orderBy('flash_sale_id')->get();
        foreach ($reservations as $reservation) {
            $slot = FlashSale::whereKey($reservation->flash_sale_id)->lockForUpdate()->firstOrFail();
            if (DB::table('flash_reservations')->where('id', $reservation->id)->whereNull('released_at')->update(['released_at' => now(), 'updated_at' => now()]) !== 1) {
                continue;
            }
            if (! $slot->ended_at && $slot->ends_at->isFuture()) {
                abort_unless($slot->allocated_quantity >= $reservation->quantity, 422, 'Flash allocation requires reconciliation.');
                $slot->decrement('allocated_quantity', $reservation->quantity);
            }
        }
    }
}
