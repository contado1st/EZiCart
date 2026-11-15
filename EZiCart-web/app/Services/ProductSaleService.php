<?php

namespace App\Services;

use App\Models\FlashSale;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductSaleService
{
    public function __construct(private AdministrativeAuditService $audits) {}

    public function save(User $seller, array $data, ?ProductSale $sale = null): ProductSale
    {
        return DB::transaction(function () use ($seller, $data, $sale) {
            abort_unless($seller->role === 'seller' && $seller->status === 'approved', 403);
            User::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $product = Product::whereKey($data['product_id'])->where('user_id', $seller->id)->lockForUpdate()->firstOrFail();
            if ($sale) {
                $sale = ProductSale::whereKey($sale->id)->where('seller_id', $seller->id)->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $sale->version === (int) $data['version'] && ! $sale->ended_at && $sale->ends_at->isFuture(), 409, 'This sale changed or ended. Refresh before editing.');
            }
            $start = Carbon::parse($data['starts_at'], 'Asia/Manila')->setTimezone(config('app.timezone'));
            $end = Carbon::parse($data['ends_at'], 'Asia/Manila')->setTimezone(config('app.timezone'));
            $value = $data['kind'] === 'percent' ? (int) $data['value'] : CheckoutPricingService::cents($data['value']);
            if (($data['kind'] === 'percent' && ((float) $data['value'] !== (float) $value || $value < 1 || $value > 99)) || ($data['kind'] === 'fixed' && $value < 100)) {
                throw ValidationException::withMessages(['value' => 'Use a whole percentage from 1 to 99, or a sale price of at least PHP 1.00.']);
            }
            $attributes = ['product_id' => $product->id, 'seller_id' => $seller->id, 'kind' => $data['kind'], 'value' => $value, 'starts_at' => $start, 'ends_at' => $end];
            $hash = hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
            if (! $sale && ($existing = ProductSale::where('seller_id', $seller->id)->where('operation_key', $data['operation_key'])->first())) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'This sale action was already submitted with different details.');

                return $existing;
            }
            if ($end->lte($start) || $end->lte(now()) || (! $sale && $start->lt(now())) || ($sale && ! $start->equalTo($sale->starts_at))) {
                throw ValidationException::withMessages(['ends_at' => 'End after the start and current time. New sales cannot start in the past; edit the discount or end time of an existing sale.']);
            }
            $overlap = ProductSale::where('product_id', $product->id)->whereNull('ended_at')->where('starts_at', '<', $end)->where('ends_at', '>', $start)
                ->when($sale, fn ($q) => $q->whereKeyNot($sale->id))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_at' => 'This product already has a sale in that time period.']);
            }
            $prices = $product->variations()->where('is_active', true)->pluck('price_adjustment')->map(fn ($adjustment) => CheckoutPricingService::cents($product->price) + CheckoutPricingService::cents($adjustment));
            if ($prices->isEmpty()) {
                $prices->push(CheckoutPricingService::cents($product->price));
            }
            foreach ($prices as $original) {
                $effective = $data['kind'] === 'percent' ? intdiv($original * (100 - $value) + 50, 100) : $value;
                if (FlashSale::where('product_id', $product->id)->whereNull('ended_at')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->where('unit_cents', '>=', $effective)->exists()) {
                    throw ValidationException::withMessages(['value' => 'End the overlapping flash slot before setting a store price at or below its flash price.']);
                }
                if ($effective < 100 || $effective >= $original) {
                    throw ValidationException::withMessages(['value' => 'Every active option must have a sale price of at least PHP 1.00, below its original price.']);
                }
            }
            $before = $sale?->only(['kind', 'value', 'starts_at', 'ends_at', 'version']) ?? [];
            $sale ??= new ProductSale(['operation_key' => $data['operation_key'], 'request_hash' => $hash, 'version' => 0]);
            $sale->fill([...$attributes, 'version' => $sale->version + 1])->save();
            $this->audits->record($seller, $sale, 'product_sale_saved', $before, $sale->only(['kind', 'value', 'starts_at', 'ends_at', 'version']), 'Seller-funded price reduction; previously placed orders retain their snapshot.');

            return $sale;
        }, 3);
    }

    public function end(User $seller, ProductSale $sale): void
    {
        DB::transaction(function () use ($seller, $sale) {
            abort_unless($seller->role === 'seller' && $seller->status === 'approved', 403);
            Product::whereKey($sale->product_id)->where('user_id', $seller->id)->lockForUpdate()->firstOrFail();
            $locked = ProductSale::whereKey($sale->id)->where('seller_id', $seller->id)->lockForUpdate()->firstOrFail();
            if ($locked->ended_at) {
                return;
            }
            $locked->forceFill(['ended_at' => now(), 'version' => $locked->version + 1])->save();
            $this->audits->record($seller, $locked, 'product_sale_ended', [], ['ended_at' => $locked->ended_at], 'Seller ended the offer. Existing order prices are retained.');
        });
    }
}
